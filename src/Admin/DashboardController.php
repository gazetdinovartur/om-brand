<?php

namespace App\Admin;

use App\Entity\AdminUser;
use App\Entity\CaseStudy;
use App\Entity\ChronicleEntry;
use App\Entity\ChronicleEra;
use App\Entity\ChronicleSeries;
use App\Entity\ChronicleTag;
use App\Entity\ContentBlock;
use App\Entity\Inquiry;
use App\Entity\PaymentOffer;
use App\Entity\SiteSettings;
use App\Enum\ChronicleStatus;
use App\Enum\InquiryStatus;
use App\Repository\ChronicleEntryRepository;
use App\Repository\InquiryRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly InquiryRepository $inquiries,
        private readonly ChronicleEntryRepository $chronicles,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'newInquiries' => $this->inquiries->count(['status' => InquiryStatus::New]),
            'draftEntries' => $this->chronicles->count(['status' => ChronicleStatus::Draft]),
            'publishedEntries' => $this->chronicles->count(['status' => ChronicleStatus::Published]),
        ]);
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
        yield MenuItem::section('Хроника');
        yield MenuItem::linkTo(ChronicleEntryCrudController::class, 'Записи', 'fa fa-book');
        yield MenuItem::linkTo(ChronicleEraCrudController::class, 'Эпохи', 'fa fa-layer-group');
        yield MenuItem::linkTo(ChronicleTagCrudController::class, 'Ключевые слова', 'fa fa-tags');
        yield MenuItem::linkTo(ChronicleSeriesCrudController::class, 'Серии', 'fa fa-list-ol');
        yield MenuItem::section('Заявки');
        yield MenuItem::linkTo(InquiryCrudController::class, 'Заявки', 'fa fa-inbox');
        yield MenuItem::linkTo(PaymentOfferCrudController::class, 'Оплаты', 'fa fa-credit-card');
        yield MenuItem::section('Система');
        yield MenuItem::linkTo(AdminUserCrudController::class, 'Админы', 'fa fa-shield');
    }
}
