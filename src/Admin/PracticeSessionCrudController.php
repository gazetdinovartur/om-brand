<?php

declare(strict_types=1);

namespace App\Admin;

use App\Admin\Concern\CompactIndexActionsTrait;
use App\Admin\Concern\RequiresMutatingPostCsrfTrait;
use App\Entity\PracticeSession;
use App\Enum\PracticeSessionKind;
use App\Enum\PracticeSessionStatus;
use App\Infrastructure\Kinescope\KinescopeSpeakJwt;
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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PracticeSessionCrudController extends AbstractCrudController
{
    use CompactIndexActionsTrait;
    use RequiresMutatingPostCsrfTrait;

    public function __construct(
        private readonly PracticeSessionService $practiceSessions,
        private readonly PracticeKinescopeService $kinescopeSessions,
        private readonly KinescopeSpeakJwt $speakJwt,
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
                'Только встреча Speak (без OBS). После сохранения создаётся комната Kinescope; участники — «Комната на сайте».',
            )
            ->setHelp(
                Crud::PAGE_DETAIL,
                '«В эфир» → «Ведущий (JWT)» или комната на сайте. «Отменить комнату» удаляет комнату в Kinescope, карточка остаётся.',
            )
            ->setHelp(
                Crud::PAGE_EDIT,
                'Название уходит в комнату Kinescope при сохранении.',
            )
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $goLive = $this->mutatingAction('goLive', 'В эфир', 'fa fa-broadcast-tower', 'goLive')
            ->displayIf(static fn (PracticeSession $session): bool => PracticeSessionStatus::Scheduled === $session->getStatus()
                && $session->hasKinescopeRemote());

        $finish = $this->mutatingAction('finish', 'Встреча завершена', 'fa fa-check', 'finishSession')
            ->displayIf(static fn (PracticeSession $session): bool => \in_array(
                $session->getStatus(),
                [PracticeSessionStatus::Scheduled, PracticeSessionStatus::Live],
                true,
            ));

        $archive = $this->mutatingAction('archive', 'В архив', 'fa fa-archive', 'archiveSession')
            ->displayIf(static fn (PracticeSession $session): bool => PracticeSessionStatus::Replay === $session->getStatus());

        $retryRemote = $this->mutatingAction('createRemote', 'Создать комнату', 'fa fa-video', 'createRemote')
            ->displayIf(static fn (PracticeSession $session): bool => !$session->hasKinescopeRemote());

        $hostLink = Action::new('hostLink', 'Ведущий (JWT)', 'fa fa-user-tie')
            ->linkToUrl(fn (PracticeSession $session): string => $this->hostSpeakUrl($session) ?? '#')
            ->setHtmlAttributes([
                'target' => '_blank',
                'rel' => 'noopener',
                'title' => 'Ведущий (JWT)',
                'aria-label' => 'Ведущий (JWT)',
            ])
            ->displayIf(static fn (PracticeSession $session): bool => $session->isSpeak()
                && null !== $session->getKinescopeRoomCode());

        $calls = Action::new('calls', 'Звонки', 'fa fa-list')
            ->linkToCrudAction('listCalls')
            ->setHtmlAttributes(['title' => 'Звонки', 'aria-label' => 'Звонки'])
            ->displayIf(static fn (PracticeSession $session): bool => $session->isSpeak()
                && null !== $session->getKinescopeRoomId());

        $cancelRemote = $this->mutatingAction('cancelRemote', 'Отменить комнату', 'fa fa-ban', 'cancelRemote')
            ->displayIf(static fn (PracticeSession $session): bool => $session->isSpeak()
                && null !== $session->getKinescopeRoomId()
                && \in_array($session->getStatus(), [PracticeSessionStatus::Scheduled, PracticeSessionStatus::Live], true));

        return $this->compactBuiltinIndexActions($actions
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action) => $action->setLabel('Создать сессию'))
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $goLive)
            ->add(Crud::PAGE_DETAIL, $goLive)
            ->add(Crud::PAGE_INDEX, $hostLink)
            ->add(Crud::PAGE_DETAIL, $hostLink)
            ->add(Crud::PAGE_INDEX, $calls)
            ->add(Crud::PAGE_DETAIL, $calls)
            ->add(Crud::PAGE_INDEX, $finish)
            ->add(Crud::PAGE_DETAIL, $finish)
            ->add(Crud::PAGE_INDEX, $cancelRemote)
            ->add(Crud::PAGE_DETAIL, $cancelRemote)
            ->add(Crud::PAGE_INDEX, $archive)
            ->add(Crud::PAGE_DETAIL, $archive)
            ->add(Crud::PAGE_INDEX, $retryRemote)
            ->add(Crud::PAGE_DETAIL, $retryRemote)
            ->reorder(Crud::PAGE_INDEX, [
                Action::DETAIL,
                'goLive',
                'hostLink',
                'createRemote',
                'calls',
                'finish',
                'cancelRemote',
                'archive',
                Action::EDIT,
                Action::DELETE,
            ])
            ->reorder(Crud::PAGE_DETAIL, [
                'goLive',
                'hostLink',
                'createRemote',
                'calls',
                'finish',
                'cancelRemote',
                'archive',
                Action::EDIT,
                Action::DELETE,
            ]));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex()->setLabel('#');
        yield TextField::new('title', 'Название')
            ->setHelp('Синхронизируется с именем комнаты Speak в Kinescope');
        yield DateTimeField::new('startsAt', 'Начало')->setFormat('dd.MM.yyyy HH:mm');

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
            yield ChoiceField::new('kind', 'Формат')
                ->setChoices([
                    PracticeSessionKind::Speak->label() => PracticeSessionKind::Speak,
                    PracticeSessionKind::Live->label() => PracticeSessionKind::Live,
                ])
                ->formatValue(static function ($value, $entity): string {
                    if ($entity instanceof PracticeSession) {
                        return $entity->getKind()->label();
                    }

                    return $value instanceof PracticeSessionKind ? $value->label() : '';
                })
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
            ->setHelp('Участники с оплатой. Админ — после входа на сайт.')
            ->formatValue(fn (): string => $this->practiceRoomUrl());

        yield TextField::new('howToBroadcast', 'Как провести')
            ->onlyOnDetail()
            ->setTemplatePath('admin/practice_session/howto.html.twig');

        yield TextField::new('kinescopeRoomCode', 'Код Speak')
            ->onlyOnDetail()
            ->formatValue(static fn ($value, PracticeSession $session): ?string => $session->isSpeak() ? $session->getKinescopeRoomCode() : null);

        yield TextField::new('kinescopeVideoId', 'Video ID записи')
            ->onlyOnDetail()
            ->setHelp('Появляется после speak.record.finished / ручного завершения');

        // Legacy Live sessions only
        yield TextField::new('kinescopeIngestUrl', 'RTMP URL (устаревший Live)')
            ->onlyOnDetail()
            ->formatValue(static fn ($value, PracticeSession $session): ?string => $session->isSpeak() ? null : $session->getKinescopeIngestUrl());
        yield TextField::new('kinescopeStreamKey', 'Stream key (устаревший Live)')
            ->onlyOnDetail()
            ->formatValue(static fn ($value, PracticeSession $session): ?string => $session->isSpeak() ? null : $session->getKinescopeStreamKey());

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

        $entityInstance->setKind(PracticeSessionKind::Speak);
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
                'Сессия сохранена, но комнату Speak создать не удалось: '.$exception->getMessage()
                .' Нажми «Создать комнату» в карточке.',
            );
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof PracticeSession) {
            $entityInstance->touch();
        }
        parent::updateEntity($entityManager, $entityInstance);

        if (!$entityInstance instanceof PracticeSession || !$entityInstance->isSpeak()) {
            return;
        }

        try {
            $this->kinescopeSessions->syncSpeakSessionToRemote($entityInstance);
            $this->addFlash('success', 'Название отправлено в комнату Kinescope.');
        } catch (\Throwable $exception) {
            $this->addFlash(
                'warning',
                'На сайте сохранено, но Kinescope не обновился: '.$exception->getMessage(),
            );
        }
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof PracticeSession && $entityInstance->isSpeak()
            && null !== $entityInstance->getKinescopeRoomId()) {
            try {
                $this->kinescopeSessions->deleteSessionRemote($entityInstance);
            } catch (\Throwable $exception) {
                $this->addFlash(
                    'warning',
                    'Сессия удалена на сайте, но комнату Kinescope убрать не удалось: '.$exception->getMessage(),
                );
            }
        }

        parent::deleteEntity($entityManager, $entityInstance);
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

    #[AdminRoute(path: '/{entityId}/create-remote', name: 'create_remote', options: ['methods' => ['POST']])]
    public function createRemote(AdminContext $context): RedirectResponse
    {
        $this->assertMutatingAdminPost($context, 'createRemote');
        $session = $context->getEntity()->getInstance();
        if (!$session instanceof PracticeSession) {
            throw $this->createNotFoundException();
        }

        $session->setKind(PracticeSessionKind::Speak);

        try {
            $this->kinescopeSessions->createRemoteForSession($session);
            $this->addFlash('success', $this->createdFlashMessage($session));
        } catch (\Throwable $exception) {
            $this->addFlash('danger', 'Kinescope: '.$exception->getMessage());
        }

        return $this->redirectToDetail($session);
    }

    #[AdminRoute(path: '/{entityId}/go-live', name: 'go_live', options: ['methods' => ['POST']])]
    public function goLive(AdminContext $context): RedirectResponse
    {
        $this->assertMutatingAdminPost($context, 'goLive');
        $session = $context->getEntity()->getInstance();
        if (!$session instanceof PracticeSession) {
            throw $this->createNotFoundException();
        }

        if (!$session->hasKinescopeRemote()) {
            $this->addFlash('danger', 'Сначала создай комнату Speak.');

            return $this->redirectToDetail($session);
        }

        $this->kinescopeSessions->goLive($session);
        $this->addFlash(
            'success',
            sprintf('«%s» в эфире. Заходи ведущим или в комнату: %s', $session->getTitle(), $this->practiceRoomUrl()),
        );

        return $this->redirectToDetail($session);
    }

    #[AdminRoute(path: '/{entityId}/finish', name: 'finish', options: ['methods' => ['POST']])]
    public function finishSession(AdminContext $context): RedirectResponse
    {
        $this->assertMutatingAdminPost($context, 'finish');
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

    #[AdminRoute(path: '/{entityId}/archive', name: 'archive', options: ['methods' => ['POST']])]
    public function archiveSession(AdminContext $context): RedirectResponse
    {
        $this->assertMutatingAdminPost($context, 'archive');
        $session = $context->getEntity()->getInstance();
        if (!$session instanceof PracticeSession) {
            throw $this->createNotFoundException();
        }

        $this->practiceSessions->archive($session);
        $this->addFlash('success', sprintf('Сессия «%s» в архиве.', $session->getTitle()));

        return $this->redirectToIndex();
    }

    #[AdminRoute(path: '/{entityId}/cancel-remote', name: 'cancel_remote', options: ['methods' => ['POST']])]
    public function cancelRemote(AdminContext $context): RedirectResponse
    {
        $this->assertMutatingAdminPost($context, 'cancelRemote');
        $session = $context->getEntity()->getInstance();
        if (!$session instanceof PracticeSession) {
            throw $this->createNotFoundException();
        }

        try {
            $this->kinescopeSessions->cancelSpeakSessionRemote($session);
            $this->addFlash(
                'success',
                'Комната удалена в Kinescope. Карточка осталась (статус «Архив»). Можно снова нажать «Создать комнату».',
            );
        } catch (\Throwable $exception) {
            $this->addFlash('danger', 'Не удалось отменить комнату: '.$exception->getMessage());
        }

        return $this->redirectToDetail($session);
    }

    #[AdminRoute(path: '/{entityId}/calls', name: 'calls')]
    public function listCalls(AdminContext $context): Response
    {
        $session = $context->getEntity()->getInstance();
        if (!$session instanceof PracticeSession) {
            throw $this->createNotFoundException();
        }

        $calls = [];
        $error = null;
        try {
            $calls = $this->kinescopeSessions->listCallsForSession($session);
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        return $this->render('admin/practice_session/calls.html.twig', [
            'session' => $session,
            'calls' => $calls,
            'error' => $error,
            'backUrl' => $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($session->getId())
                ->generateUrl(),
            'jwtConfigured' => $this->speakJwt->isConfigured(),
        ]);
    }

    private function createdFlashMessage(PracticeSession $session): string
    {
        return sprintf(
            'Комната Speak готова. Дальше: «В эфир» → «Ведущий (JWT)» или %s',
            $this->practiceRoomUrl(),
        );
    }

    private function hostSpeakUrl(PracticeSession $session): ?string
    {
        $code = $session->getKinescopeRoomCode();
        if (null === $code || '' === $code) {
            return null;
        }

        if ($this->speakJwt->isConfigured()) {
            return $this->speakJwt->buildAdminEmbedUrl($code);
        }

        return 'https://speak.kinescope.io/'.$code;
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

    private function mutatingAction(string $name, string $label, string $icon, string $crudAction): Action
    {
        return Action::new($name, $label, $icon)
            ->linkToCrudAction($crudAction)
            ->renderAsForm()
            ->setTemplatePath('admin/action_post_csrf.html.twig')
            ->setHtmlAttributes(['title' => $label, 'aria-label' => $label]);
    }
}
