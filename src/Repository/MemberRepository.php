<?php

namespace App\Repository;

use App\Entity\Member;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Member>
 */
class MemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Member::class);
    }

    public function findOneByPreferredEmail(string $email): ?Member
    {
        return $this->createQueryBuilder('m')
            ->andWhere('LOWER(m.preferredEmail) = :email')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByIdentity(?string $lastNameOrCompany, ?string $firstNameOrService, ?string $city): ?Member
    {
        if (!$lastNameOrCompany && !$firstNameOrService) {
            return null;
        }

        $qb = $this->createQueryBuilder('m');

        if ($lastNameOrCompany) {
            $qb->andWhere('LOWER(m.lastNameOrCompany) = :lastName')
                ->setParameter('lastName', mb_strtolower(trim($lastNameOrCompany)));
        }

        if ($firstNameOrService) {
            $qb->andWhere('LOWER(m.firstNameOrService) = :firstName')
                ->setParameter('firstName', mb_strtolower(trim($firstNameOrService)));
        }

        if ($city) {
            $qb->andWhere('LOWER(m.city) = :city')
                ->setParameter('city', mb_strtolower(trim($city)));
        }

        return $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    /**
     * @return Member[]
     */
    public function findPaginated(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        return $this->createQueryBuilder('m')
            ->leftJoin('m.sector', 's')
            ->addSelect('s')
            ->orderBy('m.lastNameOrCompany', 'ASC')
            ->addOrderBy('m.firstNameOrService', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Member[]
     */
    public function findAllWithCoordinates(): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.sector', 's')
            ->addSelect('s')
            ->andWhere('m.latitude IS NOT NULL')
            ->andWhere('m.longitude IS NOT NULL')
            ->orderBy('m.lastNameOrCompany', 'ASC')
            ->addOrderBy('m.firstNameOrService', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
