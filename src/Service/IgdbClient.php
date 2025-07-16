<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * 🎮 SERVICE IGDB CLIENT - INTERFACE AVANCÉE AVEC L'API IGDB
 *
 * Ce service centralise toutes les interactions avec l'API IGDB (Internet Game Database)
 * pour la récupération, la recherche et l'amélioration des données de jeux vidéo.
 *
 * 🔧 FONCTIONNALITÉS PRINCIPALES :
 *
 * 🔍 RECHERCHE & RÉCUPÉRATION :
 * - Recherche de jeux par mot-clé (titre, etc.)
 * - Récupération des jeux populaires, top 100, jeux récents, etc.
 * - Pagination et gestion des résultats volumineux
 *
 * 🖼️ OPTIMISATION DES IMAGES :
 * - Amélioration automatique de la qualité des images de couverture et screenshots
 * - Conversion des URLs pour obtenir la meilleure résolution possible
 *
 * 🔒 SÉCURITÉ & PERFORMANCE :
 * - Gestion intelligente du token d'accès OAuth (Twitch)
 * - Réutilisation du token pour limiter les appels
 * - Gestion des erreurs et fallback
 *
 * 🎯 UTILISATION :
 * - Utilisé par les services d'import, de recherche et d'affichage frontend
 * - Permet d'enrichir dynamiquement la base locale avec des données IGDB
 * - Complète les recherches utilisateur en temps réel
 *
 * ⚡ EXEMPLES D'USAGE :
 * - Import massif de jeux (top 100, populaires, récents)
 * - Recherche dynamique lors d'une requête utilisateur
 * - Amélioration de la qualité d'affichage des images sur le frontend
 *
 * 💡 AVANTAGES :
 * - Centralisation de toute la logique IGDB
 * - Facile à maintenir et à étendre
 * - Optimisé pour la performance et la qualité des données
 *
 * 🔧 UTILISATION RECOMMANDÉE :
 * - Pour tout accès à IGDB (import, recherche, enrichissement)
 * - Pour garantir la cohérence et la qualité des données jeux
 */
class IgdbClient
{
    private HttpClientInterface $client;
    private string $clientId;
    private string $clientSecret;
    private ?string $accessToken = null;
    private ?int $tokenTimestamp = null;
    private LoggerInterface $logger;
    
    // Cache simple pour les résultats de comptage
    private array $countCache = [];
    private array $countCacheTimestamps = [];
    private const COUNT_CACHE_DURATION = 3600; // 1 heure

    /**
     * Constructeur de la classe IgdbClient.
     *
     * @param HttpClientInterface $client       Le client HTTP pour effectuer les requêtes.
     * @param string              $clientId     L'identifiant client pour l'API IGDB.
     * @param string              $clientSecret Le secret client pour l'API IGDB.
     * @param LoggerInterface     $logger       Le logger pour les messages de debug.
     */
    public function __construct(HttpClientInterface $client, string $clientId, string $clientSecret, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->logger = $logger;
    }

    /**
     * Récupère un token d'accès pour l'API IGDB.
     *
     * Si un token valide existe déjà (moins d'une heure), il est réutilisé.
     * Sinon, une nouvelle requête est effectuée pour obtenir un token.
     *
     * @return string Le token d'accès valide.
     */
    public function getAccessToken(): string
    {
        // Vérifie si le token actuel est encore valide
        if ($this->accessToken && (time() - $this->tokenTimestamp < 3600)) {
            return $this->accessToken;
        }

        // Effectue une requête pour obtenir un nouveau token
        $response = $this->client->request('POST', 'https://id.twitch.tv/oauth2/token', [
            'query' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
            ],
        ]);

        // Convertit la réponse en tableau et extrait le token
        $data = $response->toArray();

        $this->accessToken = $data['access_token'];
        $this->tokenTimestamp = time();

