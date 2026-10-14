<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\SpeakMeeting;
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
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SpeakMeetingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly PracticeKinescopeService $kinescopeSessions,
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
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $createRoom = Action::new('createRoom', 'Создать комнату', 'fa fa-video')
            ->linkToCrudAction('createRoom')
            ->displayIf(static fn (SpeakMeeting $m): bool => null === $m->getKinescopeRoomId());

        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $a) => $a->setLabel('Новая встреча'))
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $createRoom)
            ->add(Crud::PAGE_DETAIL, $createRoom);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('title', 'Название');
        yield DateTimeField::new('startsAt', 'Начало')->setFormat('dd.MM.yyyy HH:mm');
        yield ChoiceField::new('roomType', 'Тип комнаты')
            ->setChoices([
                'Постоянная' => 'permanent_meeting',
                'Разовая' => 'instant_meeting',
                'По расписанию' => 'scheduled_meeting',
            ]);
        yield BooleanField::new('autorecording', 'Автозапись');
        yield TextField::new('token', 'Токен ссылки')->onlyOnDetail();
        yield TextField::new('kinescopeRoomId', 'Room id')->hideOnIndex();
        yield TextField::new('kinescopeRoomCode', 'Code')->hideOnIndex();
        yield TextField::new('speakEmbedUrl', 'Embed URL')->onlyOnDetail();
        yield TextareaField::new('notes', 'Заметки')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Создано')->setFormat('dd.MM.yyyy HH:mm')->onlyOnDetail();
    }

    #[AdminRoute(path: '/{entityId}/create-room', name: 'create_room')]
    public function createRoom(AdminContext $context): RedirectResponse
    {
        $meeting = $context->getEntity()->getInstance();
        if (!$meeting instanceof SpeakMeeting) {
            throw $this->createNotFoundException();
        }

        try {
            $this->kinescopeSessions->createSpeakMeetingRemote($meeting);
            $url = $this->urlGenerator->generate(
                'web_speak_meet',
                ['token' => $meeting->getToken()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
            $this->addFlash('success', 'Комната создана. Ссылка для участников: '.$url);
        } catch (\Throwable $exception) {
            $this->addFlash('danger', 'Kinescope: '.$exception->getMessage());
        }

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($meeting->getId())
                ->generateUrl(),
        );
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof SpeakMeeting) {
            $entityInstance->touch();
        }
        parent::updateEntity($entityManager, $entityInstance);
    }
}
