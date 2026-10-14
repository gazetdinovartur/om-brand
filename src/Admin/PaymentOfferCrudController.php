<?php

namespace App\Admin;

use App\Entity\Inquiry;
use App\Entity\PaymentOffer;
use App\Enum\PaymentOfferStatus;
use App\Repository\InquiryRepository;
use App\Service\PaymentOfferService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

class PaymentOfferCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly PaymentOfferService $paymentOfferService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly InquiryRepository $inquiries,
        private readonly RequestStack $requestStack,
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
            ->setPageTitle(Crud::PAGE_NEW, 'Новая оплата')
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

    public function createEntity(string $entityFqcn): PaymentOffer
    {
        $offer = new PaymentOffer();

        $inquiryId = (int) ($this->requestStack->getCurrentRequest()?->query->get('inquiry') ?? 0);
        if ($inquiryId > 0) {
            $inquiry = $this->inquiries->find($inquiryId);
            if ($inquiry instanceof Inquiry) {
                $offer->setInquiry($inquiry);
                if ('' === $offer->getTitle()) {
                    $offer->setTitle(sprintf('Оплата — %s', $inquiry->getName()));
                }
            }
        }

        return $offer;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('token', 'Ссылки')
            ->setTemplatePath('admin/crud/payment_links.html.twig')
            ->hideOnIndex()
            ->hideWhenCreating();

        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('inquiry', 'Заявка')
            ->setHelp('Для чека ЮKassa нужен email в контакте заявки.');
        yield TextField::new('title', 'Название');
        yield IntegerField::new('amount', 'Сумма, ₽')
            ->setHelp('Целое число в рублях, например 5000. При создании сразу создаётся платёж в ЮKassa.')
            ->formatValue(static fn (int $value): string => number_format($value / 100, 0, ',', ' ').' ₽')
            ->setFormTypeOptions([
                // В БД — копейки; в админке показываем и принимаем рубли.
                'getter' => static fn (PaymentOffer $offer): int => (int) round($offer->getAmountRubles()),
                'setter' => static function (PaymentOffer $offer, ?int $rubles): void {
                    $offer->setAmount(max(0, (int) $rubles) * 100);
                },
            ]);

        yield TextField::new('paymentUrl', 'Ссылка на оплату')
            ->setHelp('confirmation_url ЮKassa')
            ->hideOnForm()
            ->hideOnIndex();

        yield TextField::new('yookassaPaymentId', 'ID платежа ЮKassa')
            ->hideOnForm()
            ->hideOnIndex();

        yield ChoiceField::new('status', 'Статус')
            ->setChoices(array_combine(
                array_map(static fn (PaymentOfferStatus $s) => $s->label(), PaymentOfferStatus::cases()),
                PaymentOfferStatus::cases(),
            ))
            ->setHelp('Меняется автоматически по webhook / возврату из ЮKassa.')
            ->hideOnForm();

        yield DateTimeField::new('expiresAt', 'Действует до');
        yield DateTimeField::new('paidAt', 'Оплачено')->hideOnForm();
        yield TextareaField::new('note', 'Заметка')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Создана')->hideOnForm();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);

        if (!$entityInstance instanceof PaymentOffer) {
            return;
        }

        $inquiry = $entityInstance->getInquiry();
        if (!$inquiry instanceof Inquiry) {
            $entityManager->remove($entityInstance);
            $entityManager->flush();
            throw new \InvalidArgumentException('Выберите заявку — оплата всегда привязана к заявке.');
        }

        try {
            $this->paymentOfferService->attachYookassaPayment(
                $entityInstance,
                $inquiry->getContact(),
                $entityInstance->getTitle(),
            );
        } catch (\Throwable $exception) {
            $entityManager->remove($entityInstance);
            $entityManager->flush();
            throw new \RuntimeException(
                'Не удалось создать платёж в ЮKassa: '.$exception->getMessage(),
                0,
                $exception,
            );
        }

        $this->flashClientUrl($entityInstance, created: true);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
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
}
