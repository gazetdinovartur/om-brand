<?php

namespace App\EventSubscriber;

use App\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminUserPasswordSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'hashPassword',
            BeforeEntityUpdatedEvent::class => 'hashPassword',
        ];
    }

    public function hashPassword(BeforeEntityPersistedEvent|BeforeEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!$entity instanceof AdminUser) {
            return;
        }

        $plainPassword = $entity->getPassword();

        if ($event instanceof BeforeEntityUpdatedEvent && '' === $plainPassword) {
            $original = $this->entityManager->getUnitOfWork()->getOriginalEntityData($entity);
            if (isset($original['password'])) {
                $entity->setPassword($original['password']);
            }

            return;
        }

        if ('' === $plainPassword) {
            return;
        }

        if (!str_starts_with($plainPassword, '$')) {
            $entity->setPassword($this->passwordHasher->hashPassword($entity, $plainPassword));
        }
    }
}
