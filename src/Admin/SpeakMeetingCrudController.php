<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\SpeakMeeting;
use App\Service\PracticeKinescopeService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SpeakMeetingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly PracticeKinescopeService $kinescopeSessions,
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
            ->setHelp(Crud::PAGE_NEW, 'После сохранения комната Kinescope создаётся сразу — ссылка для участников появится во flash и в карточке.')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $a) => $a->setLabel('Новая встреча'))
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex()->setLabel('#');
        yield TextField::new('title', 'Название')
            ->setHelp('Как увидишь в списке и в Kinescope');
        yield DateTimeField::new('startsAt', 'Начало')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->setRequired(false)
            ->setHelp('Необязательно — только для себя в списке');
        yield BooleanField::new('autorecording', 'Автозапись')
            ->renderAsSwitch(false)
            ->hideOnIndex();
        yield TextareaField::new('notes', 'Заметки')
            ->hideOnIndex()
            ->setRequired(false);

        // Property getter returns relative path; formatValue = absolute text for copy/open.
        yield UrlField::new('siteMeetUrl', 'Ссылка для участников')
            ->onlyOnIndex()
            ->formatValue(fn ($_, SpeakMeeting $m): string => $this->publicMeetUrl($m));

        yield UrlField::new('siteMeetUrl', 'Ссылка для участников')
            ->onlyOnDetail()
            ->formatValue(fn ($_, SpeakMeeting $m): string => $this->publicMeetUrl($m));

        yield TextField::new('kinescopeRoomCode', 'Код комнаты Speak')
            ->onlyOnDetail()
            ->setHelp('Технический код Kinescope; участникам давай ссылку сайта выше');

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
