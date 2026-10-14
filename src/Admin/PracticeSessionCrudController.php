<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\PracticeSession;
use App\Enum\PracticeSessionKind;
use App\Enum\PracticeSessionStatus;
use App\Service\PracticeKinescopeService;
use App\Service\PracticeSessionService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PracticeSessionCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly PracticeSessionService $practiceSessions,
        private readonly PracticeKinescopeService $kinescopeSessions,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PracticeSession::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Сессия практики')
            ->setEntityLabelInPlural('Сессии практики')
            ->setDefaultSort(['startsAt' => 'DESC'])
            ->setPageTitle(Crud::PAGE_INDEX, 'Сессии практики')
            ->setPageTitle(Crud::PAGE_NEW, 'Новая сессия')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать сессию')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Сессия практики')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $createRemote = Action::new('createRemote', 'Создать в Kinescope', 'fa fa-cloud-upload-alt')
            ->linkToCrudAction('createRemote')
            ->displayIf(static fn (PracticeSession $session): bool => null === $session->getKinescopeEventId()
                && null === $session->getKinescopeRoomId());

        $goLive = Action::new('goLive', 'В эфир', 'fa fa-broadcast-tower')
            ->linkToCrudAction('goLive')
            ->displayIf(static fn (PracticeSession $session): bool => PracticeSessionStatus::Scheduled === $session->getStatus());

        $finish = Action::new('finish', 'Эфир завершён', 'fa fa-check')
            ->linkToCrudAction('finishSession')
            ->displayIf(static fn (PracticeSession $session): bool => \in_array(
                $session->getStatus(),
                [PracticeSessionStatus::Scheduled, PracticeSessionStatus::Live],
                true,
            ));

        $sync = Action::new('sync', 'Синхронизировать', 'fa fa-sync')
            ->linkToCrudAction('syncSession')
            ->displayIf(static fn (PracticeSession $session): bool => null !== $session->getKinescopeEventId()
                || null !== $session->getKinescopeVideoId()
                || null !== $session->getKinescopeRoomId());

        $archive = Action::new('archive', 'В архив', 'fa fa-archive')
            ->linkToCrudAction('archiveSession')
            ->displayIf(static fn (PracticeSession $session): bool => PracticeSessionStatus::Replay === $session->getStatus());

        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action) => $action->setLabel('Создать сессию'))
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $createRemote)
            ->add(Crud::PAGE_DETAIL, $createRemote)
            ->add(Crud::PAGE_INDEX, $goLive)
            ->add(Crud::PAGE_DETAIL, $goLive)
            ->add(Crud::PAGE_INDEX, $finish)
            ->add(Crud::PAGE_DETAIL, $finish)
            ->add(Crud::PAGE_INDEX, $sync)
            ->add(Crud::PAGE_DETAIL, $sync)
            ->add(Crud::PAGE_INDEX, $archive)
            ->add(Crud::PAGE_DETAIL, $archive);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('title', 'Название');
        yield DateTimeField::new('startsAt', 'Начало')->setFormat('dd.MM.yyyy HH:mm');
        yield ChoiceField::new('kind', 'Тип')
            ->setChoices([
                PracticeSessionKind::Live->label() => PracticeSessionKind::Live,
                PracticeSessionKind::Speak->label() => PracticeSessionKind::Speak,
            ]);
        yield ChoiceField::new('status', 'Статус')
            ->setChoices([
                PracticeSessionStatus::Scheduled->label() => PracticeSessionStatus::Scheduled,
                PracticeSessionStatus::Live->label() => PracticeSessionStatus::Live,
                PracticeSessionStatus::Replay->label() => PracticeSessionStatus::Replay,
                PracticeSessionStatus::Completed->label() => PracticeSessionStatus::Completed,
            ]);
        yield TextField::new('kinescopeEventId', 'Event id')->hideOnIndex()
            ->setHelp('Заполняется кнопкой «Создать в Kinescope» или вручную');
        yield TextField::new('kinescopeVideoId', 'Video id')->hideOnIndex();
        yield TextField::new('kinescopeRoomId', 'Speak room id')->hideOnIndex();
        yield TextField::new('kinescopeRoomCode', 'Speak code')->hideOnIndex();
        yield TextField::new('kinescopeIngestUrl', 'RTMP URL')->onlyOnDetail();
        yield TextField::new('kinescopeStreamKey', 'Stream key')->onlyOnDetail()
            ->setHelp('Только для OBS, не светить участникам');
        yield TextField::new('kinescopeChatEmbedUrl', 'URL чата')->hideOnIndex();
        yield TextField::new('posterUrl', 'Постер')->hideOnIndex();
        yield IntegerField::new('durationSeconds', 'Длительность, сек')->hideOnIndex();
        yield TextareaField::new('notes', 'Заметки')->hideOnIndex();
        yield DateTimeField::new('recordingReadyAt', 'Запись готова')->setFormat('dd.MM.yyyy HH:mm')->onlyOnDetail();
        yield DateTimeField::new('debitedAt', 'Списано')->setFormat('dd.MM.yyyy HH:mm')->onlyOnDetail();
        yield DateTimeField::new('createdAt', 'Создано')->setFormat('dd.MM.yyyy HH:mm')->onlyOnDetail();
    }

    #[AdminRoute(path: '/{entityId}/create-remote', name: 'create_remote')]
    public function createRemote(AdminContext $context): RedirectResponse
    {
        $session = $context->getEntity()->getInstance();
        if (!$session instanceof PracticeSession) {
            throw $this->createNotFoundException();
        }

        try {
            $this->kinescopeSessions->createRemoteForSession($session);
            $this->addFlash('success', sprintf('«%s» создана в Kinescope.', $session->getTitle()));
        } catch (\Throwable $exception) {
            $this->addFlash('danger', 'Kinescope: '.$exception->getMessage());
        }

        return $this->redirectToDetail($session);
    }

    #[AdminRoute(path: '/{entityId}/go-live', name: 'go_live')]
    public function goLive(AdminContext $context): RedirectResponse
    {
        $session = $context->getEntity()->getInstance();
        if (!$session instanceof PracticeSession) {
            throw $this->createNotFoundException();
        }

        $this->kinescopeSessions->goLive($session);
        $this->addFlash('success', sprintf('«%s» в статусе эфир.', $session->getTitle()));

        return $this->redirectToIndex();
    }

    #[AdminRoute(path: '/{entityId}/finish', name: 'finish')]
    public function finishSession(AdminContext $context): RedirectResponse
    {
        $session = $context->getEntity()->getInstance();
        if (!$session instanceof PracticeSession) {
            throw $this->createNotFoundException();
        }

        try {
            $result = PracticeSessionKind::Live === $session->getKind()
                ? $this->kinescopeSessions->finishLiveViaApi($session)
                : $this->practiceSessions->finishWithRecording($session, $session->getKinescopeVideoId());
        } catch (\Throwable $exception) {
            $this->addFlash('danger', 'Не удалось завершить: '.$exception->getMessage());

            return $this->redirectToIndex();
        }

        $this->addFlash(
            'success',
            $result['debited']
                ? sprintf('Сессия «%s»: запись открыта, встречи списаны.', $session->getTitle())
                : sprintf('Сессия «%s»: статус обновлён.', $session->getTitle()),
        );

        return $this->redirectToIndex();
    }

    #[AdminRoute(path: '/{entityId}/sync', name: 'sync')]
    public function syncSession(AdminContext $context): RedirectResponse
    {
        $session = $context->getEntity()->getInstance();
        if (!$session instanceof PracticeSession) {
            throw $this->createNotFoundException();
        }

        try {
            $this->kinescopeSessions->syncSession($session);
            $this->addFlash('success', 'Синхронизация с Kinescope выполнена.');
        } catch (\Throwable $exception) {
            $this->addFlash('danger', 'Синхронизация: '.$exception->getMessage());
        }

        return $this->redirectToDetail($session);
    }

    #[AdminRoute(path: '/{entityId}/archive', name: 'archive')]
    public function archiveSession(AdminContext $context): RedirectResponse
    {
        $session = $context->getEntity()->getInstance();
        if (!$session instanceof PracticeSession) {
            throw $this->createNotFoundException();
        }

        $this->practiceSessions->archive($session);
        $this->addFlash('success', sprintf('Сессия «%s» в архиве.', $session->getTitle()));

        return $this->redirectToIndex();
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof PracticeSession) {
            $entityInstance->touch();
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function redirectToIndex(): RedirectResponse
    {
        return $this->redirect(
            $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }

    private function redirectToDetail(PracticeSession $session): RedirectResponse
    {
        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($session->getId())
                ->generateUrl(),
        );
    }
}
