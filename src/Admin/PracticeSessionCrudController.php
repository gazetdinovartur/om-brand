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
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PracticeSessionCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly PracticeSessionService $practiceSessions,
        private readonly PracticeKinescopeService $kinescopeSessions,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
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
            ->setHelp(
                Crud::PAGE_NEW,
                'Название + время + тип Live/Speak → сохранить. Откроется карточка с RTMP (Live) или кодом Speak и кнопкой «В эфир».',
            )
            ->setHelp(
                Crud::PAGE_DETAIL,
                'Live: OBS (RTMP + key) → «В эфир» → Start Streaming. Участники — «Комната на сайте».',
            )
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $goLive = Action::new('goLive', 'В эфир', 'fa fa-broadcast-tower')
            ->linkToCrudAction('goLive')
            ->displayIf(static fn (PracticeSession $session): bool => PracticeSessionStatus::Scheduled === $session->getStatus()
                && $session->hasKinescopeRemote());

        $finish = Action::new('finish', 'Эфир завершён', 'fa fa-check')
            ->linkToCrudAction('finishSession')
            ->displayIf(static fn (PracticeSession $session): bool => \in_array(
                $session->getStatus(),
                [PracticeSessionStatus::Scheduled, PracticeSessionStatus::Live],
                true,
            ));

        $sync = Action::new('sync', 'Обновить RTMP', 'fa fa-sync')
            ->linkToCrudAction('syncSession')
            ->displayIf(static fn (PracticeSession $session): bool => null !== $session->getKinescopeEventId());

        $archive = Action::new('archive', 'В архив', 'fa fa-archive')
            ->linkToCrudAction('archiveSession')
            ->displayIf(static fn (PracticeSession $session): bool => PracticeSessionStatus::Replay === $session->getStatus());

        $retryRemote = Action::new('createRemote', 'Создать в Kinescope', 'fa fa-cloud-upload-alt')
            ->linkToCrudAction('createRemote')
            ->displayIf(static fn (PracticeSession $session): bool => !$session->hasKinescopeRemote());

        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action) => $action->setLabel('Создать сессию'))
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $goLive)
            ->add(Crud::PAGE_DETAIL, $goLive)
            ->add(Crud::PAGE_INDEX, $finish)
            ->add(Crud::PAGE_DETAIL, $finish)
            ->add(Crud::PAGE_DETAIL, $sync)
            ->add(Crud::PAGE_INDEX, $archive)
            ->add(Crud::PAGE_DETAIL, $archive)
            ->add(Crud::PAGE_INDEX, $retryRemote)
            ->add(Crud::PAGE_DETAIL, $retryRemote)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'goLive', 'createRemote', 'finish', 'archive', Action::EDIT, Action::DELETE])
            ->reorder(Crud::PAGE_DETAIL, ['goLive', 'createRemote', 'sync', 'finish', 'archive', Action::EDIT, Action::DELETE]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex()->setLabel('#');
        yield TextField::new('title', 'Название');
        yield DateTimeField::new('startsAt', 'Начало')->setFormat('dd.MM.yyyy HH:mm');
        yield ChoiceField::new('kind', 'Тип')
            ->setChoices([
                PracticeSessionKind::Live->label() => PracticeSessionKind::Live,
                PracticeSessionKind::Speak->label() => PracticeSessionKind::Speak,
            ])
            ->setHelp('Live = OBS; Speak = общая видеовстреча');

        if (Crud::PAGE_NEW !== $pageName) {
            yield ChoiceField::new('status', 'Статус')
                ->setChoices([
                    PracticeSessionStatus::Scheduled->label() => PracticeSessionStatus::Scheduled,
                    PracticeSessionStatus::Live->label() => PracticeSessionStatus::Live,
                    PracticeSessionStatus::Replay->label() => PracticeSessionStatus::Replay,
                    PracticeSessionStatus::Completed->label() => PracticeSessionStatus::Completed,
                ])
                ->renderAsBadges([
                    PracticeSessionStatus::Scheduled->value => 'secondary',
                    PracticeSessionStatus::Live->value => 'success',
                    PracticeSessionStatus::Replay->value => 'primary',
                    PracticeSessionStatus::Completed->value => 'dark',
                ])
                ->hideOnForm();
        }

        yield TextareaField::new('notes', 'Заметки')
            ->hideOnIndex()
            ->setRequired(false);

        yield UrlField::new('roomLink', 'Комната на сайте')
            ->onlyOnIndex()
            ->formatValue(fn (): string => $this->practiceRoomUrl());

        yield UrlField::new('roomLink', 'Комната на сайте')
            ->onlyOnDetail()
            ->setHelp('Участники с оплатой. Админ тоже может открыть после входа на сайт.')
            ->formatValue(fn (): string => $this->practiceRoomUrl());

        yield TextField::new('howToBroadcast', 'Как запустить')
            ->onlyOnDetail()
            ->setTemplatePath('admin/practice_session/howto.html.twig');

        yield TextField::new('kinescopeIngestUrl', 'RTMP URL (OBS Server)')
            ->onlyOnDetail()
            ->setHelp('OBS → Settings → Stream → Custom → Server');
        yield TextField::new('kinescopeStreamKey', 'Stream key (OBS)')
            ->onlyOnDetail()
            ->setHelp('OBS → Stream Key. Не светить участникам.');

        yield TextField::new('kinescopeEventId', 'Kinescope event id')
            ->onlyOnDetail()
            ->formatValue(static fn ($value, PracticeSession $session): ?string => $session->isSpeak() ? null : $session->getKinescopeEventId());

        yield TextField::new('kinescopeRoomCode', 'Код Speak')
            ->onlyOnDetail()
            ->formatValue(static fn ($value, PracticeSession $session): ?string => $session->isSpeak() ? $session->getKinescopeRoomCode() : null);

        yield DateTimeField::new('debitedAt', 'Списано')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->onlyOnDetail();
        yield DateTimeField::new('createdAt', 'Создано')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->onlyOnDetail();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof PracticeSession) {
            parent::persistEntity($entityManager, $entityInstance);

            return;
        }

        if (PracticeSessionStatus::Scheduled !== $entityInstance->getStatus()) {
            $entityInstance->setStatus(PracticeSessionStatus::Scheduled);
        }

        parent::persistEntity($entityManager, $entityInstance);

        if ($entityInstance->hasKinescopeRemote()) {
            return;
        }

        try {
            $this->kinescopeSessions->createRemoteForSession($entityInstance);
            $this->addFlash('success', $this->createdFlashMessage($entityInstance));
        } catch (\Throwable $exception) {
            $this->addFlash(
                'danger',
                'Сессия сохранена, но Kinescope не создал эфир/комнату: '.$exception->getMessage()
                .' Нажми «Создать в Kinescope» в карточке после правки настроек.',
            );
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof PracticeSession) {
            $entityInstance->touch();
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * @param AdminContext<PracticeSession> $context
     */
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        if (Action::NEW === $action) {
            $entity = $context->getEntity()->getInstance();
            if ($entity instanceof PracticeSession && null !== $entity->getId()) {
                return $this->redirectToDetail($entity);
            }
        }

        return parent::getRedirectResponseAfterSave($context, $action);
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
            $this->addFlash('success', $this->createdFlashMessage($session));
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

        if (!$session->hasKinescopeRemote()) {
            $this->addFlash('danger', 'Сначала создай эфир/комнату в Kinescope (кнопка «Создать в Kinescope»).');

            return $this->redirectToDetail($session);
        }

        $this->kinescopeSessions->goLive($session);
        $msg = $session->isSpeak()
            ? sprintf('«%s» в эфире. Заходи в комнату: %s', $session->getTitle(), $this->practiceRoomUrl())
            : sprintf(
                '«%s» в эфире на сайте. В OBS: Start Streaming (Server + Stream key из этой карточки). Участники: %s',
                $session->getTitle(),
                $this->practiceRoomUrl(),
            );
        $this->addFlash('success', $msg);

        return $this->redirectToDetail($session);
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

            return $this->redirectToDetail($session);
        }

        $this->addFlash(
            'success',
            $result['debited']
                ? sprintf('Сессия «%s»: запись открыта, встречи списаны.', $session->getTitle())
                : sprintf('Сессия «%s»: статус обновлён.', $session->getTitle()),
        );

        return $this->redirectToDetail($session);
    }

    #[AdminRoute(path: '/{entityId}/sync', name: 'sync')]
    public function syncSession(AdminContext $context): RedirectResponse
    {
        $session = $context->getEntity()->getInstance();
        if (!$session instanceof PracticeSession) {
            throw $this->createNotFoundException();
        }

        try {
            if (null !== $session->getKinescopeEventId()) {
                $this->kinescopeSessions->refreshLiveCredentials($session);
            }
            $this->kinescopeSessions->syncSession($session);
            $this->addFlash('success', 'Данные Kinescope обновлены (RTMP/запись).');
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

    private function createdFlashMessage(PracticeSession $session): string
    {
        $room = $this->practiceRoomUrl();
        if ($session->isSpeak()) {
            return sprintf('Speak готов. Дальше: «В эфир» → комната %s', $room);
        }

        $parts = ['Live готов. Дальше: скопируй RTMP + key → «В эфир» → OBS Start Streaming.'];
        if (null !== $session->getKinescopeIngestUrl()) {
            $parts[] = 'Server: '.$session->getKinescopeIngestUrl();
        }
        if (null !== $session->getKinescopeStreamKey()) {
            $parts[] = 'Key: '.$session->getKinescopeStreamKey();
        }
        $parts[] = 'Участники: '.$room;

        return implode(' · ', $parts);
    }

    private function practiceRoomUrl(): string
    {
        return $this->urlGenerator->generate(
            'web_practice_room',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
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
