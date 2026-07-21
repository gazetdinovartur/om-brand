<?php

namespace App\Admin;

use App\Entity\CaseStudy;
use App\Enum\CasePresentationMode;
use App\Form\Admin\CaseStudyImageType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FileField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Constraints\File;

class CaseStudyCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return CaseStudy::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addHtmlContentToBody(sprintf(
                '<meta name="case-reorder-csrf" content="%s"><meta name="case-reorder-url" content="%s">',
                htmlspecialchars($this->csrfTokenManager->getToken('case_reorder')->getValue(), ENT_QUOTES),
                htmlspecialchars($this->generateUrl('admin_case_reorder'), ENT_QUOTES),
            ))
            ->addCssFile('css/admin-cases.css')
            ->addJsFile('https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js')
            ->addJsFile('js/admin-cases.js');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Кейс')
            ->setEntityLabelInPlural('Кейсы')
            ->setDefaultSort(['sortOrder' => 'ASC', 'id' => 'DESC'])
            ->setSearchFields(['title', 'slug', 'domain', 'outcomeLine'])
            ->setPaginatorPageSize(50)
            ->setPageTitle(Crud::PAGE_INDEX, 'Кейсы')
            ->setPageTitle(Crud::PAGE_NEW, 'Новый кейс')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать кейс')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Кейс')
            ->setFormOptions(['validation_groups' => ['Default']])
            ->showEntityActionsInlined();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isPublished', 'Опубликован'))
            ->add(BooleanFilter::new('showOnLanding', 'На лендинге'))
            ->add(BooleanFilter::new('hasDetailPage', 'Есть страница'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::BATCH_DELETE)
            ->reorder(Crud::PAGE_INDEX, [Action::EDIT, Action::DELETE])
            ->update(
                Crud::PAGE_INDEX,
                Action::EDIT,
                static fn (Action $action): Action => $action
                    ->setLabel(false)
                    ->setIcon('fa fa-pen')
                    ->setHtmlAttributes(['title' => 'Редактировать', 'aria-label' => 'Редактировать']),
            )
            ->update(
                Crud::PAGE_INDEX,
                Action::DELETE,
                static fn (Action $action): Action => $action
                    ->setLabel(false)
                    ->setIcon('fa fa-trash-alt')
                    ->setHtmlAttributes(['title' => 'Удалить', 'aria-label' => 'Удалить']),
            );
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id', ' ')
                ->setTemplatePath('admin/case/field_drag.html.twig')
                ->setSortable(false);
            yield ImageField::new('coverImagePath', ' ')
                ->setBasePath('uploads/cases')
                ->setSortable(false);
            yield TextField::new('title', 'Кейс')
                ->setTemplatePath('admin/case/field_title.html.twig')
                ->setSortable(true);

            return;
        }

        if (Crud::PAGE_DETAIL === $pageName) {
            yield IdField::new('id');
            yield ImageField::new('coverImagePath', 'Обложка')->setBasePath('uploads/cases');
            yield TextField::new('title', 'Название');
            yield TextField::new('slug', 'Slug');
            yield TextareaField::new('summary', 'Кратко');
            yield TextareaField::new('outcomeLine', 'Что изменилось');
            yield TextField::new('domain', 'Сфера');
            yield TextField::new('role', 'Роль');
            yield IntegerField::new('year', 'Год');
            yield BooleanField::new('isPublished', 'Опубликован');
            yield BooleanField::new('showOnLanding', 'На лендинге');
            yield BooleanField::new('isFeatured', 'Крупный');
            yield BooleanField::new('hasDetailPage', 'Страница истории');
            yield IntegerField::new('sortOrder', 'Порядок');
            yield DateTimeField::new('createdAt', 'Создан');

            return;
        }

        yield FormField::addTab('Основное', 'fa fa-briefcase');
        yield FormField::addFieldset('Карточка');
        yield TextField::new('title', 'Название');
        yield SlugField::new('slug', 'Slug')
            ->setTargetFieldName('title')
            ->setHelp('Адрес страницы. Пример: om-player');
        yield ImageField::new('coverImagePath', 'Обложка')
            ->setBasePath('uploads/cases')
            ->setUploadDir('public/uploads/cases')
            ->setUploadedFileNamePattern('[uuid].[extension]')
            ->setHelp('Главный кадр. OG для соцсетей берётся из обложки автоматически.');
        yield TextareaField::new('summary', 'Кратко для списка')
            ->setColumns(12)
            ->setNumOfRows(2)
            ->addCssClass('case-admin-grow')
            ->setHelp('1–2 предложения');
        yield TextareaField::new('outcomeLine', 'Что изменилось')
            ->setColumns(12)
            ->setNumOfRows(2)
            ->addCssClass('case-admin-grow')
            ->setHelp('Одна фраза результата');

        yield FormField::addFieldset('Мета');
        yield TextField::new('domain', 'Сфера')
            ->setColumns(4)
            ->setHelp('образование · e-commerce');
        yield TextField::new('role', 'Роль')
            ->setColumns(4)
            ->setHelp('продукт, разработка');
        yield IntegerField::new('year', 'Год')
            ->setColumns(4);

        yield FormField::addFieldset('Публикация');
        yield BooleanField::new('isPublished', 'Опубликован')
            ->setColumns(3);
        yield BooleanField::new('showOnLanding', 'На лендинге')
            ->setColumns(3)
            ->setHelp('Секция кейсов на /dev--null');
        yield BooleanField::new('isFeatured', 'Крупный')
            ->setColumns(3)
            ->setHelp('Выделить на лендинге');
        yield BooleanField::new('hasDetailPage', 'Страница истории')
            ->setColumns(3)
            ->setHelp('Отдельный URL /cases/…');

        yield FormField::addTab('История', 'fa fa-book-open');
        yield FormField::addFieldset('Рассказ')
            ->setHelp('Три коротких блока — как другу, без пафоса.');
        yield TextareaField::new('storyHook', 'Вступление')
            ->setColumns(12)
            ->setNumOfRows(3)
            ->addCssClass('case-admin-grow')
            ->setHelp('Зачем проект и с чего началось');
        yield TextareaField::new('storyBody', 'История')
            ->setColumns(12)
            ->setNumOfRows(6)
            ->addCssClass('case-admin-grow')
            ->setHelp('Что делали и как шли');
        yield TextareaField::new('storyOutcome', 'Итог')
            ->setColumns(12)
            ->setNumOfRows(3)
            ->addCssClass('case-admin-grow')
            ->setHelp('Чем закончилось');

        yield FormField::addTab('Медиа', 'fa fa-play');
        yield FormField::addFieldset('Презентация')
            ->setHelp('Сначала тип — затем появятся поля видео и/или аудио.');
        yield ChoiceField::new('presentationMode', 'Тип')
            ->setColumns(12)
            ->setChoices($this->presentationChoices())
            ->renderAsNativeWidget()
            ->setFormTypeOption('choice_value', static fn (?CasePresentationMode $mode): string => $mode?->value ?? '')
            ->setFormTypeOption('attr', [
                'data-case-presentation-mode' => '1',
                'class' => 'form-select case-admin-presentation-mode',
            ]);
        yield TextField::new('presentationIntro', 'Подпись над плеером')
            ->setColumns(12)
            ->addCssClass('case-admin-grow');

        yield FormField::addFieldset('Видео')
            ->setHtmlAttribute('data-case-media', 'video')
            ->addCssClass('js-case-media-block js-case-media-video');
        yield UrlField::new('videoUrl', 'Ссылка')
            ->setColumns(12)
            ->addCssClass('js-case-media-video')
            ->setHelp('YouTube, Vimeo, Rutube');
        yield TextField::new('videoTitle', 'Название')
            ->setColumns(12)
            ->addCssClass('js-case-media-video');

        yield FormField::addFieldset('Аудио')
            ->setHtmlAttribute('data-case-media', 'audio')
            ->addCssClass('js-case-media-block js-case-media-audio');
        yield TextField::new('omTrackSlug', 'Slug OmPlayer')
            ->setColumns(12)
            ->addCssClass('js-case-media-audio')
            ->setHelp('Трек с music.arturlun.ru');
        yield FileField::new('audioPath', 'Файл')
            ->setColumns(12)
            ->addCssClass('js-case-media-audio')
            ->setBasePath('uploads/cases/audio')
            ->setUploadDir('public/uploads/cases/audio')
            ->setUploadedFileNamePattern('[uuid].[extension]')
            ->mimeTypes('audio/*')
            ->maxSize('30M')
            ->setFileConstraints([
                new File(maxSize: '30M', mimeTypes: [
                    'audio/mpeg',
                    'audio/mp3',
                    'audio/mp4',
                    'audio/x-m4a',
                    'audio/wav',
                    'audio/ogg',
                    'audio/webm',
                ]),
            ]);
        yield UrlField::new('audioUrl', 'Внешняя ссылка')
            ->setColumns(12)
            ->addCssClass('js-case-media-audio');
        yield TextField::new('audioTitle', 'Название аудио')
            ->setColumns(12)
            ->addCssClass('js-case-media-audio');

        yield FormField::addFieldset('Галерея')
            ->addCssClass('js-case-gallery')
            ->setHelp('Добавьте кадры и при необходимости подпишите. Порядок — перетаскиванием за ⋮⋮.');
        yield CollectionField::new('galleryImages', false)
            ->setEntryType(CaseStudyImageType::class)
            ->setEntryIsComplex(true)
            ->renderExpanded()
            ->allowAdd()
            ->allowDelete()
            ->setColumns(12)
            ->setFormTypeOption('by_reference', false)
            ->setFormTypeOption('entry_options', [
                'label' => false,
            ])
            ->addCssClass('case-gallery-collection');

        yield FormField::addTab('SEO', 'fa fa-search');
        yield FormField::addFieldset('Поиск')
            ->setHelp('Картинка для соцсетей берётся из обложки автоматически.');
        yield TextField::new('seoTitle', 'Title')
            ->setColumns(12)
            ->setHelp('Пусто → «Название · Бренд»');
        yield TextareaField::new('seoDescription', 'Description')
            ->setColumns(12)
            ->setNumOfRows(2)
            ->addCssClass('case-admin-grow')
            ->setHelp('До ~160 символов. Пусто → итог или кратко');
    }

    /**
     * @return array<string, CasePresentationMode>
     */
    private function presentationChoices(): array
    {
        $choices = [];
        foreach (CasePresentationMode::cases() as $mode) {
            $choices[$mode->label()] = $mode;
        }

        return $choices;
    }
}
