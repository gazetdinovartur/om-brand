<?php

namespace App\Admin;

use App\Entity\ChronicleSeries;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ChronicleSeriesCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ChronicleSeries::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Серия')
            ->setEntityLabelInPlural('Серии')
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
        yield TextareaField::new('description', 'Описание')->hideOnIndex();
        yield IntegerField::new('sortOrder', 'Порядок');
    }
}
