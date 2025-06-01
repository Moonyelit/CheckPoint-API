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

            // 📥 ÉTAPE 2 : Si résultats insuffisants, complète avec import IGDB
            if (count($games) === 0) {
                try {
                    $importedGames = $gameImporter->importGamesBySearch($query);
                    $games = array_merge($games, $importedGames);
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Erreur lors de la récupération des jeux IGDB.');
                }
            }

            // 💾 Sauvegarde en cache pour 1h (évite requêtes répétées)
            $cachedGames->set($games);
            $cachedGames->expiresAfter(3600); // 1h
            $cache->save($cachedGames);
        } else {
            // ⚡ Récupération depuis le cache (performance optimale)
            $games = $cachedGames->get();
        }

        // 🎨 Affichage des résultats avec vue Twig
        return $this->render('games/search.html.twig', [
            'games' => $games,
            'query' => $query,
        ]);
    }
}
