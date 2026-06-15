<?php

namespace App\Admin;

use App\Entity\PaymentOffer;
use App\Enum\PaymentOfferStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class PaymentOfferCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PaymentOffer::class;
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
            ->setPageTitle(Crud::PAGE_DETAIL, 'Оплата');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('token', 'Токен')->hideOnForm();
        yield AssociationField::new('inquiry', 'Заявка');
        yield TextField::new('title', 'Название');
        yield IntegerField::new('amount', 'Сумма (коп.)')
            ->setHelp('Например, 500000 = 5 000 ₽');
        yield UrlField::new('sberPaymentUrl', 'Ссылка СБП');
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
}
