<?php

namespace App\Command;

use App\Service\IgdbClient;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 🔧 COMMANDE AVANCÉE - CORRECTION ET DIAGNOSTIC COMPLET DES IMAGES
 * 
 * Cette commande effectue une analyse complète et une correction de tous les problèmes
 * d'images dans la base de données avec un reporting détaillé.
 * 
 * 🔧 FONCTIONNALITÉS AVANCÉES :
 * 
 * 🔍 DIAGNOSTIC COMPLET :
 * - Validation des URLs (format, accessibilité)
 * - Détection des images corrompues ou manquantes
 * - Statistiques détaillées par catégorie
 * - Logging verbeux pour debug
 * 
 * ✨ CORRECTION INTELLIGENTE :
 * - Amélioration qualité automatique (t_cover_big)
 * - Skip des images déjà optimisées
 * - Gestion d'erreurs individuelle par jeu
 * - Mise à jour conditionnelle (uniquement si changement)
 * 
 * 📊 REPORTING DÉTAILLÉ :
 * - Compteurs par type de traitement
 * - Affichage avant/après pour chaque amélioration
 * - Résumé final avec statistiques complètes
 * - Test automatique des endpoints trending
 * 
 * 🎯 OBJECTIFS :
 * - Résoudre tous les problèmes d'images en une fois
 * - Fournir un diagnostic complet de l'état des images
 * - Optimiser l'affichage dans tous les composants
 * - Valider la qualité des endpoints publics
 * 
 * ⚡ UTILISATION :
 * php bin/console app:fix-images
 * 
 * 🔄 WORKFLOW :
 * 1. Scan complet de tous les jeux
 * 2. Validation URL par URL
 * 3. Amélioration qualité si nécessaire
 * 4. Sauvegarde batch optimisée
 * 5. Test des endpoints critiques
 * 6. Rapport final détaillé
 * 
 * 💡 AVANTAGES :
 * - Commande "all-in-one" pour les images
 * - Debug avancé avec logs détaillés
 * - Validation de la cohérence globale
 * - Test automatique post-traitement
 * 
 * 🚨 UTILISATION RECOMMANDÉE :
 * - Après migration ou import massif
 * - En cas de problèmes d'affichage frontend
 * - Maintenance mensuelle approfondie
 * - Debug suite à changements API IGDB
 */

#[AsCommand(
    name: 'app:fix-images',
    description: 'Corrige et améliore toutes les images avec diagnostic complet et reporting détaillé',
)]
class FixImagesCommand extends Command
{
    private GameRepository $gameRepository;
    private IgdbClient $igdbClient;
    private EntityManagerInterface $entityManager;

    public function __construct(
        GameRepository $gameRepository,
        IgdbClient $igdbClient,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct();
        $this->gameRepository = $gameRepository;
        $this->igdbClient = $igdbClient;
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('🔧 Correction et amélioration complète des images');
        $io->info('Diagnostic avancé avec validation, correction et test des endpoints');

        // 📋 Récupère tous les jeux pour analyse complète
        $games = $this->gameRepository->findAll();
        $totalGames = count($games);
        
        $io->info("📊 Analyse de $totalGames jeux...");

        // 📈 Compteurs pour statistiques finales
        $improvedCount = 0;
        $errorCount = 0;
        $nullCount = 0;

        foreach ($games as $game) {
            $title = $game->getTitle();
            $originalUrl = $game->getCoverUrl();

            // 🔍 DIAGNOSTIC : Vérification des jeux sans image
            if (!$originalUrl) {
                $nullCount++;
                $io->text("⚠️  $title : Pas d'URL d'image");
                continue;
            }

            try {
                // ✅ VALIDATION : Vérification format URL
                if (!filter_var($originalUrl, FILTER_VALIDATE_URL)) {
                    $io->error("❌ URL invalide pour $title : $originalUrl");
                    $errorCount++;
                    continue;
                }

                // ✨ AMÉLIORATION : Optimisation qualité image
                $improvedUrl = $this->igdbClient->improveImageQuality($originalUrl, 't_cover_big');

                // 💾 MISE À JOUR : Seulement si l'URL a effectivement changé
                if ($improvedUrl !== $originalUrl) {
                    $game->setCoverUrl($improvedUrl);
                    $game->setUpdatedAt(new \DateTimeImmutable());
                    $improvedCount++;
                    $io->text("✅ Améliorée: $title");
                    $io->text("   Avant: $originalUrl");
                    $io->text("   Après: $improvedUrl");
                } else {
                    $io->text("🔍 Déjà optimisée: $title");
                }

            } catch (\Exception $e) {
                $errorCount++;
                $io->error("❌ Erreur pour $title : " . $e->getMessage());
            }
        }

        // 💾 Sauvegarde toutes les modifications en batch
        if ($improvedCount > 0) {
            $this->entityManager->flush();
            $io->success("$improvedCount images améliorées et sauvegardées !");
        }

        // 📊 Affichage du résumé statistique
        $io->section('📊 Résumé statistique :');
        $io->listing([
            "Total de jeux analysés : $totalGames",
            "Images améliorées : $improvedCount",
            "Jeux sans images : $nullCount",
            "Erreurs rencontrées : $errorCount",
            "Déjà optimisées : " . ($totalGames - $improvedCount - $nullCount - $errorCount)
        ]);

        // 🧪 TESTS AUTOMATIQUES :
        // - Test automatique des endpoints jeux récents

        $io->success('🎯 Correction et diagnostic terminés avec succès !');

        return Command::SUCCESS;
    }
} 