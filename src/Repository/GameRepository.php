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
     * Retourne les jeux du Top 100.
     * Filtre les jeux avec au moins 50 votes et trie par note pondérée.
     *
     * @return Game[]
     */
    public function findTop100Games(int $limit = 5): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.totalRating IS NOT NULL')
            ->andWhere('g.totalRating >= 75')
            ->andWhere('g.totalRatingCount >= 80')
            ->orderBy('g.totalRating', 'DESC')
            ->addOrderBy('g.totalRatingCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les meilleurs jeux sortis dans les 365 derniers jours.
     * Trie par totalRating décroissant.
     *
     * @return Game[]
     */
    public function findTopYearGames(int $limit = 5): array
    {
        $oneYearAgo = new \DateTimeImmutable('-365 days');
        return $this->createQueryBuilder('g')
            ->andWhere('g.releaseDate >= :oneYearAgo')
            ->andWhere('g.totalRating IS NOT NULL')
            ->andWhere('g.totalRating >= 80')
            ->andWhere('g.totalRatingCount >= 50')
            ->setParameter('oneYearAgo', $oneYearAgo)
            ->orderBy('g.totalRating', 'DESC')
            ->addOrderBy('g.totalRatingCount', 'DESC')
            ->addOrderBy('g.releaseDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les meilleurs jeux sortis dans les 365 derniers jours avec critères stricts.
     * Filtre les jeux avec une note >= 80 et au moins 80 votes.
     * Trie par totalRating décroissant.
     *
     * @param int $limit Nombre maximum de jeux à retourner
     * @param int $minRating Note minimum (sur 100)
     * @param int $minVotes Nombre minimum de votes
     * @return Game[]
     */
    public function findTopYearGamesWithCriteria(int $limit = 5, int $minRating = 80, int $minVotes = 80): array
    {
        $oneYearAgo = new \DateTimeImmutable('-365 days');

        return $this->createQueryBuilder('g')
            ->andWhere('g.releaseDate >= :oneYearAgo')
            ->andWhere('g.totalRating IS NOT NULL')
            ->andWhere('g.totalRating >= :minRating')
            ->andWhere('(g.totalRatingCount >= :minVotes OR g.totalRatingCount IS NULL)')
            ->setParameter('oneYearAgo', $oneYearAgo)
            ->setParameter('minRating', $minRating)
            ->setParameter('minVotes', $minVotes)
            ->orderBy('g.totalRating', 'DESC')
            ->addOrderBy('g.totalRatingCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
