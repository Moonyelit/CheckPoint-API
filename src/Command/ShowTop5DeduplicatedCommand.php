<?php

namespace App\Command;

use App\Repository\GameRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 🎮 COMMANDE DE DIAGNOSTIC - TOP 5 DÉDUPLIQUÉ
 * 
 * Cette commande affiche le top 5 des jeux récents (365 derniers jours)
 * avec déduplication pour éviter les variantes (Deluxe Edition, etc.)
 * 
 * 📊 CRITÈRES :
 * - Jeux sortis dans les 365 derniers jours
 * - Note ≥ 80/100 et Votes ≥ 80
 * - Déduplication par nom principal
 * - Catégorie = 0 (jeux principaux uniquement)
 * 
 * 🎯 OBJECTIF :
 * Vérifier que les jeux du HeroBanner sont bien présents
 * et ont les bonnes notes/votes
 */

#[AsCommand(
    name: 'app:show-top5-deduplicated',
    description: 'Affiche le top 5 dédupliqué des jeux récents',
)]
class ShowTop5DeduplicatedCommand extends Command
{
    public function __construct(
        private GameRepository $gameRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🎮 TOP 5 DÉDUPLIQUÉ des jeux des 365 derniers jours');
        $io->text('✨ DÉDUPLIQUÉ : Un seul jeu par nom principal (évite les "Deluxe Edition", etc.)');

        // Récupère les jeux récents avec critères
        $oneYearAgo = new \DateTimeImmutable('-365 days');
        
        $qb = $this->gameRepository->createQueryBuilder('g')
            ->where('g.releaseDate >= :oneYearAgo')
            ->andWhere('g.totalRating >= 80')
            ->andWhere('g.totalRatingCount >= 80')
            ->andWhere('(g.category = 0 OR g.category IS NULL)')
            ->setParameter('oneYearAgo', $oneYearAgo)
            ->orderBy('g.totalRating', 'DESC')
            ->addOrderBy('g.totalRatingCount', 'DESC');

        $games = $qb->getQuery()->getResult();

        if (empty($games)) {
            $io->warning('Aucun jeu récent trouvé avec les critères demandés');
            return Command::SUCCESS;
        }

        // Déduplication par nom principal
        $deduplicatedGames = $this->deduplicateGames($games);

        $io->section('🏆 TOP 5 DÉDUPLIQUÉ - Jeux affichés dans le HeroBanner');

        $top5 = array_slice($deduplicatedGames, 0, 5);
        foreach ($top5 as $index => $game) {
            $rating = $game->getTotalRating() ? number_format($game->getTotalRating(), 1) : 'N/A';
            $votes = $game->getTotalRatingCount() ?: 0;
            $releaseDate = $game->getReleaseDate() ? $game->getReleaseDate()->format('Y-m-d') : 'N/A';
            $genres = $game->getGenres() ? implode(', ', $game->getGenres()) : 'N/A';
            $platforms = $game->getPlatforms() ? implode(', ', $game->getPlatforms()) : 'N/A';

            $io->text(sprintf(
                ' %d. 🎯 %s',
                $index + 1,
                $game->getTitle()
            ));
            $io->text(sprintf(
                '     Note: %s/10 | Votes: %d | Sortie: %s',
                $rating,
                $votes,
                $releaseDate
            ));
            $io->text(sprintf(
                '     Genres: %s',
                $genres
            ));
            $io->text(sprintf(
                '     Plateformes: %s',
                $platforms
            ));
            $io->newLine();
        }

        // Statistiques
        $totalGames = $this->gameRepository->count([]);
        $recentGamesCount = count($games);
        $deduplicatedCount = count($deduplicatedGames);

        $io->section('📈 Statistiques');
        $io->table(
            ['Métrique', 'Valeur'],
            [
                ['Total jeux en base', $totalGames],
                ['Jeux récents (365j) avec critères', $recentGamesCount],
                ['Jeux affichés (dédupliqués)', $deduplicatedCount],
                ['Jeux affichés (avec doublons)', count($games)]
            ]
        );

        $io->success('✅ Top 5 dédupliqué affiché avec succès !');
        $io->text('💡 Ces jeux sont récupérés via l\'endpoint /api/custom/games/year/top100');
        $io->text('🔄 Pour mettre à jour : php bin/console app:import-top-year-games');
        $io->text('✨ Maintenant : Un seul jeu par nom principal !');

        return Command::SUCCESS;
    }

    private function deduplicateGames(array $games): array
    {
        $deduplicated = [];
        $processedTitles = [];

        foreach ($games as $game) {
            $title = $game->getTitle();
            
            // Nettoyage du titre pour la déduplication
            $cleanTitle = $this->cleanTitleForDeduplication($title);
            
            if (!isset($processedTitles[$cleanTitle])) {
                $deduplicated[] = $game;
                $processedTitles[$cleanTitle] = true;
            }
        }

        return $deduplicated;
    }

    private function cleanTitleForDeduplication(string $title): string
    {
        // Supprime les suffixes courants pour la déduplication
        $suffixes = [
            '/\s*-\s*Deluxe Edition$/i',
            '/\s*-\s*Collector\'s Edition$/i',
            '/\s*-\s*Special Edition$/i',
            '/\s*-\s*Ultimate Edition$/i',
            '/\s*-\s*Complete Edition$/i',
            '/\s*-\s*Definitive Edition$/i',
            '/\s*-\s*Remastered$/i',
            '/\s*-\s*Remake$/i',
            '/\s*:\s*Deluxe Edition$/i',
            '/\s*:\s*Collector\'s Edition$/i',
            '/\s*:\s*Special Edition$/i',
            '/\s*:\s*Ultimate Edition$/i',
            '/\s*:\s*Complete Edition$/i',
            '/\s*:\s*Definitive Edition$/i',
            '/\s*:\s*Remastered$/i',
            '/\s*:\s*Remake$/i',
            '/\s*\(.*?\)\s*$/i', // Supprime les parenthèses à la fin
        ];

        $cleanTitle = $title;
        foreach ($suffixes as $pattern) {
            $cleanTitle = preg_replace($pattern, '', $cleanTitle);
        }

        return trim($cleanTitle);
    }
} 