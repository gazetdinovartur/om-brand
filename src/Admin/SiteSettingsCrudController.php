<?php

namespace App\Admin;

use App\Entity\SiteSettings;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class SiteSettingsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SiteSettings::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Настройки')
            ->setEntityLabelInPlural('Настройки')
            ->setPageTitle(Crud::PAGE_INDEX, 'Настройки сайта')
            ->setPageTitle(Crud::PAGE_EDIT, 'Настройки сайта')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Настройки сайта');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Имя')
            ->setHelp('Шапка, футер и вкладка браузера. На главной приоритет у блока hero в «Контенте».');
        yield TextField::new('tagline', 'Краткое описание')
            ->setHelp('Meta description для поисковиков и превью ссылки в соцсетях. На главном экране показывается текст из блока hero.');
        yield TextField::new('city', 'Подпись в шапке')
            ->setHelp('Строка под именем в шапке сайта.');
        yield ImageField::new('avatarPath', 'Аватар')
            ->setBasePath('uploads/avatars')
            ->setUploadDir('public/uploads/avatars')
            ->setUploadedFileNamePattern('[uuid].[extension]');
        yield UrlField::new('telegramUrl', 'Telegram')->hideOnIndex();
        yield UrlField::new('githubUrl', 'GitHub')->hideOnIndex();
        yield EmailField::new('email', 'Email для политики')
            ->hideOnIndex()
            ->setHelp('Указывается в политике конфиденциальности для обращений по персональным данным.');
        yield EmailField::new('notificationEmail', 'Email для заявок')
            ->hideOnIndex()
            ->setHelp('Куда приходят уведомления (Gmail, Яндекс и т.д.). Отправитель задаётся в MAILER_FROM на сервере, не здесь.');
        yield TextareaField::new('formSuccessMessage', 'Сообщение после заявки')->hideOnIndex();
        yield TextField::new('sbpPaymentUrlTemplate', 'Шаблон ссылки СБП')
            ->hideOnIndex()
            ->setHelp('Подставляется при сохранении оплаты. Плейсхолдеры: {amount_rubles}, {amount}, {token}, {title}, {id}. Пример: https://qr.nspk.ru/...?sum={amount_rubles}');
    }
}
