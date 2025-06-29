<?php

namespace App\Command;

use App\Repository\GameRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:show-top5-games',
    description: 'Affiche le top 5 des jeux des 365 derniers jours avec plus de 80 votes',
)]
class ShowTop5GamesCommand extends Command
{
    public function __construct(
        private GameRepository $gameRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🎮 TOP 5 des jeux des 365 derniers jours');
        $io->text('Critères : Note ≥ 80/100, Votes ≥ 80, Sortis dans les 365 derniers jours');

        try {
            // Récupère les jeux des 365 derniers jours avec critères stricts
            $games = $this->gameRepository->findTopYearGamesWithCriteria(5, 80, 80);

            if (empty($games)) {
                $io->warning('❌ Aucun jeu trouvé avec ces critères');
                $io->text('💡 Essayez de relancer l\'import : php bin/console app:import-top-year-games');
                return Command::FAILURE;
            }

            $io->section('🏆 TOP 5 - Jeux affichés dans le HeroBanner');

            foreach ($games as $index => $game) {
                $rating = $game->getTotalRating() ? number_format($game->getTotalRating(), 1) : 'N/A';
                $votes = $game->getTotalRatingCount() ?? 0;
                $releaseDate = $game->getReleaseDate() ? $game->getReleaseDate()->format('Y-m-d') : 'N/A';
                $position = $index + 1;
                
                $io->text("{$position}. 🎯 {$game->getTitle()}");
                $io->text("    Note: {$rating}/10 | Votes: {$votes} | Sortie: {$releaseDate}");
                
                if ($game->getGenres()) {
                    $genres = implode(', ', $game->getGenres());
                    $io->text("    Genres: {$genres}");
                }
                
                if ($game->getPlatforms()) {
                    $platforms = implode(', ', $game->getPlatforms());
                    $io->text("    Plateformes: {$platforms}");
                }
                
                $io->text("");
            }

            // Statistiques
            $io->section('📊 Statistiques');
            $totalGames = $this->gameRepository->count([]);
            $recentGamesCount = $this->gameRepository->createQueryBuilder('g')
                ->select('COUNT(g.id)')
                ->where('g.releaseDate >= :oneYearAgo')
                ->andWhere('g.totalRating IS NOT NULL')
                ->andWhere('g.totalRating >= 80')
                ->andWhere('g.totalRatingCount >= 80')
                ->setParameter('oneYearAgo', new \DateTimeImmutable('-365 days'))
                ->getQuery()
                ->getSingleScalarResult();

            $io->table(
                ['Métrique', 'Valeur'],
                [
                    ['Total jeux en base', $totalGames],
                    ['Jeux récents (365j) avec critères', $recentGamesCount],
                    ['Jeux affichés dans HeroBanner', count($games)]
                ]
            );

            $io->success('✅ Top 5 affiché avec succès !');
            $io->text('💡 Ces jeux sont récupérés via l\'endpoint /api/custom/games/year/top100');
            $io->text('🔄 Pour mettre à jour : php bin/console app:import-top-year-games');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("❌ Erreur lors de l'affichage : " . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 