<?php

namespace App\Admin;

use App\Entity\CaseStudy;
use App\Enum\CasePresentationMode;
use App\Form\Admin\CaseStudyImageType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
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
use Symfony\Component\Validator\Constraints\File;

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
            ->setPageTitle(Crud::PAGE_DETAIL, 'Кейс')
            ->setFormOptions(['validation_groups' => ['Default']]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield FormField::addFieldset('Витрина', 'fa fa-briefcase');
        yield TextField::new('title', 'Название кейса');
        yield SlugField::new('slug', 'Адрес страницы (slug)')
            ->setTargetFieldName('title')
            ->setHelp('Из названия. Можно поправить вручную. Пример: om-player');
        yield ImageField::new('coverImagePath', 'Обложка')
            ->setBasePath('uploads/cases')
            ->setUploadDir('public/uploads/cases')
            ->setUploadedFileNamePattern('[uuid].[extension]')
            ->setHelp('Кадр продукта или интерфейса — главный визуал');
        yield TextareaField::new('summary', 'Кратко для списка')
            ->hideOnIndex()
            ->setHelp('1–2 предложения для страницы «Кейсы». Не полный рассказ.');
        yield TextField::new('outcomeLine', 'Что изменилось')
            ->hideOnIndex()
            ->setHelp('Одна фраза результата для людей/бизнеса. Показывается на главной и в списке.');
        yield TextField::new('domain', 'Сфера')
            ->hideOnIndex()
            ->setHelp('Например: образование, e-commerce, личный бренд');
        yield TextField::new('role', 'Ваша роль')
            ->hideOnIndex()
            ->setHelp('Например: продукт, разработка, дизайн');
        yield IntegerField::new('year', 'Год')
            ->hideOnIndex()
            ->setHelp('Год проекта или запуска');

        yield FormField::addFieldset('Где показывать', 'fa fa-eye');
        yield BooleanField::new('isPublished', 'Опубликован');
        yield BooleanField::new('showOnLanding', 'На главной')
            ->setHelp('Показывать в секции кейсов на лендинге (лучше 2–4 штуки)');
        yield BooleanField::new('isFeatured', 'Крупный на главной')
            ->hideOnIndex()
            ->setHelp('Один выделенный кейс шире остальных');
        yield BooleanField::new('hasDetailPage', 'Отдельная страница истории')
            ->setHelp('Включайте, когда заполнены блоки истории ниже');
        yield IntegerField::new('sortOrder', 'Порядок');
        yield DateTimeField::new('createdAt', 'Создан')->hideOnForm();

        yield FormField::addFieldset('История', 'fa fa-book-open')
            ->setHelp('Три коротких блока. Без пафоса — как рассказали бы другу.');
        yield TextareaField::new('storyHook', 'Вступление')
            ->hideOnIndex()
            ->setNumOfRows(3)
            ->setHelp('1–3 предложения: зачем этот проект и с чего началось.');
        yield TextareaField::new('storyBody', 'История')
            ->hideOnIndex()
            ->setNumOfRows(10)
            ->setHelp('Основной рассказ: что делали и как шли.');
        yield TextareaField::new('storyOutcome', 'Итог')
            ->hideOnIndex()
            ->setNumOfRows(4)
            ->setHelp('Чем закончилось и что важно сейчас.');

        yield FormField::addFieldset('Презентация (видео / голос)', 'fa fa-play')
            ->setHelp('Опционально. Приоритет голоса: OmPlayer (slug) → файл → ссылка.');
        yield ChoiceField::new('presentationMode', 'Тип презентации')
            ->setChoices($this->presentationChoices())
            ->hideOnIndex();
        yield TextField::new('presentationIntro', 'Зачем слушать / смотреть')
            ->hideOnIndex()
            ->setHelp('Одна фраза над плеером. Например: «Рассказываю историю проекта вслух».');
        yield TextField::new('presentationDuration', 'Длительность')
            ->hideOnIndex()
            ->setHelp('Например: ~6 мин');
        yield UrlField::new('videoUrl', 'Ссылка на видео')
            ->hideOnIndex()
            ->setHelp('YouTube, Vimeo или Rutube');
        yield TextField::new('videoTitle', 'Название видео')
            ->hideOnIndex()
            ->setHelp('Для доступности (a11y)');
        yield TextField::new('omTrackSlug', 'Трек OmPlayer (slug)')
            ->hideOnIndex()
            ->setHelp('Загрузите трек на music.arturlun.ru и вставьте slug — встроится <om-player>.');
        yield FileField::new('audioPath', 'Аудиофайл')
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
            ])
            ->hideOnIndex()
            ->setHelp('Если нет трека в OmPlayer — загрузите mp3/m4a сюда.');
        yield UrlField::new('audioUrl', 'Ссылка на аудио')
            ->hideOnIndex()
            ->setHelp('Внешний URL, если файл не на сайте и не в OmPlayer');
        yield TextField::new('audioTitle', 'Название аудио')
            ->hideOnIndex();

        yield FormField::addFieldset('Галерея', 'fa fa-images');
        yield CollectionField::new('galleryImages', 'Кадры проекта')
            ->setEntryType(CaseStudyImageType::class)
            ->setEntryIsComplex(true)
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOption('by_reference', false)
            ->hideOnIndex()
            ->setHelp('Дополнительные кадры на странице кейса. Порядок — поле «Порядок» у кадра.');

        yield FormField::addFieldset('SEO', 'fa fa-search');
        yield TextField::new('seoTitle', 'Title для поиска')
            ->hideOnIndex()
            ->setHelp('Если пусто — «Название · Бренд»');
        yield TextareaField::new('seoDescription', 'Description для поиска')
            ->hideOnIndex()
            ->setHelp('До 160 символов. Если пусто — берётся «Что изменилось» или кратко.');
        yield ImageField::new('ogImagePath', 'Картинка для соцсетей (OG)')
            ->setBasePath('uploads/cases')
            ->setUploadDir('public/uploads/cases')
            ->setUploadedFileNamePattern('og-[uuid].[extension]')
            ->hideOnIndex()
            ->setHelp('Если пусто — обложка кейса');
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
