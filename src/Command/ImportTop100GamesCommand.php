<?php

namespace App\Command;

use App\Service\GameImporter;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Cocur\Slugify\Slugify;

/**
 * 🏆 COMMANDE D'IMPORT - TOP 100 JEUX DE TOUS LES TEMPS
 * 
 * Cette commande récupère les 100 meilleurs jeux de tous les temps depuis l'API IGDB
 * avec des critères de qualité stricts pour garantir que seuls les vrais AAA et 
 * hits populaires soient importés.
 * 
 * 📊 CRITÈRES DE SÉLECTION :
 * - Jeux 2024-2025 : Note ≥85, Votes ≥100
 * - Jeux 2018+ : Note ≥88, Votes ≥200  
 * - Classiques : Note ≥90, Votes ≥500
 * - Tri : Par note décroissante, puis par nombre de votes
 * - Limite : 100 jeux maximum
 * 
 * 🧹 NETTOYAGE AUTOMATIQUE :
 * - Suppression des jeux avec <100 votes après import
 * - Nettoyage automatique des slugs (suppression des IDs IGDB)
 * - Évite les jeux comme "Pixadom" qui polluent le classement
 * 
 * 🎯 OBJECTIF :
 * Alimenter l'endpoint API Platform /api/games avec des jeux de qualité
 * 
 * ⚡ UTILISATION :
 * php bin/console app:import-top100-games
 * 
 * 💡 FRÉQUENCE RECOMMANDÉE :
 * Une fois par semaine (les classiques changent peu)
 */

// Pour récupérer les jeux du Top 100 d'IGDB, 
// faire dans le terminal dans le dossier CheckPoint-API : 
// php bin/console app:import-top100-games

#[AsCommand(
    name: 'app:import-top100-games',
    description: 'Importe les 100 meilleurs jeux de tous les temps depuis IGDB avec nettoyage automatique',
)]
class ImportTop100GamesCommand extends Command
{
    public function __construct(
        private GameImporter $importer,
        private GameRepository $gameRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('🏆 Import du Top 100 de tous les temps');
        $io->text('Critères : Note ≥ 7.5/10 (75/100), Votes ≥ 80');
        $io->text('📊 Champs récupérés : total_rating, total_rating_count, category, follows, last_popularity_update');

        // Import des jeux
        $this->importer->importTop100Games(80, 75);

        $io->success('✅ Import du Top 100 terminé !');
        
        // Nettoyage automatique des slugs
        $io->section('🧹 Nettoyage automatique des slugs');
        $this->cleanGameSlugs($io);
        
        // Nettoyage automatique des jeux de faible qualité
        $io->section('🧹 Nettoyage automatique des jeux de faible qualité');
        
        $connection = $this->entityManager->getConnection();
        
        // Compte les jeux à supprimer (moins de 100 votes)
        $lowQualityCount = $connection->executeQuery(
            'SELECT COUNT(*) FROM game WHERE total_rating_count < 100 OR total_rating_count IS NULL'
        )->fetchOne();
        
        if ($lowQualityCount > 0) {
            $io->text("Suppression de $lowQualityCount jeux avec moins de 100 votes...");
            
            // Supprime d'abord les entités liées
            $connection->executeStatement('DELETE FROM user_wallpaper WHERE wallpaper_id IN (SELECT id FROM wallpaper WHERE game_id IN (SELECT id FROM game WHERE total_rating_count < 100 OR total_rating_count IS NULL))');
            $connection->executeStatement('DELETE FROM screenshot WHERE game_id IN (SELECT id FROM game WHERE total_rating_count < 100 OR total_rating_count IS NULL)');
            $connection->executeStatement('DELETE FROM wallpaper WHERE game_id IN (SELECT id FROM game WHERE total_rating_count < 100 OR total_rating_count IS NULL)');
            
            // Supprime les jeux
            $deleted = $connection->executeStatement('DELETE FROM game WHERE total_rating_count < 100 OR total_rating_count IS NULL');
            
            $io->success("✅ $deleted jeux de faible qualité supprimés !");
        } else {
            $io->info('Aucun jeu de faible qualité à supprimer');
        }
        
        // Statistiques finales
        $totalGames = $this->gameRepository->count([]);
        $highQualityGames = $this->gameRepository->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.totalRatingCount >= :minVotes')
            ->setParameter('minVotes', 200)
            ->getQuery()
            ->getSingleScalarResult();
            
        $io->table(
            ['Statut', 'Nombre'],
            [
                ['Jeux au total', $totalGames],
                ['Jeux haute qualité (≥200 votes)', $highQualityGames],
                ['Jeux supprimés', $deleted ?? 0]
            ]
        );

        $io->text('💡 Ces jeux alimentent l\'endpoint API Platform /api/games');
        $io->text('🔄 Les slugs sont maintenant propres et uniques !');

        return Command::SUCCESS;
    }

    /**
     * Nettoie automatiquement les slugs des jeux
     */
    private function cleanGameSlugs(SymfonyStyle $io): void
    {
        $io->text('🧹 Nettoyage automatique des slugs (suppression des IDs IGDB)...');
        $games = $this->gameRepository->findAll();
        $updatedCount = 0;
        $slugify = new Slugify();
        $slugMap = [];

        foreach ($games as $game) {
            $oldSlug = $game->getSlug();
            $title = $game->getTitle();

            // Vérifier si le slug contient un ID IGDB (se termine par -nombre)
            if (preg_match('/^(.+)-\d+$/', $oldSlug, $matches)) {
                $baseSlug = $matches[1];
                
                // Générer un nouveau slug basé sur le titre original pour préserver la logique
                $newBaseSlug = $this->generateSlugFromTitle($title);
                $newSlug = $this->generateUniqueSlugWithMap($newBaseSlug, $game->getId(), $slugMap);

                if ($newSlug !== $oldSlug) {
                    $game->setSlug($newSlug);
                    $this->entityManager->persist($game);
                    $updatedCount++;
                    $io->text(sprintf('✅ %s : %s → %s', $title, $oldSlug, $newSlug));
                }
            } else {
                // Marquer les slugs déjà propres dans la map
                $slugMap[$oldSlug] = $game->getId();
            }
        }

        if ($updatedCount > 0) {
            $this->entityManager->flush();
            $io->success(sprintf('✅ %d slugs nettoyés automatiquement !', $updatedCount));
        } else {
            $io->info('Tous les slugs sont déjà propres !');
        }
    }

    /**
     * Génère un slug basé sur le titre original pour préserver la logique
     */
    private function generateSlugFromTitle(string $title): string
    {
        $slugify = new Slugify();
        return $slugify->slugify($title);
    }

    /**
     * Génère un slug unique sans inclure l'ID IGDB en utilisant une map des slugs existants
     */
    private function generateUniqueSlugWithMap(string $baseSlug, ?int $existingId, array &$slugMap): string
    {
        $slug = $baseSlug;
        $counter = 2;
        // Vérifier si le slug existe déjà dans notre map ou en base
        while (true) {
            $existingGame = $this->gameRepository->findOneBy(['slug' => $slug]);
            $inMap = isset($slugMap[$slug]);
            // Si aucun jeu avec ce slug, ou si c'est le même jeu (mise à jour)
            if ((!$existingGame && !$inMap) || ($existingId && $existingGame && $existingGame->getId() === $existingId)) {
                break;
            }
            $slug = $baseSlug . '-' . $counter;
            $counter++;
            if ($counter > 100) {
                $slug = $baseSlug . '-' . time();
                break;
            }
        }
        $slugMap[$slug] = $existingId ?? true;
        return $slug;
    }
} 