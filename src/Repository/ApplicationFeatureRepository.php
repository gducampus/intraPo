<?php

namespace App\Repository;

use App\Entity\ApplicationFeature;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApplicationFeature>
 */
class ApplicationFeatureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApplicationFeature::class);
    }

    /**
     * @return array<int, array{id: int|string, label: string|null, description: string|null, category: string|null, routeName: string|null, url: string|null}>
     */
    public function findCatalogRows(): array
    {
        return $this->createQueryBuilder('feature')
            ->select(
                'feature.id AS id',
                'feature.label AS label',
                'feature.description AS description',
                'feature.category AS category',
                'feature.routeName AS routeName',
                'feature.url AS url'
            )
            ->orderBy('feature.label', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
