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
    public function searchGames(string $search, int $limit = 30, int $offset = 0): array
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
                fields name, summary, cover.url, first_release_date, genres.name, platforms.name, screenshots;
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
            fields name, summary, cover.url, first_release_date, genres.name, platforms.name, screenshots, total_rating, involved_companies.company.name;
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
     * Récupère les jeux du Top 100 d'IGDB.
     *
     * Cette méthode récupère les vrais hits récents et AAA classiques :
     * - Jeux très récents (2024-2025) comme Clair Obscur avec critères souples
     * - Jeux récents populaires (2018+) comme Baldur's Gate 3, Elden Ring
     * - AAA classiques avec beaucoup de votes
     *
     * @return array La liste des jeux du top 100.
     */
    public function getTop100Games(): array
    {
        $accessToken = $this->getAccessToken();

        // Calcul des dates (timestamp Unix)
        $year2024 = (new \DateTime('2024-01-01'))->getTimestamp();
        $year2018 = (new \DateTime('2018-01-01'))->getTimestamp();

        // Effectue une requête POST pour récupérer les jeux du top 100
        $response = $this->client->request('POST', 'https://api.igdb.com/v4/games', [
            'headers' => [
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'text/plain',
            ],
            'body' => <<<EOT
            fields name, summary, cover.url, first_release_date, genres.name, platforms.name, screenshots, total_rating, total_rating_count, involved_companies.company.name;
            sort total_rating desc;
            where (first_release_date >= $year2024 & total_rating >= 80 & total_rating_count >= 5) | (first_release_date >= $year2018 & total_rating >= 88 & total_rating_count >= 50) | (total_rating >= 85 & total_rating_count >= 1000);
            limit 100;
            EOT
        ]);

        $games = $response->toArray();
        
        // Améliore la qualité des images de couverture
        foreach ($games as &$game) {
            if (isset($game['cover']['url'])) {
                $game['cover']['url'] = $this->improveImageQuality($game['cover']['url'], 't_cover_big');
            }
        }

        return $games;
    }

    /**
     * Récupère les meilleurs jeux sortis dans les 365 derniers jours.
     *
     * Cette méthode récupère les jeux récents les mieux notés :
     * - Jeux sortis dans les 365 derniers jours
     * - Note minimum 75/100 et au moins 10 votes
     * - Triés par note décroissante
     *
     * @return array La liste des jeux de l'année.
     */
    public function getTopYearGames(): array
    {
        $accessToken = $this->getAccessToken();

        // Calcul de la date pour les 365 derniers jours (timestamp Unix)
        $oneYearAgo = (new \DateTime('-365 days'))->getTimestamp();
        $now = (new \DateTime())->getTimestamp();

        // Effectue une requête POST pour récupérer les jeux de l'année
        $response = $this->client->request('POST', 'https://api.igdb.com/v4/games', [
            'headers' => [
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'text/plain',
            ],
            'body' => <<<EOT
            fields name, summary, cover.url, first_release_date, genres.name, platforms.name, screenshots, total_rating, total_rating_count, involved_companies.company.name;
            sort total_rating desc;
            where first_release_date >= $oneYearAgo & first_release_date <= $now & total_rating >= 75 & total_rating_count >= 10;
            limit 50;
            EOT
        ]);

        $games = $response->toArray();
        
        // Améliore la qualité des images de couverture
        foreach ($games as &$game) {
            if (isset($game['cover']['url'])) {
                $game['cover']['url'] = $this->improveImageQuality($game['cover']['url'], 't_cover_big');
            }
        }

        return $games;
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
}
