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

    /**
     * Retourne les meilleurs jeux des 365 derniers jours DÉDUPLIQUÉS par nom principal.
     * Évite les doublons comme "Clair Obscur: Expedition 33" et "Clair Obscur: Expedition 33 – Deluxe Edition".
     * Prend la version avec la meilleure note pour chaque nom principal.
     * Filtre uniquement les jeux principaux (pas les DLC/expansions).
     *
     * @param int $limit Nombre maximum de jeux à retourner
     * @param int $minRating Note minimum (sur 100)
     * @param int $minVotes Nombre minimum de votes
     * @return Game[]
     */
    public function findTopYearGamesDeduplicated(int $limit = 5, int $minRating = 80, int $minVotes = 80): array
    {
        $oneYearAgo = new \DateTimeImmutable('-365 days');

        // Récupère tous les jeux qui respectent les critères
        $allGames = $this->createQueryBuilder('g')
            ->andWhere('g.releaseDate >= :oneYearAgo')
            ->andWhere('g.totalRating IS NOT NULL')
            ->andWhere('g.totalRating >= :minRating')
            ->andWhere('(g.totalRatingCount >= :minVotes OR g.totalRatingCount IS NULL)')
            // Filtre uniquement les jeux principaux (category = 0 ou null)
            ->andWhere('(g.category = 0 OR g.category IS NULL)')
            ->setParameter('oneYearAgo', $oneYearAgo)
            ->setParameter('minRating', $minRating)
            ->setParameter('minVotes', $minVotes)
            ->orderBy('g.totalRating', 'DESC')
            ->addOrderBy('g.totalRatingCount', 'DESC')
            ->getQuery()
            ->getResult();

        // Groupe les jeux par nom principal et garde le meilleur de chaque groupe
        $groupedGames = [];

        foreach ($allGames as $game) {
            $mainTitle = $this->extractMainTitle($game->getTitle());
            
            // Si on n'a pas encore vu ce titre principal, ou si ce jeu a une meilleure note
            if (!isset($groupedGames[$mainTitle]) || 
                $game->getTotalRating() > $groupedGames[$mainTitle]->getTotalRating() ||
                ($game->getTotalRating() == $groupedGames[$mainTitle]->getTotalRating() && 
                 $game->getTotalRatingCount() > $groupedGames[$mainTitle]->getTotalRatingCount())) {
                
                $groupedGames[$mainTitle] = $game;
            }
        }

        // Trie par note décroissante
        uasort($groupedGames, function($a, $b) {
            if ($a->getTotalRating() != $b->getTotalRating()) {
                return $b->getTotalRating() <=> $a->getTotalRating();
            }
            return $b->getTotalRatingCount() <=> $a->getTotalRatingCount();
        });

        // Prend les premiers selon la limite
        return array_slice(array_values($groupedGames), 0, $limit);
    }

    /**
     * Extrait le nom principal d'un titre de jeu avec une regex générique.
     * Supprime les suffixes courants (Edition, Remake, Remastered, DLC, Update, Pass, etc.).
     *
     * @param string $title Le titre complet du jeu
     * @return string Le nom principal du jeu
     */
    private function extractMainTitle(string $title): string
    {
        // Normaliser les tirets et espaces spéciaux
        $normalized = str_replace([
            '\x{2013}', // EN DASH
            '\x{2014}', // EM DASH
            '\x{00A0}', // espace insécable
            '–', // EN DASH
            '—', // EM DASH
            chr(194).chr(160), // espace insécable utf-8
        ], ['-', '-', ' ', '-', '-', ' '], $title);

        // Regex pour supprimer les suffixes courants après :, -, ou espace
        $mainTitle = preg_replace(
            '/([:\-\s])\s*(Deluxe Edition|Ultimate Edition|Collector\'s Edition|Friend\'s Pass|Season Pass|Vicious Void|Vicious Void Galaxy|Winter Wonder|Stellar Speedway|A Big Adventure|Costume|Remastered|Remake|Definitive Edition|DLC|Update|Expansion|Galaxy|Wonder|Speedway|Pass|Edition)$/iu',
            '',
            $normalized
        );

        // Nettoyer les séparateurs qui restent à la fin
        $mainTitle = preg_replace('/([:\-])\s*$/u', '', $mainTitle);

        return trim($mainTitle);
    }
}
