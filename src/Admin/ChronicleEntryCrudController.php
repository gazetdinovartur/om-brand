<?php

namespace App\Admin;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ChronicleEntryCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
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
                '<meta name="chronicle-featured-csrf" content="%s">',
                htmlspecialchars($this->csrfTokenManager->getToken('chronicle_featured')->getValue(), ENT_QUOTES)
            ))
            ->addCssFile('css/admin-chronicle-featured.css')
            ->addJsFile('js/admin-chronicle-featured.js');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Запись')
            ->setEntityLabelInPlural('Хроника')
            ->setDefaultSort(['publishedAt' => 'DESC', 'updatedAt' => 'DESC'])
            ->setSearchFields(['title', 'lede', 'slug', 'sourceKey', 'shortHash'])
            ->setPaginatorPageSize(40)
            ->setPaginatorRangeSize(3)
            ->setPageTitle(Crud::PAGE_INDEX, 'Хроника')
            ->setPageTitle(Crud::PAGE_NEW, 'Новая запись')
            ->showEntityActionsInlined();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Статус')->setChoices([
                'Черновик' => ChronicleStatus::Draft->value,
                'Запланировано' => ChronicleStatus::Scheduled->value,
                'Опубликовано' => ChronicleStatus::Published->value,
                'В архиве' => ChronicleStatus::Archived->value,
            ]))
            ->add(EntityFilter::new('series', 'Канал'))
            ->add(EntityFilter::new('era', 'Эпоха'))
            ->add(EntityFilter::new('tags', 'Тег'))
            ->add(BooleanFilter::new('isFeatured', 'Избранное'))
            ->add(BooleanFilter::new('isUnlisted', 'Unlisted'))
            ->add(DateTimeFilter::new('publishedAt', 'Дата публикации'))
            ->add(DateTimeFilter::new('createdAt', 'Создано'));
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
        yield IdField::new('id')->hideOnForm()->hideOnIndex();
        yield BooleanField::new('isFeatured', '♥')
            ->setTemplatePath('admin/chronicle/field_featured.html.twig')
            ->onlyOnIndex();
        yield TextField::new('title', 'Заголовок')
            ->setTemplatePath('admin/chronicle/field_title_link.html.twig');
        yield TextField::new('shortHash', 'Hash')->onlyOnIndex();
        yield ChoiceField::new('status', 'Статус')
            ->setChoices(array_combine(
                array_map(static fn (ChronicleStatus $s) => $s->label(), ChronicleStatus::cases()),
                ChronicleStatus::cases(),
            ));
        yield AssociationField::new('series', 'Канал');
        yield AssociationField::new('era', 'Эпоха');
        yield DateTimeField::new('publishedAt', 'Опубликован')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Изменён')->hideOnForm();
        yield TextField::new('sourceKey', 'Source')->onlyOnDetail();
        yield BooleanField::new('isUnlisted', 'Unlisted')->hideOnIndex();
    }
}
