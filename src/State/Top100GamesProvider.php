<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\GameRepository;
use App\Service\IgdbClient;
use App\Entity\Top100Games;

class Top100GamesProvider implements ProviderInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private IgdbClient $igdbClient
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Top100Games
    {
        // Récupération des critères depuis les paramètres de requête
        $minVotes = isset($_GET['minVotes']) && is_numeric($_GET['minVotes']) ? (int) $_GET['minVotes'] : 200; // Augmenté de 80 à 200
        $minRating = isset($_GET['minRating']) && is_numeric($_GET['minRating']) ? (int) $_GET['minRating'] : 80; // Augmenté de 75 à 80
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int) $_GET['limit'] : 100;
        
        // Log détaillé des paramètres GET reçus
        error_log("Top100GamesProvider - Paramètres GET reçus: " . json_encode($_GET));
        error_log("Top100GamesProvider - Critères appliqués: minVotes=$minVotes, minRating=$minRating, limit=$limit");
        
        // Critères appliqués
        $criteria = [
            'minVotes' => $minVotes,
            'minRating' => $minRating,
            'limit' => $limit
        ];

        // Récupération des jeux avec critères
        $games = $this->gameRepository->findTopGamesWithCriteria($minVotes, $minRating, $limit);
        $totalCount = $this->gameRepository->countTopGamesWithCriteria($minVotes, $minRating);

        // Log des résultats
        error_log("Top100GamesProvider - Jeux trouvés: " . count($games) . ", Total count: $totalCount");

        $result = [];
        foreach ($games as $game) {
            // Log des données de chaque jeu pour debug
            error_log("Top100GamesProvider - Jeu: {$game->getTitle()}, Note: {$game->getTotalRating()}, Votes: {$game->getTotalRatingCount()}, Critères respectés: Note>={$minRating}=" . ($game->getTotalRating() >= $minRating ? 'OUI' : 'NON') . ", Votes>={$minVotes}=" . ($game->getTotalRatingCount() >= $minVotes ? 'OUI' : 'NON'));
            
            // Amélioration de la qualité de l'image de couverture
            $coverUrl = null;
            if ($game->getCoverUrl()) {
                $originalCoverUrl = $game->getCoverUrl();
                if (strpos($originalCoverUrl, '//') === 0) {
                    $originalCoverUrl = 'https:' . $originalCoverUrl;
                } elseif (!preg_match('/^https?:\/\//', $originalCoverUrl)) {
                    $originalCoverUrl = 'https://' . $originalCoverUrl;
                }
                $coverUrl = $this->igdbClient->improveImageQuality($originalCoverUrl, 't_cover_big');
            }

            $result[] = [
                'id' => $game->getId(),
                'title' => $game->getTitle(),
                'name' => $game->getTitle(), // Compatibilité avec le front-end
                'slug' => $game->getSlug(), // Ajout du slug pour la navigation
                'totalRating' => $game->getTotalRating(),
                'total_rating' => $game->getTotalRating(), // Compatibilité avec le front-end
                'totalRatingCount' => $game->getTotalRatingCount(),
                'total_rating_count' => $game->getTotalRatingCount(), // Compatibilité avec le front-end
                'platforms' => $game->getPlatforms() ? array_map(function($platform) {
                    return ['name' => $platform];
                }, $game->getPlatforms()) : [],
                'coverUrl' => $coverUrl,
                'cover' => $coverUrl ? ['url' => $coverUrl] : null,
                'genres' => $game->getGenres() ? array_map(function($genre) {
                    return ['name' => $genre];
                }, $game->getGenres()) : [],
                'gameModes' => $game->getGameModes() ? array_map(function($mode) {
                    return ['name' => $mode];
                }, $game->getGameModes()) : [],
                'perspectives' => $game->getPerspectives() ? array_map(function($perspective) {
                    return ['name' => $perspective];
                }, $game->getPerspectives()) : [],
                'releaseDate' => $game->getReleaseDate() ? $game->getReleaseDate()->format('Y-m-d') : null,
                'first_release_date' => $game->getReleaseDate() ? $game->getReleaseDate()->getTimestamp() : null,
            ];
        }

        return new Top100Games($result, $criteria, $totalCount);
    }
} 