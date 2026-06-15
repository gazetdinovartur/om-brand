<?php

namespace App\Repository;

use App\Entity\SiteSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SiteSettings> */
class SiteSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteSettings::class);
    }

    public function getSettings(): SiteSettings
    {
        $settings = $this->findOneBy([]);

        if (!$settings instanceof SiteSettings) {
            $settings = new SiteSettings();
        }

        return $settings;
    }
}
