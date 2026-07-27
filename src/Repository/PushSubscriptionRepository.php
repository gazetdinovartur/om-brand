<?php

namespace App\Repository;

use App\Entity\PushSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushSubscription>
 */
class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    public function findOneByEndpoint(string $endpoint): ?PushSubscription
    {
        return $this->findOneBy(['endpointHash' => PushSubscription::hashEndpoint($endpoint)]);
    }

    /**
     * @return list<PushSubscription>
     */
    public function findAllSubscriptions(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
