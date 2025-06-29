<?php

namespace App\Command;

use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 🧹 COMMANDE DE NETTOYAGE - OPTIMISATION HERO BANNER
 * 
 * Cette commande nettoie la base de données pour optimiser l'affichage
 * du HeroBanner en supprimant les jeux de faible qualité et en priorisant
 * les jeux récents de 2024-2025.
 * 
 * 🎯 OBJECTIF :
 * - Supprimer les jeux avec moins de 50 votes
 * - Prioriser les jeux récents (2024-2025)
 * - Optimiser l'affichage du carousel HeroBanner
 * 
 * ⚡ UTILISATION :
 * php bin/console app:clean-low-quality-games
 */

#[AsCommand(
    name: 'app:clean-low-quality-games',
    description: 'Nettoie les jeux de faible qualité pour optimiser le HeroBanner',
)]
class CleanLowQualityGamesCommand extends Command
{
    public function __construct(
        private GameRepository $gameRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🧹 Nettoyage des jeux de faible qualité');
        $io->text('🎯 Optimisation de l\'affichage du HeroBanner');

        // Compte les jeux avant nettoyage
        $totalBefore = $this->gameRepository->count([]);
        $io->text("📊 Total des jeux avant nettoyage : {$totalBefore}");

        // Supprime les jeux avec moins de 50 votes
        $connection = $this->entityManager->getConnection();
        
        // Supprime d'abord les entités liées dans l'ordre correct
        $io->text('🧹 Suppression des entités liées...');
        $connection->executeStatement('DELETE FROM screenshot WHERE game_id IN (SELECT id FROM game WHERE total_rating_count < 50 OR total_rating_count IS NULL)');
        $connection->executeStatement('DELETE FROM user_wallpaper WHERE wallpaper_id IN (SELECT id FROM wallpaper WHERE game_id IN (SELECT id FROM game WHERE total_rating_count < 50 OR total_rating_count IS NULL))');
        $connection->executeStatement('DELETE FROM wallpaper WHERE game_id IN (SELECT id FROM game WHERE total_rating_count < 50 OR total_rating_count IS NULL)');
        
        // Puis supprime les jeux
        $deletedCount = $connection->executeStatement(
            'DELETE FROM game WHERE total_rating_count < 50 OR total_rating_count IS NULL'
        );

        $io->success("✅ {$deletedCount} jeux de faible qualité supprimés");

        // Compte les jeux après nettoyage
        $totalAfter = $this->gameRepository->count([]);
        $io->text("📊 Total des jeux après nettoyage : {$totalAfter}");

        // Affiche les meilleurs jeux récents
        $io->section('🎮 Meilleurs jeux récents (2024-2025)');
        $recentGames = $this->gameRepository->createQueryBuilder('g')
            ->where('g.releaseDate >= :oneYearAgo')
            ->andWhere('g.totalRating IS NOT NULL')
            ->andWhere('g.totalRating >= 80')
            ->andWhere('g.totalRatingCount >= 50')
            ->setParameter('oneYearAgo', new \DateTimeImmutable('-365 days'))
            ->orderBy('g.releaseDate', 'DESC')
            ->addOrderBy('g.totalRating', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        foreach ($recentGames as $game) {
            $rating = $game->getTotalRating() ? number_format($game->getTotalRating(), 1) : 'N/A';
            $votes = $game->getTotalRatingCount() ?? 0;
            $releaseDate = $game->getReleaseDate() ? $game->getReleaseDate()->format('Y-m-d') : 'N/A';
            
            $io->text("🎯 {$game->getTitle()} | Note: {$rating}/10 | Votes: {$votes} | Sortie: {$releaseDate}");
        }

        $io->success('🎉 Nettoyage terminé ! Le HeroBanner affichera maintenant les meilleurs jeux récents.');

        return Command::SUCCESS;
    }
} 