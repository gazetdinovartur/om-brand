<?php

namespace App\Admin;

use App\Entity\AdminUser;
use App\Entity\CaseStudy;
use App\Entity\ContentBlock;
use App\Entity\Inquiry;
use App\Entity\PaymentOffer;
use App\Entity\SiteSettings;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Артур · админка');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('css/admin-custom.css')
            ->addJsFile('js/admin-content-block.js');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Главная', 'fa fa-home');
        yield MenuItem::section('Сайт');
        yield MenuItem::linkTo(SiteSettingsCrudController::class, 'Настройки', 'fa fa-sliders');
        yield MenuItem::linkTo(ContentBlockCrudController::class, 'Контент', 'fa fa-file-lines');
        yield MenuItem::linkTo(CaseStudyCrudController::class, 'Кейсы', 'fa fa-briefcase');
        yield MenuItem::section('Заявки');
        yield MenuItem::linkTo(InquiryCrudController::class, 'Заявки', 'fa fa-inbox');
        yield MenuItem::linkTo(PaymentOfferCrudController::class, 'Оплаты', 'fa fa-credit-card');
        yield MenuItem::section('Система');
        yield MenuItem::linkTo(AdminUserCrudController::class, 'Админы', 'fa fa-shield');
    }
}
