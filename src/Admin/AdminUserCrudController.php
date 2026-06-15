<?php

namespace App\Admin;

use App\Entity\AdminUser;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AdminUserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AdminUser::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Админ')
            ->setEntityLabelInPlural('Админы')
            ->setPageTitle(Crud::PAGE_INDEX, 'Администраторы')
            ->setPageTitle(Crud::PAGE_NEW, 'Новый администратор')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать администратора')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Администратор');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email', 'Email');
        yield TextField::new('password', 'Пароль')
            ->onlyOnForms()
            ->onlyWhenCreating();
    }
}
