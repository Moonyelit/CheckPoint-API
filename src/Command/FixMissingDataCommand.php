<?php

namespace App\Command;

use App\Entity\Game;
use App\Service\IgdbClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 🔧 COMMANDE DE CORRECTION - DONNÉES MANQUANTES
 * 
 * Cette commande met à jour les notes et votes manquants pour les jeux spécifiques
 * qui doivent apparaître dans le HeroBanner.
 * 
 * 🎯 OBJECTIF :
 * - Mettre à jour les notes et votes des jeux prioritaires
 * - S'assurer que les jeux récents apparaissent dans le HeroBanner
 * - Corriger les données manquantes d'IGDB
 * 
 * ⚡ UTILISATION :
 * php bin/console app:fix-missing-data
 */

#[AsCommand(
    name: 'app:fix-missing-data',
    description: 'Corrige les données manquantes des jeux depuis l\'API IGDB'
)]
class FixMissingDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IgdbClient $igdbClient
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔧 Correction des données manquantes');
        $io->newLine();

        // Récupérer tous les jeux qui n'ont pas de note ou de votes
        $games = $this->entityManager->getRepository(Game::class)->createQueryBuilder('g')
            ->where('g.totalRating IS NULL OR g.totalRatingCount IS NULL OR g.category IS NULL')
            ->getQuery()
            ->getResult();

        if (empty($games)) {
            $io->success('✅ Tous les jeux ont déjà leurs données complètes !');
            return Command::SUCCESS;
        }

        $io->text(sprintf('🎯 Récupération des données pour %d jeux...', count($games)));
        $io->newLine();

        $updatedCount = 0;
        $errors = [];

        foreach ($games as $game) {
            try {
                $io->text(sprintf('🔄 Mise à jour : %s', $game->getTitle()));
                
                // Récupérer les détails du jeu depuis IGDB
                $gameDetails = $this->igdbClient->getGameDetails($game->getIgdbId());
                
                if ($gameDetails) {
                    $updated = false;
                    
                    // Mettre à jour la note si elle est manquante
                    if ($game->getTotalRating() === null && isset($gameDetails['total_rating'])) {
                        $game->setTotalRating($gameDetails['total_rating']);
                        $updated = true;
                    }
                    
                    // Mettre à jour le nombre de votes si il est manquant
                    if ($game->getTotalRatingCount() === null && isset($gameDetails['total_rating_count'])) {
                        $game->setTotalRatingCount($gameDetails['total_rating_count']);
                        $updated = true;
                    }
                    
                    // Mettre à jour la catégorie si elle est manquante
                    if ($game->getCategory() === null && isset($gameDetails['category'])) {
                        $game->setCategory($gameDetails['category']);
                        $updated = true;
                    }
                    
                    if ($updated) {
                        $this->entityManager->persist($game);
                        $updatedCount++;
                        
                        $io->text(sprintf('   ✅ Note: %s/10 | Votes: %s | Catégorie: %s', 
                            $game->getTotalRating() ?? 'N/A',
                            $game->getTotalRatingCount() ?? 'N/A',
                            $game->getCategory() ?? 'N/A'
                        ));
                    } else {
                        $io->text('   ⚠️ Aucune donnée mise à jour');
                    }
                } else {
                    $errors[] = sprintf('❌ Impossible de récupérer les données pour : %s', $game->getTitle());
                    $io->text('   ❌ Données non disponibles');
                }
                
            } catch (\Exception $e) {
                $errors[] = sprintf('❌ Erreur pour %s : %s', $game->getTitle(), $e->getMessage());
                $io->text(sprintf('   ❌ Erreur : %s', $e->getMessage()));
            }
            
            $io->newLine();
        }

        // Sauvegarder les modifications
        $this->entityManager->flush();

        $io->success(sprintf('✅ %d jeux mis à jour avec succès !', $updatedCount));

        if (!empty($errors)) {
            $io->warning('⚠️ Erreurs rencontrées :');
            foreach ($errors as $error) {
                $io->text($error);
            }
        }

        // Afficher le top 5 après correction
        $io->newLine();
        $io->text('🎮 Meilleurs jeux récents après correction');
        $io->text('------------------------------------------');
        
        $topGames = $this->entityManager->getRepository(Game::class)->createQueryBuilder('g')
            ->where('g.totalRating >= 80')
            ->andWhere('g.totalRatingCount >= 80')
            ->andWhere('g.releaseDate >= :oneYearAgo')
            ->setParameter('oneYearAgo', new \DateTime('-365 days'))
            ->orderBy('g.totalRating', 'DESC')
            ->addOrderBy('g.totalRatingCount', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        foreach ($topGames as $game) {
            $io->text(sprintf(' 🎯 %s | Note: %.1f/10 | Votes: %s | Sortie: %s', 
                $game->getTitle(),
                $game->getTotalRating(),
                $game->getTotalRatingCount(),
                $game->getReleaseDate()->format('Y-m-d')
            ));
        }

        $io->newLine();
        $io->success('🎉 Correction terminée ! Le HeroBanner affichera maintenant les bons jeux.');

        return Command::SUCCESS;
    }
} 