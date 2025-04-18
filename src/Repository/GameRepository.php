<?php

namespace App\Repository;

use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    //    /**
    //     * @return Game[] Returns an array of Game objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('g')
    //            ->andWhere('g.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('g.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Game
    //    {
    //        return $this->createQueryBuilder('g')
    //            ->andWhere('g.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function findByTitleLike(string $title): array
    {
        return $this->createQueryBuilder('g')
            ->where('LOWER(g.title) LIKE LOWER(:title)')
            ->setParameter('title', '%' . $title . '%')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les $limit meilleurs jeux sortis en $year.
     *
     * @return Game[]
     */
    public function findTopRatedByYear(int $year, int $limit = 5): array
    {
        $start = new \DateTimeImmutable("$year-01-01 00:00:00");
        $end   = new \DateTimeImmutable("$year-12-31 23:59:59");

        return $this->createQueryBuilder('g')
            ->andWhere('g.releaseDate BETWEEN :start AND :end')
            ->andWhere('g.totalRating IS NOT NULL')
            ->setParameter('start', $start)
            ->setParameter('end',   $end)
            ->orderBy('g.totalRating', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
