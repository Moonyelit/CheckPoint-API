<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

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
     * @return array La liste des jeux correspondant à la recherche.
     */
    public function searchGames(string $search, int $limit = 50): array
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
            EOT
        ]);

        // Retourne les résultats sous forme de tableau
        return $response->toArray();
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

        // Retourne les résultats sous forme de tableau
        return $response->toArray();
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

        // Retourne les résultats sous forme de tableau
        return $response->toArray();
    }
}
