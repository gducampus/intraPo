<?php

namespace App\Repository;

use App\Entity\DocumentFolder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentFolder>
 */
class DocumentFolderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentFolder::class);
    }

    /**
     * @return DocumentFolder[]
     */
    public function findRootFoldersOrdered(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.parent IS NULL')
            ->orderBy('f.position', 'ASC')
            ->addOrderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return DocumentFolder[]
     */
    public function findPotentialParents(?DocumentFolder $current = null): array
    {
        $all = $this->createQueryBuilder('f')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();

        if (!$current instanceof DocumentFolder || null === $current->getId()) {
            return $all;
        }

        return array_values(array_filter(
            $all,
            fn (DocumentFolder $candidate): bool => !$this->isSameOrDescendant($candidate, $current)
        ));
    }

    private function isSameOrDescendant(DocumentFolder $candidate, DocumentFolder $current): bool
    {
        if ($candidate->getId() === $current->getId()) {
            return true;
        }

        $cursor = $candidate->getParent();
        while ($cursor instanceof DocumentFolder) {
            if ($cursor->getId() === $current->getId()) {
                return true;
            }

            $cursor = $cursor->getParent();
        }

        return false;
    }
}
