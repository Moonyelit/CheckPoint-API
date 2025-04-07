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

    public function __construct(HttpClientInterface $client, string $clientId, string $clientSecret)
    {
        $this->client = $client;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken && (time() - $this->tokenTimestamp < 3600)) {
            return $this->accessToken;
        }

        $response = $this->client->request('POST', 'https://id.twitch.tv/oauth2/token', [
            'query' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
            ],
        ]);

        $data = $response->toArray();

        $this->accessToken = $data['access_token'];
        $this->tokenTimestamp = time();

        return $this->accessToken;
    }

    public function searchGames(string $search): array
    {
        $accessToken = $this->getAccessToken();
    
        $response = $this->client->request('POST', 'https://api.igdb.com/v4/games', [
            'headers' => [
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'text/plain',
            ],
            'body' => <<<EOT
                fields name, summary, cover.url, first_release_date, genres.name, platforms.name;
                search "$search";
                limit 10;
            EOT
        ]);
    
        return $response->toArray();
    }
    
}
