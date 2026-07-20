<?php

namespace App\Admin;

use App\Entity\ChronicleEra;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ChronicleEraCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ChronicleEra::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Эпоха')
            ->setEntityLabelInPlural('Эпохи')
            ->setDefaultSort(['sortOrder' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->reorder(Crud::PAGE_INDEX, [Action::EDIT, Action::DELETE]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('title', 'Название');
        yield SlugField::new('slug', 'Slug')->setTargetFieldName('title');
        yield TextField::new('periodLabel', 'Период')->setHelp('Например: 2010 — 2015');
        yield ColorField::new('color', 'Цвет акцента');
        yield TextareaField::new('description', 'Описание')->hideOnIndex();
        yield IntegerField::new('sortOrder', 'Порядок');
    }
}
