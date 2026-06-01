<?php

namespace App\Repository;

use App\Entity\AccessRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccessRegistry>
 *
 * @method AccessRegistry|null find($id, $lockMode = null, $lockVersion = null)
 * @method AccessRegistry|null findOneBy(array $criteria, array $orderBy = null)
 * @method AccessRegistry[]    findAll()
 * @method AccessRegistry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AccessRegistryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessRegistry::class);
    }

    public function add(AccessRegistry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AccessRegistry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return AccessRegistry[]
     */
    public function findUnassignedApplicationsByPublicId(string $publicId): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.publicId = :publicId')
            ->andWhere('ar.application IS NOT NULL')
            ->andWhere('ar.domain IS NULL OR ar.domain = :emptyDomain')
            ->setParameter('publicId', $publicId)
            ->setParameter('emptyDomain', '')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return AccessRegistry[] Returns an array of AccessRegistry objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?AccessRegistry
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
