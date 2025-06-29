<?php

namespace App\Command;

use App\Repository\GameRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 🔍 COMMANDE DE DÉBOGAGE - HERO BANNER
 * 
 * Cette commande affiche exactement quels jeux sont retournés par l'API
 * du HeroBanner pour vérifier le tri et la sélection.
 * 
 * 🎯 OBJECTIF :
 * - Voir quels jeux sont affichés dans le HeroBanner
 * - Vérifier le tri par note et votes
 * - Déboguer les critères de sélection
 * 
 * ⚡ UTILISATION :
 * php bin/console app:debug-images
 */

#[AsCommand(
    name: 'app:debug-images',
    description: 'Débogue les jeux affichés dans le HeroBanner',
)]
class DebugImagesCommand extends Command
{
    public function __construct(
        private GameRepository $gameRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔍 Débogage du HeroBanner');
        $io->text('🎯 Vérification des jeux affichés dans le carousel');

        // Récupère les jeux exactement comme l'API du HeroBanner
        $recentGames = $this->gameRepository->createQueryBuilder('g')
            ->where('g.releaseDate >= :oneYearAgo')
            ->andWhere('g.totalRating IS NOT NULL')
            ->andWhere('g.totalRating >= 80')
            ->andWhere('g.totalRatingCount >= 50')
            ->setParameter('oneYearAgo', new \DateTimeImmutable('-365 days'))
            ->orderBy('g.totalRating', 'DESC')
            ->addOrderBy('g.totalRatingCount', 'DESC')
            ->addOrderBy('g.releaseDate', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $io->section('🎮 Jeux retournés par l\'API HeroBanner (triés par note)');
        
        foreach ($recentGames as $index => $game) {
            $rating = $game->getTotalRating() ? number_format($game->getTotalRating(), 1) : 'N/A';
            $votes = $game->getTotalRatingCount() ?? 0;
            $releaseDate = $game->getReleaseDate() ? $game->getReleaseDate()->format('Y-m-d') : 'N/A';
            $position = $index + 1;
            
            $io->text("{$position}. 🎯 {$game->getTitle()}");
            $io->text("    Note: {$rating}/10 | Votes: {$votes} | Sortie: {$releaseDate}");
            $io->text("");
        }

        // Affiche les 5 premiers (ceux qui apparaissent dans le HeroBanner)
        $io->section('🎯 TOP 5 - Jeux affichés dans le HeroBanner');
        $top5 = array_slice($recentGames, 0, 5);
        
        foreach ($top5 as $index => $game) {
            $rating = $game->getTotalRating() ? number_format($game->getTotalRating(), 1) : 'N/A';
            $votes = $game->getTotalRatingCount() ?? 0;
            $releaseDate = $game->getReleaseDate() ? $game->getReleaseDate()->format('Y-m-d') : 'N/A';
            $position = $index + 1;
            
            $io->text("{$position}. {$game->getTitle()} | {$rating}/10 | {$votes} votes | {$releaseDate}");
        }

        $io->success('🎉 Débogage terminé ! Vérifiez que les bons jeux apparaissent en premier.');

        return Command::SUCCESS;
    }
} 