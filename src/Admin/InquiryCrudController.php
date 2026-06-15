<?php

namespace App\Admin;

use App\Entity\Inquiry;
use App\Enum\ContactType;
use App\Enum\InquiryStatus;
use App\Enum\InquiryType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class InquiryCrudController extends AbstractCrudController
{
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
            ->setPageTitle(Crud::PAGE_DETAIL, 'Заявка');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_DETAIL, Action::new('attachment', 'Скачать файл', 'fa fa-paperclip')
                ->linkToRoute('admin_inquiry_attachment', static fn (Inquiry $inquiry): array => ['id' => $inquiry->getId()])
                ->displayIf(static fn (Inquiry $inquiry): bool => $inquiry->hasAttachment()));
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
                array_map(static fn (InquiryType $t) => $t->label(), InquiryType::ordered()),
                InquiryType::ordered(),
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
