<?php

namespace App\Admin;

use App\Entity\PaymentOffer;
use App\Enum\PaymentOfferStatus;
use App\Service\PaymentOfferService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PaymentOfferCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly PaymentOfferService $paymentOfferService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PaymentOffer::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile('js/admin-payment.js');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Оплата')
            ->setEntityLabelInPlural('Оплаты')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPageTitle(Crud::PAGE_INDEX, 'Оплаты')
            ->setPageTitle(Crud::PAGE_NEW, 'Новая ссылка на оплату')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать оплату')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Оплата')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action) => $action->setLabel('Создать оплату'))
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('token', 'Ссылки')
            ->setTemplatePath('admin/crud/payment_links.html.twig')
            ->hideOnIndex()
            ->hideWhenCreating();

        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('inquiry', 'Заявка');
        yield TextField::new('title', 'Название');
        yield IntegerField::new('amount', 'Сумма, ₽')
            ->setHelp('Целое число в рублях, например 5000. Ссылка СБП пересчитается при сохранении, если задан шаблон в настройках.')
            ->formatValue(static fn (int $value): string => number_format($value / 100, 0, ',', ' ').' ₽');

        yield TextField::new('sberPaymentUrl', 'Ссылка СБП')
            ->hideOnForm()
            ->setHelp('Заполняется автоматически из шаблона в настройках сайта.');

        yield ChoiceField::new('status', 'Статус')
            ->setChoices(array_combine(
                array_map(static fn (PaymentOfferStatus $s) => $s->label(), PaymentOfferStatus::cases()),
                PaymentOfferStatus::cases(),
            ));

        yield DateTimeField::new('expiresAt', 'Действует до');
        yield DateTimeField::new('paidAt', 'Оплачено')->hideOnForm();
        yield TextareaField::new('note', 'Заметка')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Создана')->hideOnForm();
    }

    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $builder = parent::createEditFormBuilder($entityDto, $formOptions, $context);
        $this->prepareAmountFieldForAdmin($builder, $entityDto->getInstance());

        return $builder;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof PaymentOffer) {
            $this->storeAmountFromAdminRubles($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);

        if ($entityInstance instanceof PaymentOffer) {
            $this->flashClientUrl($entityInstance, created: true);
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof PaymentOffer) {
            $this->storeAmountFromAdminRubles($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);

        if ($entityInstance instanceof PaymentOffer) {
            $this->flashClientUrl($entityInstance, created: false);
        }
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        if (\in_array($action, [Action::NEW, Action::EDIT], true)) {
            $entity = $context->getEntity()->getInstance();
            if ($entity instanceof PaymentOffer && null !== $entity->getId()) {
                $url = $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::DETAIL)
                    ->setEntityId($entity->getId())
                    ->generateUrl();

                return $this->redirect($url);
            }
        }

        /** @var RedirectResponse $response */
        $response = parent::getRedirectResponseAfterSave($context, $action);

        return $response;
    }

    private function flashClientUrl(PaymentOffer $offer, bool $created): void
    {
        $this->addFlash('success', sprintf(
            '%s Ссылка для клиента: %s',
            $created ? 'Оплата создана.' : 'Оплата обновлена.',
            $this->paymentOfferService->getClientUrl($offer),
        ));
    }

    private function prepareAmountFieldForAdmin(FormBuilderInterface $builder, mixed $entity): void
    {
        if (!$entity instanceof PaymentOffer || !$builder->has('amount')) {
            return;
        }

        $builder->get('amount')->setData((int) round($entity->getAmountRubles()));
    }

    private function storeAmountFromAdminRubles(PaymentOffer $offer): void
    {
        $rubles = max(0, $offer->getAmount());
        $offer->setAmount($rubles * 100);
    }
}
