<?php

namespace App\Command;

use App\Service\IgdbClient;
use App\Repository\GameRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 🐛 COMMANDE DE DEBUG - ANALYSE APPROFONDIE DES IMAGES
 * 
 * Cette commande fournit un diagnostic complet de l'état des images dans la base
 * de données sans faire de modifications. Parfaite pour le debug et l'analyse.
 * 
 * 🔍 ANALYSES EFFECTUÉES :
 * 
 * 📊 STATISTIQUES GLOBALES :
 * - Comptage total des jeux en base
 * - Répartition jeux avec/sans images
 * - Pourcentage de couverture images
 * 
 * 🚨 DÉTECTION PROBLÈMES :
 * - Liste des jeux sans images (avec ID IGDB)
 * - Identification des URL manquantes ou vides
 * - Preview des jeux problématiques
 * 
 * 🏆 TEST ENDPOINTS CRITIQUES :
 * - Top 5 jeux populaires (pour homepage/carousel)
 * - Top 5 jeux récents (2 dernières années)
 * - Validation URLs avant/après amélioration
 * - Test qualité images pour affichage frontend
 * 
 * 🎯 OBJECTIFS :
 * - Diagnostic complet sans modifications
 * - Validation de la cohérence des données
 * - Preview de l'impact d'améliorations potentielles
 * - Test de la qualité des endpoints publics
 * 
 * ⚡ UTILISATION :
 * php bin/console app:debug-images
 * 
 * 🔄 WORKFLOW D'ANALYSE :
 * 1. Statistiques générales de la base
 * 2. Identification des jeux sans images
 * 3. Test endpoint jeux populaires
 * 4. Test endpoint jeux récents
 * 5. Simulation d'amélioration qualité
 * 6. Rapport final sans modifications
 * 
 * 💡 AVANTAGES :
 * - Analyse non-destructive (read-only)
 * - Vision globale de la qualité des images
 * - Preview avant correction massive
 * - Validation endpoints utilisés par frontend
 * 
 * 🔧 UTILISATION RECOMMANDÉE :
 * - Avant/après corrections d'images
 * - Debug problèmes affichage frontend
 * - Validation qualité après imports
 * - Analyse périodique de l'état des données
 * 
 * 📈 COMPLÉMENTAIRE AVEC :
 * - app:fix-images (pour les corrections)
 * - app:update-existing-images (pour les améliorations)
 * - Endpoints publics (validation cohérence)
 */

#[AsCommand(
    name: 'app:debug-images',
    description: 'Debug et analyse complète des images sans modifications (diagnostic read-only)',
)]
class DebugImagesCommand extends Command
{
    private GameRepository $gameRepository;
    private IgdbClient $igdbClient;

    public function __construct(GameRepository $gameRepository, IgdbClient $igdbClient)
    {
        parent::__construct();
        $this->gameRepository = $gameRepository;
        $this->igdbClient = $igdbClient;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('🐛 Analyse des images des jeux (mode debug)');
        $io->info('Diagnostic complet read-only - Aucune modification ne sera effectuée');

        // 📊 STATISTIQUES GLOBALES : Vue d'ensemble de la base
        $totalGames = $this->gameRepository->count([]);
        $gamesWithImages = $this->gameRepository->createQueryBuilder('g')
            ->select('COUNT(g)')
            ->where('g.coverUrl IS NOT NULL')
            ->andWhere('g.coverUrl != :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();

        $coveragePercent = $totalGames > 0 ? round(($gamesWithImages / $totalGames) * 100, 1) : 0;

        $io->success("📊 Total de jeux : $totalGames");
        $io->success("🖼️ Jeux avec images : $gamesWithImages ($coveragePercent%)");
        $io->warning("⚠️ Jeux sans images : " . ($totalGames - $gamesWithImages));

        // 🚨 ANALYSE PROBLÈMES : Affichage des jeux sans images
        $gamesWithoutImages = $this->gameRepository->createQueryBuilder('g')
            ->where('g.coverUrl IS NULL OR g.coverUrl = :empty')
            ->setParameter('empty', '')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        if (!empty($gamesWithoutImages)) {
            $io->section('🚨 Jeux sans images (sample des 10 premiers) :');
            foreach ($gamesWithoutImages as $game) {
                $io->text("- {$game->getTitle()} (ID IGDB: {$game->getIgdbId()})");
            }
        }

        // 🏆 TEST ENDPOINT POPULAIRES : Validation pour homepage/carousel
        $io->section('🏆 Test des jeux populaires pour le frontend :');
        $popularGames = $this->gameRepository->createQueryBuilder('g')
            ->where('g.totalRating IS NOT NULL')
            ->andWhere('g.coverUrl IS NOT NULL')
            ->orderBy('g.totalRating', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($popularGames as $game) {
            $originalUrl = $game->getCoverUrl();
            $improvedUrl = $this->igdbClient->improveImageQuality($originalUrl, 't_cover_big');
            
            $io->text("🎮 {$game->getTitle()}");
            $io->text("   URL originale: $originalUrl");
            $io->text("   URL améliorée: $improvedUrl");
            $io->text("   Note: {$game->getTotalRating()}");
            $io->newLine();
        }

        // 🔥 TEST ENDPOINT RÉCENTS : Validation jeux récents
        $io->section('🔥 Test des jeux récents (2 dernières années) :');
        $twoYearsAgo = new \DateTimeImmutable('-2 years');
        
        $recentGames = $this->gameRepository->createQueryBuilder('g')
            ->where('g.releaseDate >= :twoYearsAgo')
            ->andWhere('g.totalRating >= :minRating')
            ->andWhere('g.coverUrl IS NOT NULL')
            ->setParameter('twoYearsAgo', $twoYearsAgo)
            ->setParameter('minRating', 70)
            ->orderBy('g.totalRating', 'DESC')
            ->addOrderBy('g.releaseDate', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($recentGames as $game) {
            $originalUrl = $game->getCoverUrl();
            $improvedUrl = $this->igdbClient->improveImageQuality($originalUrl, 't_cover_big');
            
            $io->text("🔥 {$game->getTitle()}");
            $io->text("   URL originale: $originalUrl");
            $io->text("   URL améliorée: $improvedUrl");
            $io->text("   Date de sortie: " . $game->getReleaseDate()?->format('Y-m-d'));
            $io->text("   Note: {$game->getTotalRating()}");
            $io->newLine();
        }

        $io->success('🎯 Analyse terminée - Aucune modification effectuée');
        $io->note('💡 Utilisez app:fix-images ou app:update-existing-images pour appliquer des corrections');

        return Command::SUCCESS;
    }
} 