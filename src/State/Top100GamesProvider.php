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
        $limit = 100; // Récupère tous les jeux du Top 100 par défaut
        if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
            $limit = (int) $_GET['limit'];
        }

        $games = $this->gameRepository->findTop100Games($limit);

        $result = [];
        foreach ($games as $game) {
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
                'totalRating' => $game->getTotalRating(),
                'total_rating' => $game->getTotalRating(), // Compatibilité avec le front-end
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

        return new Top100Games($result);
    }
} 