<?php

declare(strict_types=1);

namespace App\Admin;

use App\Admin\Concern\RequiresMutatingPostCsrfTrait;
use App\Entity\SpeakMeeting;
use App\Infrastructure\Kinescope\KinescopeSpeakJwt;
use App\Service\PracticeKinescopeService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
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

class SpeakMeetingCrudController extends AbstractCrudController
{
    use RequiresMutatingPostCsrfTrait;

    public function __construct(
        private readonly PracticeKinescopeService $kinescopeSessions,
        private readonly KinescopeSpeakJwt $speakJwt,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SpeakMeeting::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Встреча Speak')
            ->setEntityLabelInPlural('Встречи Speak')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPageTitle(Crud::PAGE_INDEX, 'Встречи Speak')
            ->setPageTitle(Crud::PAGE_NEW, 'Новая встреча')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать встречу')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Встреча Speak')
            ->setHelp(
                Crud::PAGE_NEW,
                'После сохранения комната создаётся в Kinescope. Правки названия/записи/транскрипции уходят в Kinescope при сохранении.',
            )
            ->setHelp(
                Crud::PAGE_EDIT,
                'Название, автозапись и транскрипция синхронизируются с комнатой Kinescope. Тип комнаты, «ждать ведущего» и waiting room задаются только при создании.',
            )
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $hostLink = Action::new('hostLink', 'Ведущий (JWT)', 'fa fa-user-tie')
            ->linkToUrl(fn (SpeakMeeting $meeting): string => $this->hostSpeakUrl($meeting) ?? '#')
            ->setHtmlAttributes(['target' => '_blank', 'rel' => 'noopener'])
            ->displayIf(static fn (SpeakMeeting $meeting): bool => null !== $meeting->getKinescopeRoomCode());

        $calls = Action::new('calls', 'Звонки', 'fa fa-list')
            ->linkToCrudAction('listCalls')
            ->displayIf(static fn (SpeakMeeting $meeting): bool => null !== $meeting->getKinescopeRoomId());

        $cancelRemote = Action::new('cancelRemote', 'Отменить комнату', 'fa fa-ban')
            ->linkToCrudAction('cancelRemote')
            ->renderAsForm()
            ->setTemplatePath('admin/action_post_csrf.html.twig')
            ->displayIf(static fn (SpeakMeeting $meeting): bool => null !== $meeting->getKinescopeRoomId());