        return $this->accessToken;
    }

    private function cleanBody(string $body): string
    {
        // Supprime les espaces/tabs en début de chaque ligne et les lignes vides
        $lines = explode("\n", $body);
        $cleaned = array_map(fn($l) => ltrim($l), $lines);
        return implode("\n", array_filter($cleaned, fn($l) => trim($l) !== ''));
    }

    /**
     * Recherche des jeux dans l'API IGDB en fonction d'un mot-clé.
     *
     * @param string $search Le mot-clé à rechercher.
     * @param int $limit Le nombre de résultats par page
     * @param int $offset L'offset pour la pagination
     * @return array La liste des jeux correspondant à la recherche.
     */
    public function searchGames(string $search, int $limit = 50, int $offset = 0): array
    {
        try {
            $accessToken = $this->getAccessToken();
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur lors de la récupération du token IGDB: " . $e->getMessage());
            throw $e;
        }

        $requestBody = <<<EOT
fields name, summary, cover.url, first_release_date, genres.name, platforms.name, game_modes.name, player_perspectives.name, screenshots, artworks, total_rating, total_rating_count, involved_companies.company.name, involved_companies.publisher, category, alternative_names.name, release_dates.platform.name, release_dates.date, videos, age_ratings;
search "$search";
limit $limit;
offset $offset;
EOT;
        $requestBody = $this->cleanBody($requestBody);

        try {
            // Effectue une requête POST pour rechercher des jeux
            $response = $this->client->request('POST', 'https://api.igdb.com/v4/games', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'text/plain',
                ],
                'body' => $requestBody
            ]);
            
            $games = $response->toArray();
            
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur lors de la requête IGDB pour '$search': " . $e->getMessage());
            throw $e;
        }
        
        // Améliore la qualité des images de couverture
        foreach ($games as &$game) {
            if (isset($game['cover']['url'])) {
                // S'assurer que l'URL a le bon format
                $imageUrl = $game['cover']['url'];
                if (strpos($imageUrl, '//') === 0) {
                    $imageUrl = 'https:' . $imageUrl;
                }
                $game['cover']['url'] = $this->improveImageQuality($imageUrl, 't_cover_big');
            }
            
            // Ajoute les notes détaillées calculées
            $game['detailed_ratings'] = $this->calculateDetailedRatings($game);
        }

        // Retourne les résultats sous forme de tableau
        return $games;
    }

    /**
     * Récupère TOUS les jeux correspondant à une recherche (avec pagination automatique)
     *
     * @param string $search Le mot-clé à rechercher.
     * @param int $maxResults Nombre maximum de résultats à récupérer (défaut: 500)
     * @return array La liste complète des jeux correspondant à la recherche.
     */
    public function searchAllGames(string $search, int $maxResults = 500): array
    {
        $allGames = [];
        $offset = 0;
        $limit = 50; // Limite par requête pour éviter les timeouts
        
        while (count($allGames) < $maxResults) {
            try {
                $games = $this->searchGames($search, $limit, $offset);
                
                if (empty($games)) {
                    break; // Plus de résultats disponibles
                }
                
                $allGames = array_merge($allGames, $games);
                $offset += $limit;
                
                // Si on a moins de jeux que la limite, c'est qu'on a atteint la fin
                if (count($games) < $limit) {
                    break;
                }
                
                // Petite pause pour éviter de surcharger l'API
                usleep(100000); // 100ms
                
            } catch (\Exception $e) {
                $this->logger->error("❌ Erreur lors de la pagination pour '$search': " . $e->getMessage());
                break;
            }
        }
        
        return $allGames;
    }

    /**
     * Récupère les captures d'écran pour une liste d'IDs de jeux.
     *
     * @param array $ids Les IDs des captures d'écran à récupérer.
     * @return array La liste des URLs des captures d'écran.
     */
    public function getScreenshots(array $ids): array
    {
        // Si aucun ID n'est fourni, retourne un tableau vide
        if (empty($ids)) {
            return [];
        }

        $accessToken = $this->getAccessToken();

        // Concatène les IDs en une chaîne séparée par des virgules
        $idsString = implode(',', $ids);

        // Effectue une requête POST pour récupérer les captures d'écran
        $requestBody = <<<EOT
fields url;
where id = ($idsString);
EOT;
        $requestBody = $this->cleanBody($requestBody);
        $response = $this->client->request('POST', 'https://api.igdb.com/v4/screenshots', [
            'headers' => [
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'text/plain',
            ],
            'body' => $requestBody
        ]);

        $screenshots = $response->toArray();
        
        // Améliore la qualité des captures d'écran
        foreach ($screenshots as &$screenshot) {
            if (isset($screenshot['url'])) {
                $imageUrl = $screenshot['url'];
                if (strpos($imageUrl, '//') === 0) {
                    $imageUrl = 'https:' . $imageUrl;
                }
                $screenshot['url'] = $this->improveImageQuality($imageUrl, 't_1080p');
            }
        }

        // Retourne les résultats sous forme de tableau
        return $screenshots;
    }

    /**
     * Récupère une liste des jeux les plus populaires.
     *
     * Cette méthode effectue une requête à l'API IGDB pour obtenir les jeux
     * ayant une note totale (`total_rating`) non nulle, triés par ordre décroissant
     * de leur note. Elle retourne un maximum de 500 jeux.
     *
     * @return array La liste des jeux populaires avec leurs informations (nom, résumé, couverture, etc.).
     */
    public function getPopularGames(): array
    {
        try {
            $accessToken = $this->getAccessToken();
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur lors de la récupération du token IGDB: " . $e->getMessage());
            throw $e;
        }

        // REQUÊTE CORRIGÉE : Suppression des champs problématiques
        $requestBody = <<<EOT
fields name, summary, cover.url, first_release_date, genres.name, platforms.name, game_modes.name, player_perspectives.name, screenshots, total_rating, involved_companies.company.name, involved_companies.publisher, category, videos, alternative_names.name, release_dates.platform.name, release_dates.date, age_ratings;
sort total_rating desc;
where total_rating != null;
limit 500;
EOT;
        $requestBody = $this->cleanBody($requestBody);
        
        try {
            // Effectue une requête POST pour récupérer les jeux populaires
            $response = $this->client->request('POST', 'https://api.igdb.com/v4/games', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'text/plain',
                ],
                'body' => $requestBody
            ]);
            
            $games = $response->toArray();
            
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur lors de la requête IGDB pour les jeux populaires: " . $e->getMessage());
            throw $e;
        }
        
        // Améliore la qualité des images de couverture
        foreach ($games as &$game) {
            if (isset($game['cover']['url'])) {
                // S'assurer que l'URL a le bon format
                $imageUrl = $game['cover']['url'];
                if (strpos($imageUrl, '//') === 0) {
                    $imageUrl = 'https:' . $imageUrl;
                }
                $game['cover']['url'] = $this->improveImageQuality($imageUrl, 't_cover_big');
            }
        }

        // Retourne les résultats sous forme de tableau
        return $games;
    }

    /**
     * Récupère les jeux du Top 100 d'IGDB avec critères dynamiques.
     */
    public function getTop100Games(int $minVotes = 80, int $minRating = 75): array
    {
        try {
            $accessToken = $this->getAccessToken();
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur lors de la récupération du token IGDB: " . $e->getMessage());
            throw $e;
        }
        
        $minVotes = (int)$minVotes;
        $minRating = (int)$minRating;
        
        // REQUÊTE CORRIGÉE : Suppression des champs problématiques
        $requestBody = <<<EOT
fields name, summary, cover.url, first_release_date, genres.name, platforms.name, game_modes.name, player_perspectives.name, screenshots, artworks, total_rating, total_rating_count, involved_companies.company.name, involved_companies.publisher, category, alternative_names.name, videos, age_ratings;
sort total_rating desc;
where total_rating != null & total_rating_count >= $minVotes;
limit 100;
EOT;
        $requestBody = $this->cleanBody($requestBody);
        
        try {
            $response = $this->client->request('POST', 'https://api.igdb.com/v4/games', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'text/plain',
                ],
                'body' => $requestBody
            ]);
            
            $games = $response->toArray();
            
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur lors de la requête IGDB pour le Top 100: " . $e->getMessage());
            throw $e;
        }
        
        // Filtrer par note minimum côté PHP
        $filteredGames = array_filter($games, function($game) use ($minRating) {
            return isset($game['total_rating']) && $game['total_rating'] >= $minRating;
        });
        
        return $filteredGames;
    }

    /**
     * Récupère les meilleurs jeux sortis dans les 365 derniers jours avec critères dynamiques.
     * Critères optimisés pour les jeux récents de 2024-2025.
     */
    public function getTopYearGames(int $minVotes = 50, int $minRating = 80): array
    {
        try {
            $accessToken = $this->getAccessToken();
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur lors de la récupération du token IGDB: " . $e->getMessage());
            throw $e;
        }
        
        $minVotes = (int)$minVotes;
        $minRating = (int)$minRating;
        $oneYearAgo = (new \DateTimeImmutable('-365 days'))->getTimestamp();
        
        // REQUÊTE CORRIGÉE : Suppression des champs problématiques
        $requestBody = <<<EOT
fields name, summary, cover.url, first_release_date, genres.name, platforms.name, game_modes.name, player_perspectives.name, screenshots, artworks, total_rating, total_rating_count, involved_companies.company.name, category, alternative_names.name, release_dates.platform.name, release_dates.date, videos, age_ratings;
sort total_rating desc;
where total_rating != null & first_release_date >= $oneYearAgo & total_rating_count >= $minVotes & total_rating >= $minRating;
limit 50;
EOT;
        $requestBody = $this->cleanBody($requestBody);
        
        try {
            $response = $this->client->request('POST', 'https://api.igdb.com/v4/games', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'text/plain',
                ],
                'body' => $requestBody
            ]);
            
            $games = $response->toArray();
            
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur lors de la requête IGDB pour les jeux récents: " . $e->getMessage());
            throw $e;
        }
        
        foreach ($games as &$game) {
            if (isset($game['cover']['url'])) {
                $imageUrl = $game['cover']['url'];
                if (strpos($imageUrl, '//') === 0) {
                    $imageUrl = 'https:' . $imageUrl;
                }
                $game['cover']['url'] = $this->improveImageQuality($imageUrl, 't_cover_big');
            }
        }
        
        return $games;
    }

    /**
     * Récupère les détails complets d'un jeu par son ID IGDB.
     *
     * Cette méthode récupère toutes les informations détaillées d'un jeu
     * incluant la couverture, les screenshots, et toutes les métadonnées.
     *
     * @param int $gameId L'ID IGDB du jeu
     * @return array|null Les détails du jeu ou null si non trouvé
     */
    public function getGameDetails(int $gameId): ?array
    {
        try {
            $accessToken = $this->getAccessToken();
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur lors de la récupération du token IGDB: " . $e->getMessage());
            throw $e;
        }

        // REQUÊTE CORRIGÉE : Suppression des champs problématiques
        $requestBody = <<<EOT
fields name, summary, cover.url, first_release_date, genres.name, platforms.name, game_modes.name, player_perspectives.name, screenshots, total_rating, total_rating_count, involved_companies.company.name, involved_companies.publisher, category, age_ratings, alternative_names.name, videos;
where id = $gameId;
limit 1;
EOT;
        $requestBody = $this->cleanBody($requestBody);
        
        try {
            // Effectue une requête POST pour récupérer les détails du jeu
            $response = $this->client->request('POST', 'https://api.igdb.com/v4/games', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'text/plain',
                ],
                'body' => $requestBody
            ]);
            
            $games = $response->toArray();
            
            if (empty($games)) {
                return null;
            }

            $game = $games[0];
            
            // Améliore la qualité de l'image de couverture
            if (isset($game['cover']['url'])) {
                // S'assurer que l'URL a le bon format
                $imageUrl = $game['cover']['url'];
                if (strpos($imageUrl, '//') === 0) {
                    $imageUrl = 'https:' . $imageUrl;
                }
                $game['cover']['url'] = $this->improveImageQuality($imageUrl, 't_cover_big');
            }

            // Ajoute les notes détaillées calculées
            $game['detailed_ratings'] = $this->calculateDetailedRatings($game);

            return $game;
            
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur lors de la récupération des détails du jeu {$gameId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Test pour vérifier si les IDs "artworks" sont en fait des screenshots
     */
    public function testArtworksAsScreenshots(array $ids): array
    {
        // Filtrer les IDs pour ne garder que les entiers valides et uniques
        $ids = array_unique(array_filter($ids, fn($id) => is_numeric($id) && (int)$id > 0));
        if (empty($ids)) {
            return [];
        }

        $accessToken = $this->getAccessToken();
        $idsString = implode(',', $ids);

        // Test avec l'endpoint screenshots
        $requestBody = <<<EOT
fields url;
where id = ($idsString);
EOT;
        $requestBody = $this->cleanBody($requestBody);
        
        try {
            $response = $this->client->request('POST', 'https://api.igdb.com/v4/screenshots', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'text/plain',
                ],
                'body' => $requestBody
            ]);

            $screenshots = $response->toArray();
            
            // Améliore la qualité des images
            foreach ($screenshots as &$screenshot) {
                if (isset($screenshot['url'])) {
                    $imageUrl = $screenshot['url'];
                    if (strpos($imageUrl, '//') === 0) {
                        $imageUrl = 'https:' . $imageUrl;
                    }
                    $screenshot['url'] = $this->improveImageQuality($imageUrl, 't_1080p');
                }
            }

            return $screenshots;
        } catch (\Exception $e) {
            $this->logger->error("❌ Test artworks comme screenshots échoué : " . $e->getMessage());
            return [];
        }
    }

    public function getArtworks(array $ids): array
    {
        // Filtrer les IDs pour ne garder que les entiers valides et uniques
        $ids = array_unique(array_filter($ids, fn($id) => is_numeric($id) && (int)$id > 0));
        if (empty($ids)) {
            return [];
        }

        $accessToken = $this->getAccessToken();
        $idsString = implode(',', $ids);

        // Récupération avec plus de champs pour avoir plus d'informations
        $requestBody = <<<EOT
            fields url, width, height, image_id;
            where id = ($idsString);
            limit 500;
        EOT;
        $requestBody = $this->cleanBody($requestBody);
        
        try {
            $response = $this->client->request('POST', 'https://api.igdb.com/v4/artworks', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'text/plain',
                ],
                'body' => $requestBody
            ]);

            $artworks = $response->toArray();
            
            // Améliore la qualité des artworks
            foreach ($artworks as &$artwork) {
                if (isset($artwork['url'])) {
                    $imageUrl = $artwork['url'];
                    if (strpos($imageUrl, '//') === 0) {
                        $imageUrl = 'https:' . $imageUrl;
                    }
                    $artwork['url'] = $this->improveImageQuality($imageUrl, 't_1080p');
                }
            }

            return $artworks;
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur lors de la récupération des artworks: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les artworks pour une liste de jeux par leurs IDs de jeu.
     * Cette méthode peut récupérer plus d'artworks que getArtworks() car elle
     * recherche directement par ID de jeu plutôt que par ID d'artwork.
     *
     * @param array $gameIds Les IDs des jeux pour lesquels récupérer les artworks
     * @return array La liste des artworks avec leurs informations
     */
    public function getArtworksByGame(array $gameIds): array
    {
        // Filtrer les IDs pour ne garder que les entiers valides et uniques
        $gameIds = array_unique(array_filter($gameIds, fn($id) => is_numeric($id) && (int)$id > 0));
        if (empty($gameIds)) {
            return [];
        }

        $accessToken = $this->getAccessToken();
        $gameIdsString = implode(',', $gameIds);

        // Récupération des artworks par ID de jeu
        $requestBody = <<<EOT
            fields url, width, height, image_id, game;
            where game = ($gameIdsString);
            limit 1000;
        EOT;
        $requestBody = $this->cleanBody($requestBody);
        
        try {
            $response = $this->client->request('POST', 'https://api.igdb.com/v4/artworks', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'text/plain',
                ],
                'body' => $requestBody
            ]);

            $artworks = $response->toArray();
            
            // Améliore la qualité des artworks
            foreach ($artworks as &$artwork) {
                if (isset($artwork['url'])) {
                    $imageUrl = $artwork['url'];
                    if (strpos($imageUrl, '//') === 0) {
                        $imageUrl = 'https:' . $imageUrl;
                    }
                    $artwork['url'] = $this->improveImageQuality($imageUrl, 't_1080p');
                }
            }

            return $artworks;
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur lors de la récupération des artworks par jeu: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les vidéos pour une liste d'IDs.
     *
     * @param array $ids Les IDs des vidéos à récupérer.
     * @return array La liste des vidéos avec leurs informations.
     */
    public function getVideos(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $accessToken = $this->getAccessToken();
        $idsString = implode(',', $ids);

        $requestBody = <<<EOT
fields video_id, name;
where id = ($idsString);
EOT;
        $requestBody = $this->cleanBody($requestBody);
        try {
            $response = $this->client->request('POST', 'https://api.igdb.com/v4/game_videos', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'text/plain',
                ],
                'body' => $requestBody
            ]);

            $videos = $response->toArray();
            
            // Ajoute l'URL YouTube pour chaque vidéo
            foreach ($videos as &$video) {
                if (isset($video['video_id'])) {
                    $video['url'] = "https://www.youtube.com/watch?v=" . $video['video_id'];
                }
            }

            return $videos;
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur lors de la récupération des vidéos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les classifications d'âge détaillées pour une liste d'IDs de jeux.
     *
     * @param array $gameIds Les IDs des jeux pour lesquels récupérer les classifications d'âge
     * @return array Tableau associatif [game_id => [labels...]]
     */
    public function getAgeRatings(array $gameIds): array
    {
        // Filtrer les IDs pour ne garder que les entiers valides et uniques
        $gameIds = array_unique(array_filter($gameIds, fn($id) => is_numeric($id) && (int)$id > 0));
        if (empty($gameIds)) {
            return [];
        }

        $accessToken = $this->getAccessToken();
        $gameIdsString = implode(',', $gameIds);

        // 1. Récupérer les IDs d'age_ratings pour chaque jeu
        $requestBody = <<<EOT
            fields id, age_ratings;
            where id = ($gameIdsString);
            limit 500;
        EOT;
        $requestBody = $this->cleanBody($requestBody);
        try {
            $response = $this->client->request('POST', 'https://api.igdb.com/v4/games', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'text/plain',
                ],
                'body' => $requestBody
            ]);
            $games = $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur lors de la récupération des jeux: " . $e->getMessage());
            return [];
        }

        // Extraire tous les IDs d'age_ratings
        $ageRatingIds = [];
        $gameToAgeRatingIds = [];
        foreach ($games as $game) {
            if (isset($game['age_ratings']) && is_array($game['age_ratings'])) {
                $gameToAgeRatingIds[$game['id']] = $game['age_ratings'];
                foreach ($game['age_ratings'] as $arId) {
                    $ageRatingIds[] = $arId;
                }
            }
        }
        $ageRatingIds = array_unique($ageRatingIds);
        if (empty($ageRatingIds)) {
            return [];
        }

        // 2. Récupérer les détails des age_ratings
        $idsString = implode(',', $ageRatingIds);
        $requestBody = <<<EOT
            fields id, rating, category;
            where id = ($idsString);
            limit 500;
        EOT;
        $requestBody = $this->cleanBody($requestBody);
        try {
            $response = $this->client->request('POST', 'https://api.igdb.com/v4/age_ratings', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'text/plain',
                ],
                'body' => $requestBody
            ]);
            $ageRatingsDetails = $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur lors de la récupération des détails des classifications d'âge: " . $e->getMessage());
            return [];
        }

        // Mapping id => label
        $idToLabel = [];
        foreach ($ageRatingsDetails as $ar) {
            // On peut améliorer ici pour avoir PEGI/ESRB lisible
            $label = $ar['rating'] ?? null;
            if ($ar['category'] === 1) {
                $label = 'ESRB ' . $label;
            } elseif ($ar['category'] === 2) {
                $label = 'PEGI ' . $label;
            }
            $idToLabel[$ar['id']] = $label;
        }

        // Associer chaque jeu à ses labels
        $result = [];
        foreach ($gameToAgeRatingIds as $gameId => $arIds) {
            $labels = [];
            foreach ($arIds as $arId) {
                if (isset($idToLabel[$arId])) {
                    $labels[] = $idToLabel[$arId];
                }
            }
            $result[$gameId] = $labels;
        }
        return $result;
    }

    /**
     * Améliore la qualité d'une URL d'image IGDB.
     *
     * @param string $url L'URL originale de l'image
     * @param string $size La taille désirée (t_1080p, t_720p, t_cover_big, etc.)
     * @return string L'URL avec la nouvelle taille
     */
    public function improveImageQuality(string $url, string $size = 't_1080p'): string
    {
        // S'assurer que l'URL a le bon format
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        } elseif (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }

        // Vérifie si l'URL contient déjà la taille demandée pour éviter les doublons
        if (strpos($url, $size) !== false) {
            return $url;
        }

        // Vérifie si l'image est déjà en haute qualité
        $highQualityPatterns = ['t_cover_big', 't_1080p', 't_720p', 't_original'];
        foreach ($highQualityPatterns as $pattern) {
            if (strpos($url, $pattern) !== false) {
                return $url; // Déjà en haute qualité, pas besoin de modifier
            }
        }

        // Remplace les tailles de basse qualité par la taille désirée
        $patterns = [
            '/t_thumb/', '/t_micro/', '/t_cover_small/', '/t_screenshot_med/', '/t_cover_small_2x/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return preg_replace($pattern, $size, $url);
            }
        }
        
        // Si aucun pattern trouvé et que c'est une image IGDB, ajoute la taille
        if (strpos($url, 'images.igdb.com') !== false && strpos($url, '.jpg') !== false) {
            return str_replace('.jpg', '_' . $size . '.jpg', $url);
        }
        
        return $url;
    }

    /**
     * Retourne le nombre total de jeux correspondant à une recherche.
     *
     * @param string $search Le mot-clé à rechercher.
     * @return int Le nombre total de jeux trouvés.
     */
    public function countGames(string $search): int
    {
        try {
            // Vérifie si le résultat est déjà en cache
            if (isset($this->countCache[$search]) && (time() - $this->countCacheTimestamps[$search] < self::COUNT_CACHE_DURATION)) {
                return $this->countCache[$search];
            }
            
            $accessToken = $this->getAccessToken();
            
            // Une seule requête pour obtenir une estimation
            $response = $this->client->request('POST', 'https://api.igdb.com/v4/games', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'text/plain',
                ],
                'body' => <<<EOT
                    fields id;
                    search "$search";
                    limit 500;
                EOT
            ]);
            $data = $response->toArray();
            $count = count($data);
            
            // Si on a 500 résultats, il y a probablement plus de jeux
            // On retourne une estimation conservatrice
            if ($count >= 500) {
                // Pour "Final Fantasy", on sait qu'il y a beaucoup de jeux
                // On peut faire une estimation basée sur la popularité du terme
                $popularTerms = ['final fantasy', 'mario', 'zelda', 'pokemon', 'call of duty', 'fifa'];
                $isPopularTerm = in_array(strtolower($search), $popularTerms);
                
                if ($isPopularTerm) {
                    $estimatedCount = 1000; // Estimation pour les termes très populaires
                } else {
                    $estimatedCount = 750; // Estimation pour les autres termes
                }
            } else {
                $estimatedCount = $count;
            }
            
            // Met en cache le résultat
            $this->countCache[$search] = $estimatedCount;
            $this->countCacheTimestamps[$search] = time();
            
            return $estimatedCount;
            
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Calcule des notes détaillées pour le radar chart basées sur les données IGDB.
     * 
     * @param array $game Les données du jeu depuis IGDB
     * @return array Les notes calculées pour chaque critère
     */
    public function calculateDetailedRatings(array $game): array
    {
        $ratings = [
            'jouabilite' => 0,
            'gameplay' => 0,
            'musique' => 0,
            'histoire' => 0,
            'graphisme' => 0
        ];

        // Note de base basée sur total_rating
        $baseRating = $game['total_rating'] ?? 0;
        $ratingCount = $game['total_rating_count'] ?? 0;

        // Si pas de note, on ne peut pas calculer
        if ($baseRating <= 0) {
            return $ratings;
        }

        // Facteurs de pondération basés sur les genres et caractéristiques du jeu
        $genreFactors = $this->getGenreFactors($game);
        $platformFactors = $this->getPlatformFactors($game);
        $categoryFactors = $this->getCategoryFactors($game);

        // Calcul des notes détaillées
        $ratings['jouabilite'] = $this->calculateJouabiliteRating($baseRating, $genreFactors, $platformFactors);
        $ratings['gameplay'] = $this->calculateGameplayRating($baseRating, $genreFactors, $categoryFactors);
        $ratings['musique'] = $this->calculateMusiqueRating($baseRating, $genreFactors);
        $ratings['histoire'] = $this->calculateHistoireRating($baseRating, $genreFactors);
        $ratings['graphisme'] = $this->calculateGraphismeRating($baseRating, $platformFactors, $categoryFactors);

        // Normalisation des notes (0-100)
        foreach ($ratings as $key => $rating) {
            $ratings[$key] = min(100, max(0, round($rating)));
        }

        return $ratings;
    }

    /**
     * Calcule les facteurs de pondération basés sur les genres.
     */
    private function getGenreFactors(array $game): array
    {
        $genres = array_map(fn($g) => strtolower($g['name'] ?? ''), $game['genres'] ?? []);
        
        return [
            'action' => in_array('action', $genres),
            'rpg' => in_array('role-playing', $genres),
            'strategy' => in_array('strategy', $genres),
            'adventure' => in_array('adventure', $genres),
            'sports' => in_array('sports', $genres),
            'racing' => in_array('racing', $genres),
            'fighting' => in_array('fighting', $genres),
            'shooter' => in_array('shooter', $genres),
            'puzzle' => in_array('puzzle', $genres),
            'simulation' => in_array('simulation', $genres),
        ];
    }

    /**
     * Calcule les facteurs de pondération basés sur les plateformes.
     */
    private function getPlatformFactors(array $game): array
    {
        $platforms = array_map(fn($p) => strtolower($p['name'] ?? ''), $game['platforms'] ?? []);
        
        return [
            'pc' => in_array('pc (microsoft windows)', $platforms),
            'ps5' => in_array('playstation 5', $platforms),
            'ps4' => in_array('playstation 4', $platforms),
            'xbox' => in_array('xbox series x|s', $platforms) || in_array('xbox one', $platforms),
            'nintendo' => in_array('nintendo switch', $platforms) || in_array('wii u', $platforms),
            'mobile' => in_array('android', $platforms) || in_array('ios', $platforms),
        ];
    }

    /**
     * Calcule les facteurs de pondération basés sur la catégorie du jeu.
     */
    private function getCategoryFactors(array $game): array
    {
        $category = $game['category'] ?? 0;
        
        return [
            'main_game' => $category === 0,
            'dlc' => $category === 1,
            'expansion' => $category === 2,
            'bundle' => $category === 3,
            'standalone_expansion' => $category === 4,
            'mod' => $category === 5,
            'episode' => $category === 6,
            'season' => $category === 7,
        ];
    }

    /**
     * Calcule la note de jouabilité.
     */
    private function calculateJouabiliteRating(float $baseRating, array $genreFactors, array $platformFactors): float
    {
        $rating = $baseRating;
        
        // Bonus pour les genres orientés gameplay
        if ($genreFactors['action']) $rating += 5;
        if ($genreFactors['fighting']) $rating += 8;
        if ($genreFactors['sports']) $rating += 6;
        if ($genreFactors['racing']) $rating += 7;
        
        // Bonus pour les plateformes modernes
        if ($platformFactors['ps5'] || $platformFactors['xbox']) $rating += 3;
        if ($platformFactors['pc']) $rating += 2;
        
        return $rating;
    }

    /**
     * Calcule la note de gameplay.
     */
    private function calculateGameplayRating(float $baseRating, array $genreFactors, array $categoryFactors): float
    {
        $rating = $baseRating;
        
        // Bonus pour les genres avec gameplay complexe
        if ($genreFactors['rpg']) $rating += 7;
        if ($genreFactors['strategy']) $rating += 8;
        if ($genreFactors['shooter']) $rating += 6;
        if ($genreFactors['puzzle']) $rating += 4;
        
        // Malus pour les DLC/expansions
        if ($categoryFactors['dlc'] || $categoryFactors['expansion']) $rating -= 5;
        
        return $rating;
    }

    /**
     * Calcule la note de musique/OST.
     */
    private function calculateMusiqueRating(float $baseRating, array $genreFactors): float
    {
        $rating = $baseRating;
        
        // Bonus pour les genres avec musique importante
        if ($genreFactors['rpg']) $rating += 10;
        if ($genreFactors['adventure']) $rating += 8;
        if ($genreFactors['racing']) $rating += 6;
        
        // Malus pour les genres moins musicaux
        if ($genreFactors['puzzle']) $rating -= 5;
        if ($genreFactors['simulation']) $rating -= 3;
        
        return $rating;
    }

    /**
     * Calcule la note d'histoire.
     */
    private function calculateHistoireRating(float $baseRating, array $genreFactors): float
    {
        $rating = $baseRating;
        
        // Bonus pour les genres narratifs
        if ($genreFactors['rpg']) $rating += 12;
        if ($genreFactors['adventure']) $rating += 10;
        if ($genreFactors['action']) $rating += 3;
        
        // Malus pour les genres sans histoire
        if ($genreFactors['sports']) $rating -= 8;
        if ($genreFactors['racing']) $rating -= 6;
        if ($genreFactors['puzzle']) $rating -= 10;
        if ($genreFactors['fighting']) $rating -= 5;
        
        return $rating;
    }

    /**
     * Calcule la note de graphisme.
     */
    private function calculateGraphismeRating(float $baseRating, array $platformFactors, array $categoryFactors): float
    {
        $rating = $baseRating;
        
        // Bonus pour les plateformes modernes
        if ($platformFactors['ps5'] || $platformFactors['xbox']) $rating += 8;
        if ($platformFactors['ps4']) $rating += 5;
        if ($platformFactors['pc']) $rating += 6;
        
        // Malus pour les plateformes moins puissantes
        if ($platformFactors['mobile']) $rating -= 10;
        if ($platformFactors['nintendo']) $rating -= 3;
        
        // Malus pour les DLC/expansions (réutilisent les assets)
        if ($categoryFactors['dlc'] || $categoryFactors['expansion']) $rating -= 3;
        
        return $rating;
    }
}
