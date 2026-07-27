<?php

namespace App\Admin;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;
use App\Repository\ChronicleEraRepository;
use App\Repository\ChronicleSeriesRepository;
use App\Repository\ChronicleTagRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ChronicleEntryCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RequestStack $requestStack,
        private readonly ChronicleSeriesRepository $series,
        private readonly ChronicleEraRepository $eras,
        private readonly ChronicleTagRepository $tags,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ChronicleEntry::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addHtmlContentToBody(sprintf(
                '<meta name="chronicle-featured-csrf" content="%s">'
                .'<meta name="chronicle-reorder-csrf" content="%s">'
                .'<meta name="chronicle-reorder-url" content="%s">',
                htmlspecialchars($this->csrfTokenManager->getToken('chronicle_featured')->getValue(), ENT_QUOTES),
                htmlspecialchars($this->csrfTokenManager->getToken('chronicle_reorder')->getValue(), ENT_QUOTES),
                htmlspecialchars($this->generateUrl('admin_chronicle_reorder'), ENT_QUOTES),
            ))
            ->addCssFile('css/admin-chronicle-index.css')
            ->addJsFile('https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js')
            ->addJsFile('js/admin-chronicle-index.js');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Запись')
            ->setEntityLabelInPlural('Хроника')
            ->setDefaultSort(['sortOrder' => 'ASC', 'id' => 'DESC'])
            ->setSearchFields(['title', 'lede', 'slug', 'sourceKey', 'shortHash'])
            ->setPaginatorPageSize(40)
            ->setPaginatorRangeSize(3)
            ->setPageTitle(Crud::PAGE_INDEX, 'Хроника')
            ->setPageTitle(Crud::PAGE_NEW, 'Новая запись')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/chronicle/index.html.twig');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters;
    }

    public function configureActions(Actions $actions): Actions
    {
        $editor = Action::new('editor', false, 'fa fa-pen')
            ->linkToRoute('admin_chronicle_editor', static fn (ChronicleEntry $entry): array => ['id' => $entry->getId()])
            ->setHtmlAttributes(['title' => 'Редактор', 'aria-label' => 'Редактор'])
            ->addCssClass('chronicle-admin-row-action');

        $newEntry = Action::new('newEntry', 'Новая запись', 'fa fa-pen')
            ->linkToRoute('admin_chronicle_editor_new')
            ->createAsGlobalAction();

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $editor)
            ->add(Crud::PAGE_INDEX, $newEntry)
            ->update(
                Crud::PAGE_INDEX,
                Action::DELETE,
                static fn (Action $action): Action => $action
                    ->setLabel(false)
                    ->setIcon('fa fa-trash-alt')
                    ->setHtmlAttributes(['title' => 'Удалить', 'aria-label' => 'Удалить'])
                    ->addCssClass('chronicle-admin-row-action'),
            )
            ->reorder(Crud::PAGE_INDEX, ['editor', 'delete']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', ' ')
            ->setTemplatePath('admin/chronicle/field_drag.html.twig')
            ->setSortable(false)
            ->onlyOnIndex();
        yield BooleanField::new('isFeatured', '♥')
            ->setTemplatePath('admin/chronicle/field_featured.html.twig')
            ->onlyOnIndex();
        yield TextField::new('title', 'Заголовок')
            ->setTemplatePath('admin/chronicle/field_title_link.html.twig');
        yield ChoiceField::new('status', 'Статус')
            ->setChoices(array_combine(
                array_map(static fn (ChronicleStatus $s) => $s->label(), ChronicleStatus::cases()),
                ChronicleStatus::cases(),
            ))
            ->setTemplatePath('admin/chronicle/field_status.html.twig')
            ->onlyOnIndex();
        yield AssociationField::new('series', 'Канал');
        yield AssociationField::new('era', 'Эпоха');
        yield DateTimeField::new('publishedAt', 'Опубликован')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Изменён')->hideOnForm();
        yield TextField::new('sourceKey', 'Source')->onlyOnDetail();
        yield BooleanField::new('isUnlisted', 'Unlisted')->hideOnIndex();
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $templateName = (string) ($responseParameters->get('templateName') ?? '');
        if (!str_contains($templateName, 'index')) {
            return $responseParameters;
        }

        $cf = $this->readCfParams();
        $responseParameters->set('chronicleSeriesList', $this->series->findAllOrdered());
        $responseParameters->set('chronicleEraList', $this->eras->findAllOrdered());
        $responseParameters->set('chronicleTagList', $this->tags->findAllOrdered());
        $responseParameters->set('chronicleCf', $cf);
        $responseParameters->set('chronicleCfCount', $this->countActiveCf($cf));
        $responseParameters->set('chronicleStatuses', ChronicleStatus::cases());

        return $responseParameters;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $cf = $this->readCfParams();
        $alias = $qb->getRootAliases()[0] ?? 'entity';

        if (null !== $cf['status']) {
            $qb->andWhere(sprintf('%s.status = :cfStatus', $alias))
                ->setParameter('cfStatus', $cf['status']);
        }

        if (null !== $cf['series']) {
            $qb->andWhere(sprintf('%s.series = :cfSeries', $alias))
                ->setParameter('cfSeries', $cf['series']);
        }

        if (null !== $cf['era']) {
            $qb->andWhere(sprintf('%s.era = :cfEra', $alias))
                ->setParameter('cfEra', $cf['era']);
        }

        if (null !== $cf['tag']) {
            $qb->leftJoin(sprintf('%s.tags', $alias), 'cfTag')
                ->andWhere('cfTag.id = :cfTag')
                ->setParameter('cfTag', $cf['tag']);
        }

        if (null !== $cf['featured']) {
            $qb->andWhere(sprintf('%s.isFeatured = :cfFeatured', $alias))
                ->setParameter('cfFeatured', $cf['featured']);
        }

        if (null !== $cf['unlisted']) {
            $qb->andWhere(sprintf('%s.isUnlisted = :cfUnlisted', $alias))
                ->setParameter('cfUnlisted', $cf['unlisted']);
        }

        return $qb;
    }

    /**
     * @return array{
     *     status: ?ChronicleStatus,
     *     series: ?int,
     *     era: ?int,
     *     tag: ?int,
     *     featured: ?bool,
     *     unlisted: ?bool
     * }
     */
    private function readCfParams(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $raw = $request?->query->all('cf') ?? [];
        if (!\is_array($raw)) {
            $raw = [];
        }

        $status = null;
        $statusRaw = isset($raw['status']) ? (string) $raw['status'] : '';
        if ('' !== $statusRaw && 'all' !== $statusRaw) {
            $status = ChronicleStatus::tryFrom($statusRaw);
        }

        $series = isset($raw['series']) && '' !== (string) $raw['series'] ? (int) $raw['series'] : null;
        $era = isset($raw['era']) && '' !== (string) $raw['era'] ? (int) $raw['era'] : null;
        $tag = isset($raw['tag']) && '' !== (string) $raw['tag'] ? (int) $raw['tag'] : null;

        $featured = null;
        if (isset($raw['featured']) && '' !== (string) $raw['featured']) {
            $featured = '1' === (string) $raw['featured'] || 'true' === (string) $raw['featured'];
        }

        $unlisted = null;
        if (isset($raw['unlisted']) && '' !== (string) $raw['unlisted']) {
            $unlisted = '1' === (string) $raw['unlisted'] || 'true' === (string) $raw['unlisted'];
        }

        return [
            'status' => $status,
            'series' => $series > 0 ? $series : null,
            'era' => $era > 0 ? $era : null,
            'tag' => $tag > 0 ? $tag : null,
            'featured' => $featured,
            'unlisted' => $unlisted,
        ];
    }

    /**
     * @param array{
     *     status: ?ChronicleStatus,
     *     series: ?int,
     *     era: ?int,
     *     tag: ?int,
     *     featured: ?bool,
     *     unlisted: ?bool
     * } $cf
     */
    private function countActiveCf(array $cf): int
    {
        $count = 0;
        if (null !== $cf['status']) {
            ++$count;
        }
        if (null !== $cf['series']) {
            ++$count;
        }
        if (null !== $cf['era']) {
            ++$count;
        }
        if (null !== $cf['tag']) {
            ++$count;
        }
        if (null !== $cf['featured']) {
            ++$count;
        }
        if (null !== $cf['unlisted']) {
            ++$count;
        }

        return $count;
    }
}
