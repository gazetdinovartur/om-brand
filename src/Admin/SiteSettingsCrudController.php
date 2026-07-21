<?php

namespace App\Admin;

use App\Entity\SiteSettings;
use App\Repository\SiteSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SiteSettingsCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly SiteSettingsRepository $settingsRepository,
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SiteSettings::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Настройки')
            ->setEntityLabelInPlural('Настройки')
            ->setPageTitle(Crud::PAGE_EDIT, 'Настройки сайта')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Настройки сайта');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::DETAIL)
            ->update(
                Crud::PAGE_EDIT,
                Action::SAVE_AND_RETURN,
                static fn (Action $action): Action => $action->setLabel('Сохранить'),
            )
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE);
    }

    public function index(AdminContext $context): RedirectResponse
    {
        return $this->redirectToSettingsEdit();
    }

    public function new(AdminContext $context): RedirectResponse
    {
        return $this->redirectToSettingsEdit();
    }

    private function redirectToSettingsEdit(): RedirectResponse
    {
        $settings = $this->settingsRepository->getSettings();
        if (null === $settings->getId()) {
            $this->em->persist($settings);
            $this->em->flush();
        }

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::EDIT)
            ->setEntityId($settings->getId())
            ->generateUrl();

        return $this->redirect($url);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Имя')
            ->setHelp('Шапка и футер. На главной имя берётся из настроек / hero в коде.');
        yield TextField::new('tagline', 'Краткое описание')
            ->setHelp('Meta description для поисковиков и превью в соцсетях.');
        yield TextField::new('city', 'Подпись в шапке')
            ->setHelp('Строка под именем — только на лендинге /dev--null.');
        yield ImageField::new('avatarPath', 'Аватар')
            ->setBasePath('uploads/avatars')
            ->setUploadDir('public/uploads/avatars')
            ->setUploadedFileNamePattern('[uuid].[extension]');
        yield UrlField::new('telegramUrl', 'Telegram')->hideOnIndex();
        yield UrlField::new('githubUrl', 'GitHub')->hideOnIndex();
        yield EmailField::new('email', 'Email для политики')
            ->hideOnIndex()
            ->setHelp('В политике конфиденциальности для обращений по ПДн.');
        yield EmailField::new('notificationEmail', 'Email для заявок')
            ->hideOnIndex()
            ->setHelp('Куда приходят уведомления. Отправитель — MAILER_FROM на сервере.');
        yield TextareaField::new('formSuccessMessage', 'Сообщение после заявки')->hideOnIndex();
        yield TextField::new('sbpPaymentUrlTemplate', 'Шаблон ссылки СБП')
            ->hideOnIndex()
            ->setHelp('Плейсхолдеры: {amount_rubles}, {amount}, {token}, {title}, {id}.');
    }
}
