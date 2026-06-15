<?php

namespace App\Admin;

use App\Entity\CaseStudy;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CaseStudyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CaseStudy::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Кейс')
            ->setEntityLabelInPlural('Кейсы')
            ->setDefaultSort(['sortOrder' => 'ASC'])
            ->setPageTitle(Crud::PAGE_INDEX, 'Кейсы')
            ->setPageTitle(Crud::PAGE_NEW, 'Новый кейс')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать кейс')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Кейс');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('title', 'Название');
        yield TextField::new('slug', 'Slug');
        yield TextareaField::new('summary', 'Кратко')->hideOnIndex();
        yield TextareaField::new('content', 'Описание')->hideOnIndex();
        yield ImageField::new('coverImagePath', 'Обложка')
            ->setBasePath('uploads')
            ->setUploadDir('public/uploads/cases')
            ->setUploadedFileNamePattern('[uuid].[extension]');
        yield BooleanField::new('isPublished', 'Опубликован');
        yield IntegerField::new('sortOrder', 'Порядок');
        yield DateTimeField::new('createdAt', 'Создан')->hideOnForm();
    }
}
