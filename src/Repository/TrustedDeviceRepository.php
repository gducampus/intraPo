<?php

namespace App\Repository;

use App\Entity\PoUser;
use App\Entity\TrustedDevice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrustedDevice>
 */
class TrustedDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrustedDevice::class);
    }

    public function findOneByUserAndHash(PoUser $user, string $deviceHash): ?TrustedDevice
    {
        return $this->createQueryBuilder('device')
            ->andWhere('device.user = :user')
            ->andWhere('device.deviceHash = :hash')
            ->setParameter('user', $user)
            ->setParameter('hash', $deviceHash)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return TrustedDevice[]
     */
    public function findPending(int $limit = 200): array
    {
        return $this->createQueryBuilder('device')
            ->leftJoin('device.user', 'user')->addSelect('user')
            ->leftJoin('device.approvedBy', 'approvedBy')->addSelect('approvedBy')
            ->andWhere('device.isApproved = :approved')
            ->setParameter('approved', false)
            ->orderBy('device.requestedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TrustedDevice[]
     */
    public function findApproved(int $limit = 200): array
    {
        return $this->createQueryBuilder('device')
            ->leftJoin('device.user', 'user')->addSelect('user')
            ->leftJoin('device.approvedBy', 'approvedBy')->addSelect('approvedBy')
            ->andWhere('device.isApproved = :approved')
            ->setParameter('approved', true)
            ->orderBy('device.lastSeenAt', 'DESC')
            ->addOrderBy('device.approvedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
