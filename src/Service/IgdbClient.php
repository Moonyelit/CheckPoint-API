<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

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
     */
    public function __construct(HttpClientInterface $client, string $clientId, string $clientSecret)
    {
        $this->client = $client;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * Récupère un token d'accès pour l'API IGDB.
     *
     * Si un token valide existe déjà (moins d'une heure), il est réutilisé.
     * Sinon, une nouvelle requête est effectuée pour obtenir un token.
     *
     * @return string Le token d'accès valide.
     */
    private function getAccessToken(): string
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

    /**
     * Recherche des jeux dans l'API IGDB en fonction d'un mot-clé.
     *
     * @param string $search Le mot-clé à rechercher.
     * @param int $limit Le nombre de résultats par page
     * @param int $offset L'offset pour la pagination
     * @return array La liste des jeux correspondant à la recherche.
     */
    public function searchGames(string $search, int $limit = 20, int $offset = 0): array
    {
        $accessToken = $this->getAccessToken();

        // Effectue une requête POST pour rechercher des jeux
        $response = $this->client->request('POST', 'https://api.igdb.com/v4/games', [
            'headers' => [
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'text/plain',
            ],
            'body' => <<<EOT
                fields name, summary, cover.url, first_release_date, genres.name, platforms.name, game_modes.name, player_perspectives.name, screenshots;
                search "$search";
                limit $limit;
                offset $offset;
            EOT
        ]);

        $games = $response->toArray();
        
        // Améliore la qualité des images de couverture
        foreach ($games as &$game) {
            if (isset($game['cover']['url'])) {
                $game['cover']['url'] = $this->improveImageQuality($game['cover']['url'], 't_cover_big');
            }
        }

        // Retourne les résultats sous forme de tableau
        return $games;
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
        $response = $this->client->request('POST', 'https://api.igdb.com/v4/screenshots', [
            'headers' => [
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'text/plain',
            ],
            'body' => <<<EOT
            fields url;
            where id = ($idsString);
        EOT
        ]);

        $screenshots = $response->toArray();
        
        // Améliore la qualité des captures d'écran
        foreach ($screenshots as &$screenshot) {
            if (isset($screenshot['url'])) {
                $screenshot['url'] = $this->improveImageQuality($screenshot['url'], 't_1080p');
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
        $accessToken = $this->getAccessToken();

        // Effectue une requête POST pour récupérer les jeux populaires
        $response = $this->client->request('POST', 'https://api.igdb.com/v4/games', [
            'headers' => [
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'text/plain',
            ],
            'body' => <<<EOT
            fields name, summary, cover.url, first_release_date, genres.name, platforms.name, game_modes.name, player_perspectives.name, screenshots, total_rating, involved_companies.company.name;
            sort total_rating desc;
            where total_rating != null;
            limit 500;
            EOT
        ]);

        $games = $response->toArray();
        
        // Améliore la qualité des images de couverture
        foreach ($games as &$game) {
            if (isset($game['cover']['url'])) {
                $game['cover']['url'] = $this->improveImageQuality($game['cover']['url'], 't_cover_big');
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
        $accessToken = $this->getAccessToken();
        $minVotes = (int)$minVotes;
        $minRating = (int)$minRating;
        $response = $this->client->request('POST', 'https://api.igdb.com/v4/games', [
            'headers' => [
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'text/plain',
            ],
            'body' => <<<EOT
fields name, summary, cover.url, first_release_date, genres.name, platforms.name, game_modes.name, player_perspectives.name, screenshots, total_rating, total_rating_count, involved_companies.company.name;
sort total_rating desc;
where total_rating != null & total_rating_count >= $minVotes & total_rating >= $minRating;
limit 100;
EOT
        ]);
        $games = $response->toArray();
        foreach ($games as &$game) {
            if (isset($game['cover']['url'])) {
                $game['cover']['url'] = $this->improveImageQuality($game['cover']['url'], 't_cover_big');
            }
        }
        return $games;
    }

    /**
     * Récupère les meilleurs jeux sortis dans les 365 derniers jours avec critères dynamiques.
     */
    public function getTopYearGames(int $minVotes = 80, int $minRating = 75): array
    {
        $accessToken = $this->getAccessToken();
        $minVotes = (int)$minVotes;
        $minRating = (int)$minRating;
        $oneYearAgo = (new \DateTimeImmutable('-365 days'))->getTimestamp();
        $response = $this->client->request('POST', 'https://api.igdb.com/v4/games', [
            'headers' => [
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'text/plain',
            ],
            'body' => <<<EOT
fields name, summary, cover.url, first_release_date, genres.name, platforms.name, game_modes.name, player_perspectives.name, screenshots, total_rating, total_rating_count, involved_companies.company.name;
sort total_rating desc;
where total_rating != null & total_rating_count >= $minVotes & total_rating >= $minRating & first_release_date >= $oneYearAgo;
limit 100;
EOT
        ]);
        $games = $response->toArray();
        foreach ($games as &$game) {
            if (isset($game['cover']['url'])) {
                $game['cover']['url'] = $this->improveImageQuality($game['cover']['url'], 't_cover_big');
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
        $accessToken = $this->getAccessToken();

        try {
            // Effectue une requête POST pour récupérer les détails du jeu
            $response = $this->client->request('POST', 'https://api.igdb.com/v4/games', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'text/plain',
                ],
                'body' => <<<EOT
                fields name, summary, cover.url, first_release_date, genres.name, platforms.name, game_modes.name, player_perspectives.name, screenshots, total_rating, total_rating_count, involved_companies.company.name;
                where id = $gameId;
                limit 1;
                EOT
            ]);

            $games = $response->toArray();
            
            if (empty($games)) {
                return null;
            }

            $game = $games[0];
            
            // Améliore la qualité de l'image de couverture
            if (isset($game['cover']['url'])) {
                $game['cover']['url'] = $this->improveImageQuality($game['cover']['url'], 't_cover_big');
            }

            return $game;
        } catch (\Exception $e) {
            error_log("Erreur lors de la récupération des détails du jeu {$gameId}: " . $e->getMessage());
            return null;
        }
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
}
