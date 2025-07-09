<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\GameRepository;
use App\Service\IgdbClient;
use App\Entity\TopYearGames;

class TopYearGamesProvider implements ProviderInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private IgdbClient $igdbClient
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TopYearGames
    {
        $limit = 5;
        if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
            $limit = (int) $_GET['limit'];
        }

        $games = $this->gameRepository->findTopYearGamesDeduplicated($limit, 70, 50);

        $result = [];
        foreach ($games as $game) {
            if ($game->getCoverUrl()) {
                $coverUrl = $game->getCoverUrl();
                if (strpos($coverUrl, '//') === 0) {
                    $coverUrl = 'https:' . $coverUrl;
                } elseif (!preg_match('/^https?:\/\//', $coverUrl)) {
                    $coverUrl = 'https://' . $coverUrl;
                }
                $improvedUrl = $this->igdbClient->improveImageQuality($coverUrl, 't_cover_big');
            } else {
                $improvedUrl = null;
            }
            $result[] = [
                'id' => $game->getId(),
                'title' => $game->getTitle(),
                'name' => $game->getTitle(), // Compatibilité avec le front-end
                'coverUrl' => $improvedUrl,
                'cover' => $improvedUrl ? ['url' => $improvedUrl] : null,
                'releaseDate' => $game->getReleaseDate() ? $game->getReleaseDate()->format('Y-m-d') : null,
                'first_release_date' => $game->getReleaseDate() ? $game->getReleaseDate()->getTimestamp() : null,
                'totalRating' => $game->getTotalRating(),
                'total_rating' => $game->getTotalRating(), // Compatibilité avec le front-end
                'platforms' => $game->getPlatforms() ? array_map(function($platform) {
                    return ['name' => $platform];
                }, $game->getPlatforms()) : [],
                'genres' => $game->getGenres() ? array_map(function($genre) {
                    return ['name' => $genre];
                }, $game->getGenres()) : [],
                'gameModes' => $game->getGameModes() ? array_map(function($mode) {
                    return ['name' => $mode];
                }, $game->getGameModes()) : [],
                'perspectives' => $game->getPerspectives() ? array_map(function($perspective) {
                    return ['name' => $perspective];
                }, $game->getPerspectives()) : [],
            ];
        }

        return new TopYearGames($result);
    }
} 