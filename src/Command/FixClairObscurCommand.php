<?php

namespace App\Command;

use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-clair-obscur',
    description: 'Vérifie et corrige les données de Clair Obscur: Expedition 33 – Deluxe Edition',
)]
class FixClairObscurCommand extends Command
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

        $io->title('🔧 Correction des données Clair Obscur: Expedition 33');

        // Recherche les jeux Clair Obscur
        $clairObscurGames = $this->gameRepository->createQueryBuilder('g')
            ->where('g.title LIKE :title')
            ->setParameter('title', '%Clair Obscur%')
            ->getQuery()
            ->getResult();

        if (empty($clairObscurGames)) {
            $io->warning('❌ Aucun jeu Clair Obscur trouvé');
            return Command::FAILURE;
        }

        $io->section('📊 Jeux Clair Obscur trouvés :');
        foreach ($clairObscurGames as $game) {
            $rating = $game->getTotalRating() ? number_format($game->getTotalRating(), 1) : 'N/A';
            $votes = $game->getTotalRatingCount() ?? 0;
            $releaseDate = $game->getReleaseDate() ? $game->getReleaseDate()->format('Y-m-d') : 'N/A';
            
            $io->text("🎮 {$game->getTitle()}");
            $io->text("    Note: {$rating}/10 | Votes: {$votes} | Sortie: {$releaseDate}");
            $io->text("");
        }

        // Vérifier si la Deluxe Edition a des données incorrectes
        $deluxeEdition = null;
        foreach ($clairObscurGames as $game) {
            if (strpos($game->getTitle(), 'Deluxe Edition') !== false) {
                $deluxeEdition = $game;
                break;
            }
        }

        if ($deluxeEdition) {
            $io->section('🔍 Analyse de la Deluxe Edition');
            $io->text("Titre: {$deluxeEdition->getTitle()}");
            $io->text("Note: " . ($deluxeEdition->getTotalRating() ?? 'N/A'));
            $io->text("Votes: " . ($deluxeEdition->getTotalRatingCount() ?? 'N/A'));
            $io->text("IGDB ID: " . ($deluxeEdition->getIgdbId() ?? 'N/A'));

            // Si la Deluxe Edition a des données, on les supprime
            if ($deluxeEdition->getTotalRating() !== null || $deluxeEdition->getTotalRatingCount() !== null) {
                $io->warning('⚠️ La Deluxe Edition a des données de rating, ce qui est incorrect !');
                
                if ($io->confirm('Voulez-vous supprimer les données de rating de la Deluxe Edition ?')) {
                    $deluxeEdition->setTotalRating(null);
                    $deluxeEdition->setTotalRatingCount(null);
                    $deluxeEdition->setUpdatedAt(new \DateTimeImmutable());
                    
                    $this->entityManager->persist($deluxeEdition);
                    $this->entityManager->flush();
                    
                    $io->success('✅ Données de rating supprimées pour la Deluxe Edition');
                }
            } else {
                $io->info('✅ La Deluxe Edition n\'a pas de données de rating (correct)');
            }
        }

        // Vérifier le top 5 après correction
        $io->section('🎯 Vérification du top 5 après correction');
        $top5Games = $this->gameRepository->findTopYearGamesDeduplicated(5, 80, 80);
        
        foreach ($top5Games as $index => $game) {
            $rating = $game->getTotalRating() ? number_format($game->getTotalRating(), 1) : 'N/A';
            $votes = $game->getTotalRatingCount() ?? 0;
            $position = $index + 1;
            
            $io->text("{$position}. {$game->getTitle()} | {$rating}/10 | {$votes} votes");
        }

        $io->success('✅ Vérification terminée !');

        return Command::SUCCESS;
    }
} 