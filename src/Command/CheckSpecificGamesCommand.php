<?php

namespace App\Command;

use App\Repository\GameRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-specific-games',
    description: 'Vérifie les jeux spécifiques comme Astro Bot et Split Fiction',
)]
class CheckSpecificGamesCommand extends Command
{
    public function __construct(
        private GameRepository $gameRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔍 Vérification des jeux spécifiques');
        $io->text('Recherche d\'Astro Bot et Split Fiction dans la base de données');

        // Jeux à vérifier
        $gamesToCheck = [
            'Astro Bot',
            'Split Fiction',
            'Astro Bot Rescue Mission',
            'Astro\'s Playroom'
        ];

        foreach ($gamesToCheck as $gameTitle) {
            $io->section("🔎 Recherche : {$gameTitle}");
            
            $games = $this->gameRepository->createQueryBuilder('g')
                ->where('g.title LIKE :title')
                ->setParameter('title', '%' . $gameTitle . '%')
                ->getQuery()
                ->getResult();

            if (empty($games)) {
                $io->text("❌ Aucun jeu trouvé avec le titre : {$gameTitle}");
            } else {
                foreach ($games as $game) {
                    $rating = $game->getTotalRating() ? number_format($game->getTotalRating(), 1) : 'N/A';
                    $votes = $game->getTotalRatingCount() ?? 0;
                    $releaseDate = $game->getReleaseDate() ? $game->getReleaseDate()->format('Y-m-d') : 'N/A';
                    
                    $io->text("✅ Trouvé : {$game->getTitle()}");
                    $io->text("    Note: {$rating}/10 | Votes: {$votes} | Sortie: {$releaseDate}");
                    
                    // Vérifier si le jeu respecte les critères du HeroBanner
                    $meetsCriteria = $game->getTotalRating() >= 80 && $game->getTotalRatingCount() >= 80;
                    $isRecent = $game->getReleaseDate() && $game->getReleaseDate() >= new \DateTimeImmutable('-365 days');
                    
                    if ($meetsCriteria && $isRecent) {
                        $io->text("    🎯 RESPECTE les critères du HeroBanner");
                    } else {
                        $io->text("    ❌ NE RESPECTE PAS les critères du HeroBanner");
                        if (!$meetsCriteria) {
                            $io->text("       - Note < 80 ou Votes < 80");
                        }
                        if (!$isRecent) {
                            $io->text("       - Sorti il y a plus de 365 jours");
                        }
                    }
                    $io->text("");
                }
            }
        }

        // Vérifier tous les jeux récents avec de bonnes notes
        $io->section('📊 Tous les jeux récents avec note ≥ 9.0/10');
        
        $recentHighRatedGames = $this->gameRepository->createQueryBuilder('g')
            ->where('g.releaseDate >= :oneYearAgo')
            ->andWhere('g.totalRating IS NOT NULL')
            ->andWhere('g.totalRating >= 90')
            ->andWhere('g.totalRatingCount >= 50')
            ->setParameter('oneYearAgo', new \DateTimeImmutable('-365 days'))
            ->orderBy('g.totalRating', 'DESC')
            ->addOrderBy('g.totalRatingCount', 'DESC')
            ->getQuery()
            ->getResult();

        if (empty($recentHighRatedGames)) {
            $io->text("❌ Aucun jeu récent avec une note ≥ 9.0/10 trouvé");
        } else {
            foreach ($recentHighRatedGames as $game) {
                $rating = number_format($game->getTotalRating(), 1);
                $votes = $game->getTotalRatingCount() ?? 0;
                $releaseDate = $game->getReleaseDate() ? $game->getReleaseDate()->format('Y-m-d') : 'N/A';
                
                $io->text("🎮 {$game->getTitle()} | {$rating}/10 | {$votes} votes | {$releaseDate}");
            }
        }

        $io->success('✅ Vérification terminée !');
        $io->text('💡 Si les jeux ne sont pas trouvés, ils ne sont pas dans la base de données');
        $io->text('🔄 Pour les ajouter : php bin/console app:import-top-year-games');

        return Command::SUCCESS;
    }
} 