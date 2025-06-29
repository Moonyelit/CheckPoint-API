<?php

namespace App\Controller;

use App\Repository\GameRepository;
use App\Service\GameImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * 🔍 CONTRÔLEUR - RECHERCHE INTELLIGENTE DE JEUX
 * 
 * Ce contrôleur gère la recherche de jeux avec une stratégie intelligente :
 * recherche locale d'abord, puis import automatique depuis IGDB si nécessaire.
 * 
 * 🔧 FONCTIONNALITÉS :
 * 
 * 📊 STRATÉGIE DE RECHERCHE :
 * 1️⃣ Recherche dans la base locale (rapide)
 * 2️⃣ Si aucun résultat → Import auto depuis IGDB
 * 3️⃣ Cache des résultats pour éviter les requêtes répétées
 * 
 * 💾 SYSTÈME DE CACHE :
 * - Clé basée sur MD5 de la requête
 * - Durée : 1 heure (3600 secondes)
 * - Évite les appels API IGDB répétés
 * - Améliore les performances utilisateur
 * 
 * 🎯 UTILISATION :
 * - Interface web pour recherche de jeux
 * - Complément à l'API pour interface graphique
 * - Auto-enrichissement de la base de données
 * 
 * 📝 ROUTE :
 * GET /games/search/{query}
 * 
 * 🔄 WORKFLOW :
 * 1. Vérification cache existant
 * 2. Recherche locale par titre (LIKE)
 * 3. Si vide → Import IGDB automatique
 * 4. Mise en cache des résultats
 * 5. Rendu vue Twig avec résultats
 * 
 * ⚡ PERFORMANCE :
 * - Cache système pour réduire latence
 * - Import à la demande (lazy loading)
 * - Gestion d'erreurs gracieuse
 * 
 * 💡 AVANTAGES :
 * - Base de données auto-enrichie
 * - Expérience utilisateur fluide
 * - Réduction charge API IGDB
 */

class GameSearchController extends AbstractController
{
    #[Route('/games/search/{query}', name: 'games_search')]
    public function search(
        string $query,
        GameRepository $gameRepository,
        GameImporter $gameImporter
    ): Response {
        $cache = new FilesystemAdapter();

        // 🔑 Clé de cache basée sur la requête utilisateur
        $cacheKey = 'search_' . md5($query);

        // 📋 Vérifie si la recherche a été faite récemment (cache hit)
        $cachedGames = $cache->getItem($cacheKey);

        if (!$cachedGames->isHit()) {
            // 🔍 ÉTAPE 1 : Recherche locale dans la base de données
            $games = $gameRepository->findByTitleLike($query);
            
            // Log pour debug
            error_log("🔍 Recherche locale pour '$query': " . count($games) . " résultats trouvés");

            // 📥 ÉTAPE 2 : Si résultats insuffisants, complète avec import IGDB
            if (count($games) === 0) {
                error_log("📥 Aucun résultat local, tentative d'import IGDB pour '$query'");
                
                try {
                    $importedGames = $gameImporter->importGamesBySearch($query);
                    error_log("✅ Import IGDB réussi pour '$query': " . count($importedGames) . " jeux importés");
                    $games = array_merge($games, $importedGames);
                } catch (\Throwable $e) {
                    error_log("❌ Erreur lors de l'import IGDB pour '$query': " . $e->getMessage());
                    error_log("❌ Stack trace: " . $e->getTraceAsString());
                    $this->addFlash('error', 'Erreur lors de la récupération des jeux IGDB: ' . $e->getMessage());
                }
            } else {
                error_log("✅ Résultats locaux suffisants pour '$query', pas d'import IGDB nécessaire");
                
                // TEMPORAIRE: Forcer l'import IGDB même avec des résultats locaux pour tester
                error_log("🧪 TEST: Import IGDB forcé même avec des résultats locaux");
                try {
                    $importedGames = $gameImporter->importGamesBySearch($query);
                    error_log("✅ Import IGDB forcé réussi pour '$query': " . count($importedGames) . " jeux importés");
                    $games = array_merge($games, $importedGames);
                } catch (\Throwable $e) {
                    error_log("❌ Erreur lors de l'import IGDB forcé pour '$query': " . $e->getMessage());
                }
            }

            // 💾 Sauvegarde en cache pour 1h (évite requêtes répétées)
            $cachedGames->set($games);
            $cachedGames->expiresAfter(3600); // 1h
            $cache->save($cachedGames);
            error_log("💾 Cache mis à jour pour '$query' avec " . count($games) . " jeux");
        } else {
            // ⚡ Récupération depuis le cache (performance optimale)
            $games = $cachedGames->get();
            error_log("⚡ Résultats récupérés depuis le cache pour '$query': " . count($games) . " jeux");
        }

        // 🎨 Affichage des résultats avec vue Twig
        return $this->render('games/search.html.twig', [
            'games' => $games,
            'query' => $query,
        ]);
    }

    /**
     * Route de test pour forcer une recherche IGDB en vidant le cache
     */
    #[Route('/games/search-test/{query}', name: 'games_search_test')]
    public function searchTest(
        string $query,
        GameRepository $gameRepository,
        GameImporter $gameImporter
    ): Response {
        error_log("🧪 ROUTE DE TEST - Recherche forcée pour '$query'");
        
        $cache = new FilesystemAdapter();
        $cacheKey = 'search_' . md5($query);
        
        // Force la suppression du cache
        $cache->deleteItem($cacheKey);
        error_log("🗑️ Cache supprimé pour '$query'");
        
        // Recherche locale
        $games = $gameRepository->findByTitleLike($query);
        error_log("🔍 Recherche locale pour '$query': " . count($games) . " résultats trouvés");
        
        // Force l'import IGDB même si des résultats locaux existent
        try {
            error_log("📥 Import IGDB forcé pour '$query'");
            $importedGames = $gameImporter->importGamesBySearch($query);
            error_log("✅ Import IGDB réussi pour '$query': " . count($importedGames) . " jeux importés");
            $games = array_merge($games, $importedGames);
        } catch (\Throwable $e) {
            error_log("❌ Erreur lors de l'import IGDB pour '$query': " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());
            $this->addFlash('error', 'Erreur lors de la récupération des jeux IGDB: ' . $e->getMessage());
        }
        
        return $this->render('games/search.html.twig', [
            'games' => $games,
            'query' => $query,
        ]);
    }
}
