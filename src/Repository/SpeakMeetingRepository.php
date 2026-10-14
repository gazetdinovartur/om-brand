<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SpeakMeeting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SpeakMeeting> */
class SpeakMeetingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpeakMeeting::class);
    }

    public function findOneByToken(string $token): ?SpeakMeeting
    {
        $token = trim($token);
        if ('' === $token) {
            return null;
        }

        return $this->findOneBy(['token' => $token]);
    }

    public function findOneByKinescopeRoomId(string $roomId): ?SpeakMeeting
    {
        $roomId = trim($roomId);
        if ('' === $roomId) {
            return null;
        }

        return $this->findOneBy(['kinescopeRoomId' => $roomId]);
    }
}
