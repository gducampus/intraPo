<?php

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    /**
     * @return Role[]
     */
    public function findAllOrderedByLabel(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.label', 'ASC')
            ->addOrderBy('r.code', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