        $createRemote = Action::new('createRemote', 'Создать комнату', 'fa fa-video')
            ->linkToCrudAction('createRemote')
            ->renderAsForm()
            ->setTemplatePath('admin/action_post_csrf.html.twig')
            ->displayIf(static fn (SpeakMeeting $meeting): bool => null === $meeting->getKinescopeRoomId());

        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $a) => $a->setLabel('Новая встреча'))
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $hostLink)
            ->add(Crud::PAGE_DETAIL, $hostLink)
            ->add(Crud::PAGE_INDEX, $createRemote)
            ->add(Crud::PAGE_DETAIL, $createRemote)
            ->add(Crud::PAGE_INDEX, $calls)
            ->add(Crud::PAGE_DETAIL, $calls)
            ->add(Crud::PAGE_INDEX, $cancelRemote)
            ->add(Crud::PAGE_DETAIL, $cancelRemote)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'hostLink', 'createRemote', 'calls', 'cancelRemote', Action::EDIT, Action::DELETE])
            ->reorder(Crud::PAGE_DETAIL, ['hostLink', 'createRemote', 'calls', 'cancelRemote', Action::EDIT, Action::DELETE]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex()->setLabel('#');
        yield TextField::new('title', 'Название')
            ->setHelp('Синхронизируется с именем комнаты в Kinescope');
        yield DateTimeField::new('startsAt', 'Начало')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->setRequired(false)
            ->setHelp('Только для списка на сайте; в Kinescope отдельного поля нет');

        if (Crud::PAGE_NEW === $pageName) {
            yield ChoiceField::new('roomType', 'Тип комнаты')
                ->setChoices([
                    'Постоянная' => 'permanent_meeting',
                    'Разовая' => 'instant_meeting',
                    'По расписанию' => 'scheduled_meeting',
                ])
                ->setHelp('Задаётся только при создании');
            yield BooleanField::new('waitingRoom', 'Зал ожидания')
                ->renderAsSwitch(false)
                ->setHelp('Гости ждут, пока ведущий впустит. Только при создании.');
            yield BooleanField::new('waitForHost', 'Ждать ведущего')
                ->renderAsSwitch(false)
                ->setHelp('Гости входят после появления ведущего. Только при создании.');
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            yield TextField::new('roomType', 'Тип комнаты');
            yield BooleanField::new('waitingRoom', 'Зал ожидания')->renderAsSwitch(false);
            yield BooleanField::new('waitForHost', 'Ждать ведущего')->renderAsSwitch(false);
        }

        yield BooleanField::new('autorecording', 'Автозапись')
            ->renderAsSwitch(false)
            ->setHelp('Синхронизируется с Kinescope');
        yield BooleanField::new('transcriptionAutostart', 'Автотранскрипция')
            ->renderAsSwitch(false)
            ->hideOnIndex()
            ->setHelp('Синхронизируется с Kinescope');
        yield TextareaField::new('notes', 'Заметки')
            ->hideOnIndex()
            ->setRequired(false)
            ->setHelp('Только у нас на сайте');

        yield UrlField::new('siteMeetUrl', 'Ссылка для участников')
            ->onlyOnIndex()
            ->formatValue(fn ($_, SpeakMeeting $m): string => $this->publicMeetUrl($m));

        yield UrlField::new('siteMeetUrl', 'Ссылка для участников')
            ->onlyOnDetail()
            ->formatValue(fn ($_, SpeakMeeting $m): string => $this->publicMeetUrl($m));

        yield TextField::new('kinescopeRoomCode', 'Код комнаты Speak')
            ->onlyOnDetail()
            ->setHelp('Технический код Kinescope');

        yield DateTimeField::new('createdAt', 'Создано')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->onlyOnDetail();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof SpeakMeeting) {
            parent::persistEntity($entityManager, $entityInstance);

            return;
        }

        parent::persistEntity($entityManager, $entityInstance);

        if (null !== $entityInstance->getKinescopeRoomId()) {
            return;
        }

        try {
            $this->kinescopeSessions->createSpeakMeetingRemote($entityInstance);
            $this->addFlash(
                'success',
                'Встреча и комната готовы. Ссылка для участников: '.$this->publicMeetUrl($entityInstance),
            );
        } catch (\Throwable $exception) {
            $this->addFlash(
                'danger',
                'Встреча сохранена, но комнату Kinescope создать не удалось: '.$exception->getMessage(),
            );
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof SpeakMeeting) {
            $entityInstance->touch();
        }
        parent::updateEntity($entityManager, $entityInstance);

        if (!$entityInstance instanceof SpeakMeeting || null === $entityInstance->getKinescopeRoomId()) {
            return;
        }

        try {
            $this->kinescopeSessions->syncSpeakMeetingToRemote($entityInstance);
            $this->addFlash('success', 'Изменения отправлены в комнату Kinescope (название, запись, транскрипция).');
        } catch (\Throwable $exception) {
            $this->addFlash(
                'warning',
                'На сайте сохранено, но Kinescope не обновился: '.$exception->getMessage(),
            );
        }
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof SpeakMeeting && null !== $entityInstance->getKinescopeRoomId()) {
            try {
                $this->kinescopeSessions->deleteSpeakMeetingRemote($entityInstance);
            } catch (\Throwable $exception) {
                $this->addFlash(
                    'warning',
                    'Встреча удалена на сайте, но комнату Kinescope убрать не удалось: '.$exception->getMessage(),
                );
            }
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    #[AdminRoute(path: '/{entityId}/create-remote', name: 'create_remote', options: ['methods' => ['POST']])]
    public function createRemote(AdminContext $context): RedirectResponse
    {
        $this->assertMutatingAdminPost($context, 'createRemote');
        $meeting = $context->getEntity()->getInstance();
        if (!$meeting instanceof SpeakMeeting) {
            throw $this->createNotFoundException();
        }

        try {
            $this->kinescopeSessions->createSpeakMeetingRemote($meeting);
            $this->addFlash(
                'success',
                'Комната Speak готова. Участники: '.$this->publicMeetUrl($meeting),
            );
        } catch (\Throwable $exception) {
            $this->addFlash('danger', 'Kinescope: '.$exception->getMessage());
        }

        return $this->redirectToDetail($meeting);
    }

    #[AdminRoute(path: '/{entityId}/cancel-remote', name: 'cancel_remote', options: ['methods' => ['POST']])]
    public function cancelRemote(AdminContext $context): RedirectResponse
    {
        $this->assertMutatingAdminPost($context, 'cancelRemote');
        $meeting = $context->getEntity()->getInstance();
        if (!$meeting instanceof SpeakMeeting) {
            throw $this->createNotFoundException();
        }

        try {
            $this->kinescopeSessions->cancelSpeakMeetingRemote($meeting);
            $this->addFlash(
                'success',
                'Комната удалена в Kinescope. Карточка осталась — нажми «Создать комнату».',
            );
        } catch (\Throwable $exception) {
            $this->addFlash('danger', 'Не удалось отменить комнату: '.$exception->getMessage());
        }

        return $this->redirectToDetail($meeting);
    }

    private function redirectToDetail(SpeakMeeting $meeting): RedirectResponse
    {
        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($meeting->getId())
                ->generateUrl(),
        );
    }

    #[AdminRoute(path: '/{entityId}/calls', name: 'calls')]
    public function listCalls(AdminContext $context): Response
    {
        $meeting = $context->getEntity()->getInstance();
        if (!$meeting instanceof SpeakMeeting) {
            throw $this->createNotFoundException();
        }

        $calls = [];
        $error = null;
        try {
            $calls = $this->kinescopeSessions->listCallsForMeeting($meeting);
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        return $this->render('admin/speak_meeting/calls.html.twig', [
            'meeting' => $meeting,
            'calls' => $calls,
            'error' => $error,
            'backUrl' => $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($meeting->getId())
                ->generateUrl(),
            'jwtConfigured' => $this->speakJwt->isConfigured(),
        ]);
    }

    private function hostSpeakUrl(SpeakMeeting $meeting): ?string
    {
        $code = $meeting->getKinescopeRoomCode();
        if (null === $code || '' === $code) {
            return null;
        }

        if ($this->speakJwt->isConfigured()) {
            return $this->speakJwt->buildAdminEmbedUrl($code);
        }

        return 'https://speak.kinescope.io/'.$code;
    }

    private function publicMeetUrl(SpeakMeeting $meeting): string
    {
        return $this->urlGenerator->generate(
            'web_speak_meet',
            ['token' => $meeting->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
