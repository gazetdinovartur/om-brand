<?php

namespace App\Admin;

use App\Entity\ContentBlock;
use App\Enum\ContentBlockType;
use App\Form\Admin\ContentBlockItemType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ContentBlockCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ContentBlock::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Блок контента')
            ->setEntityLabelInPlural('Контент')
            ->setDefaultSort(['sortOrder' => 'ASC'])
            ->setPageTitle(Crud::PAGE_INDEX, 'Контент сайта')
            ->setPageTitle(Crud::PAGE_NEW, 'Новый блок')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать блок')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Блок контента');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('slug', 'Ключ')
            ->setHelp('hero, pains, services, work_formats…');
        yield ChoiceField::new('type', 'Тип блока')
            ->setChoices(array_combine(
                array_map(static fn (ContentBlockType $t) => $t->label(), ContentBlockType::cases()),
                ContentBlockType::cases(),
            ));
        yield TextField::new('title', 'Заголовок');
        yield TextField::new('subtitle', 'Подзаголовок')->hideOnIndex();
        yield TextareaField::new('body', 'Основной текст')
            ->hideOnIndex()
            ->setHelp('Для типов «Текст», «Hero», «Форма», «Футер»');

        if ($this->shouldShowItemsField($pageName)) {
            yield CollectionField::new('items', $this->getItemsFieldLabel())
                ->setEntryType(ContentBlockItemType::class)
                ->setEntryIsComplex(true)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->hideOnIndex()
                ->setHelp($this->getItemsFieldHelp());
        }

        yield IntegerField::new('sortOrder', 'Порядок');
        yield BooleanField::new('isVisible', 'Показывать на сайте');
    }

    private function shouldShowItemsField(string $pageName): bool
    {
        if (Crud::PAGE_INDEX === $pageName || Crud::PAGE_DETAIL === $pageName) {
            return false;
        }

        $entity = $this->getContext()?->getEntity()?->getInstance();
        if (!$entity instanceof ContentBlock || null === $entity->getId()) {
            return true;
        }

        return $this->typeUsesItems($entity->getType());
    }

    private function typeUsesItems(ContentBlockType $type): bool
    {
        return match ($type) {
            ContentBlockType::List,
            ContentBlockType::Cards,
            ContentBlockType::Steps,
            ContentBlockType::Footer => true,
            default => false,
        };
    }

    private function getItemsFieldLabel(): string
    {
        $entity = $this->getContext()?->getEntity()?->getInstance();
        if (!$entity instanceof ContentBlock) {
            return 'Элементы';
        }

        return match ($entity->getType()) {
            ContentBlockType::List => 'Пункты списка',
            ContentBlockType::Cards => 'Карточки',
            ContentBlockType::Steps => 'Шаги',
            ContentBlockType::Footer => 'Пункты в футере',
            default => 'Элементы',
        };
    }

    private function getItemsFieldHelp(): string
    {
        $entity = $this->getContext()?->getEntity()?->getInstance();
        if (!$entity instanceof ContentBlock) {
            return 'Добавляйте элементы кнопкой ниже.';
        }

        return match ($entity->getType()) {
            ContentBlockType::List => 'Для списка достаточно текста — заголовок можно не заполнять.',
            ContentBlockType::Cards, ContentBlockType::Steps => 'pricing' === $entity->getSlug()
                ? 'У каждого элемента — заголовок и текст. Поле «Стоимость» — только если нужно показать ставку (например, от 1500 ₽/ч).'
                : 'У каждого элемента есть заголовок и текст.',
            ContentBlockType::Footer => 'Короткие пункты «что не предлагаю» — только текст.',
            default => '',
        };
    }
}
