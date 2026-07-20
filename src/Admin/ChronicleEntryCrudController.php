<?php

namespace App\Admin;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ChronicleEntryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ChronicleEntry::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Запись')
            ->setEntityLabelInPlural('Хроника')
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->setPageTitle(Crud::PAGE_INDEX, 'Хроника')
            ->setPageTitle(Crud::PAGE_NEW, 'Новая запись');
    }

    public function configureActions(Actions $actions): Actions
    {
        $editor = Action::new('editor', 'Редактор', 'fa fa-pen')
            ->linkToRoute('admin_chronicle_editor', static fn (ChronicleEntry $entry): array => ['id' => $entry->getId()]);

        $newEntry = Action::new('newEntry', 'Новая запись', 'fa fa-pen')
            ->linkToRoute('admin_chronicle_editor_new')
            ->createAsGlobalAction();

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $editor)
            ->add(Crud::PAGE_INDEX, $newEntry);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('title', 'Заголовок')
            ->setTemplatePath('admin/chronicle/field_title_link.html.twig');
        yield TextField::new('shortHash', 'Hash')->hideOnForm();
        yield ChoiceField::new('status', 'Статус')
            ->setChoices(array_combine(
                array_map(static fn (ChronicleStatus $s) => $s->label(), ChronicleStatus::cases()),
                ChronicleStatus::cases(),
            ));
        yield AssociationField::new('era', 'Эпоха');
        yield DateTimeField::new('publishedAt', 'Опубликован')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Изменён')->hideOnForm();
        yield BooleanField::new('isFeatured', 'Featured')->hideOnIndex();
        yield BooleanField::new('isUnlisted', 'Unlisted')->hideOnIndex();
    }
}
