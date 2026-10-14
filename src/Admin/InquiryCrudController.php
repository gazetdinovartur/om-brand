<?php

namespace App\Admin;

use App\Entity\Inquiry;
use App\Enum\ContactType;
use App\Enum\InquiryStatus;
use App\Enum\InquiryType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;

class InquiryCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Inquiry::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Заявка')
            ->setEntityLabelInPlural('Заявки')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPageTitle(Crud::PAGE_INDEX, 'Заявки')
            ->setPageTitle(Crud::PAGE_NEW, 'Новая заявка')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать заявку')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Заявка')
            ->showEntityActionsInlined();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Статус')->setChoices([
                InquiryStatus::New->label() => InquiryStatus::New,
                InquiryStatus::Done->label() => InquiryStatus::Done,
            ]));
    }

    public function configureActions(Actions $actions): Actions
    {
        $close = Action::new('close', 'Закрыть')
            ->linkToCrudAction('closeInquiry')
            ->displayIf(static fn (Inquiry $inquiry): bool => InquiryStatus::New === $inquiry->getStatus());

        $invoice = Action::new('invoice', 'Выставить счёт', 'fa fa-ruble-sign')
            ->linkToUrl(function (Inquiry $inquiry): string {
                return $this->adminUrlGenerator
                    ->unsetAll()
                    ->setController(PaymentOfferCrudController::class)
                    ->setAction(Action::NEW)
                    ->set('inquiry', (string) $inquiry->getId())
                    ->generateUrl();
            });

        return $actions
            ->add(Crud::PAGE_INDEX, $close)
            ->add(Crud::PAGE_DETAIL, $close)
            ->add(Crud::PAGE_INDEX, $invoice)
            ->add(Crud::PAGE_DETAIL, $invoice)
            ->add(Crud::PAGE_DETAIL, Action::new('attachment', 'Скачать файл', 'fa fa-paperclip')
                ->linkToRoute('admin_inquiry_attachment', static fn (Inquiry $inquiry): array => ['id' => $inquiry->getId()])
                ->displayIf(static fn (Inquiry $inquiry): bool => $inquiry->hasAttachment()));
    }

    #[AdminRoute(path: '/{entityId}/close', name: 'close')]
    public function closeInquiry(AdminContext $context): RedirectResponse
    {
        $inquiry = $context->getEntity()->getInstance();
        if (!$inquiry instanceof Inquiry) {
            throw $this->createNotFoundException();
        }

        if (InquiryStatus::New === $inquiry->getStatus()) {
            $inquiry->setStatus(InquiryStatus::Done);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Заявка #%d закрыта.', $inquiry->getId()));
        }

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Имя');
        yield ChoiceField::new('contactType', 'Тип контакта')
            ->setChoices(array_combine(
                array_map(static fn (ContactType $t) => $t->label(), ContactType::cases()),
                ContactType::cases(),
            ));
        yield TextField::new('contact', 'Контакт');
        yield ChoiceField::new('inquiryType', 'Тип запроса')
            ->setChoices(array_combine(
                array_map(static fn (InquiryType $t) => $t->label(), InquiryType::allOrdered()),
                InquiryType::allOrdered(),
            ));
        yield TextareaField::new('message', 'Сообщение')->hideOnIndex();
        yield ChoiceField::new('status', 'Статус')
            ->setChoices(array_combine(
                array_map(static fn (InquiryStatus $s) => $s->label(), InquiryStatus::cases()),
                InquiryStatus::cases(),
            ));
        yield TextField::new('attachmentOriginalName', 'Файл')->onlyOnDetail();
        yield TextareaField::new('adminNote', 'Заметка')->hideOnIndex();
        yield DateTimeField::new('privacyConsentAt', 'Согласие на обработку ПДн')->hideOnForm()->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Создана')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Обновлена')->onlyOnDetail();
    }
}
