<?php

namespace App\Service;

use App\Repository\GameRepository;
use Psr\Log\LoggerInterface;

/**
 * 🎮 SERVICE GAME SEARCH - RECHERCHE INTELLIGENTE AVEC FALLBACK
 *
 * Ce service gère la recherche de jeux avec une stratégie de fallback intelligente :
 * 1. Recherche d'abord en base locale (rapide)
 * 2. Si pas de résultats, recherche sur IGDB (plus lent mais complet)
 * 3. Fusion intelligente des résultats
 *
 * 🔧 FONCTIONNALITÉS :
 * - Priorité aux jeux locaux pour la performance
 * - Fallback automatique vers IGDB
 * - Gestion des erreurs et logging
 * - Import en arrière-plan pour enrichir la base
 */
class GameSearchService
{
    public function __construct(
        private GameRepository $gameRepository,
        private GameImporter $gameImporter,
        private LoggerInterface $logger
    ) {}

    /**
     * Recherche un jeu avec fallback intelligent
     * 
     * @param string $query Le terme de recherche
     * @param bool $forceIgdb Forcer la recherche sur IGDB même si des résultats locaux existent
     * @return array Résultat avec les jeux et les métadonnées
     */
    public function searchWithFallback(string $query, bool $forceIgdb = false): array
    {
        $this->logger->info("Recherche avec fallback pour : '{$query}'");

        // 1. Recherche locale d'abord
        $localGames = $this->gameRepository->findByTitleLike($query);
        $this->logger->info(sprintf("Trouvé %d jeux en base locale", count($localGames)));

        // Si on a des résultats locaux et qu'on ne force pas IGDB
        if (!empty($localGames) && !$forceIgdb) {
            // Lance l'import en arrière-plan pour enrichir la base
            $this->enrichInBackground($query);
            
            return [
                'games' => $localGames,
                'source' => 'local',
                'message' => 'Jeux trouvés en base locale',
                'total' => count($localGames),
                'local_count' => count($localGames),
                'igdb_count' => 0
            ];
        }

        // 2. Recherche sur IGDB
        $igdbGames = [];
        try {
            $igdbGames = $this->gameImporter->importGamesBySearch($query);
            $this->logger->info(sprintf("Importé %d jeux depuis IGDB", count($igdbGames)));
        } catch (\Throwable $e) {
            $this->logger->error("Erreur lors de l'import IGDB: " . $e->getMessage());
            
            // Si on a des résultats locaux, on les renvoie malgré l'erreur IGDB
            if (!empty($localGames)) {
                return [
                    'games' => $localGames,
                    'source' => 'local_fallback',
                    'message' => 'Jeux trouvés en local (erreur IGDB)',
                    'total' => count($localGames),
                    'local_count' => count($localGames),
                    'igdb_count' => 0,
                    'error' => $e->getMessage()
                ];
            }
            
            return [
                'games' => [],
                'source' => 'error',
                'message' => 'Erreur lors de la recherche',
                'total' => 0,
                'local_count' => 0,
                'igdb_count' => 0,
                'error' => $e->getMessage()
            ];
        }

        // 3. Fusion intelligente des résultats
        $finalGames = $this->mergeResults($localGames, $igdbGames);
        
        return [
            'games' => $finalGames,
            'source' => empty($localGames) ? 'igdb' : 'mixed',
            'message' => empty($localGames) ? 'Jeux importés depuis IGDB' : 'Résultats fusionnés',
            'total' => count($finalGames),
            'local_count' => count($localGames),
            'igdb_count' => count($igdbGames)
        ];
    }

    /**
     * Recherche rapide (locale uniquement)
     */
    public function searchLocal(string $query): array
    {
        $games = $this->gameRepository->findByTitleLike($query);
        
        return [
            'games' => $games,
            'source' => 'local_only',
            'message' => 'Recherche locale uniquement',
            'total' => count($games)
        ];
    }

    /**
     * Recherche complète (IGDB uniquement)
     */
    public function searchIgdb(string $query): array
    {
        try {
            $games = $this->gameImporter->importGamesBySearch($query);
            
            return [
                'games' => $games,
                'source' => 'igdb_only',
                'message' => 'Recherche IGDB uniquement',
                'total' => count($games)
            ];
        } catch (\Throwable $e) {
            $this->logger->error("Erreur recherche IGDB: " . $e->getMessage());
            
            return [
                'games' => [],
                'source' => 'error',
                'message' => 'Erreur lors de la recherche IGDB',
                'total' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Fusionne les résultats locaux et IGDB en évitant les doublons
     */
    private function mergeResults(array $localGames, array $igdbGames): array
    {
        $finalGames = [];
        $localGameIds = array_map(fn($game) => $game->getIgdbId(), $localGames);
        
        // Ajoute d'abord tous les jeux locaux (priorité)
        foreach ($localGames as $localGame) {
            $finalGames[] = $localGame;
        }
        
        // Ajoute les jeux IGDB qui ne sont pas déjà en local
        foreach ($igdbGames as $igdbGame) {
            if (!in_array($igdbGame->getIgdbId(), $localGameIds)) {
                $finalGames[] = $igdbGame;
            }
        }
        
        return $finalGames;
    }

    /**
     * Enrichit la base en arrière-plan
     */
    private function enrichInBackground(string $query): void
    {
        // En production, utilise un job queue (Symfony Messenger, etc.)
        try {
            $this->gameImporter->importGamesBySearch($query);
            $this->logger->info("Enrichissement en arrière-plan terminé pour : '{$query}'");
        } catch (\Throwable $e) {
            $this->logger->error("Erreur enrichissement arrière-plan : " . $e->getMessage());
        }
    }
} 