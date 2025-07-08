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

        $games = $this->gameRepository->findTopYearGamesDeduplicated($limit, 80, 80);

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
                'coverUrl' => $improvedUrl,
                'releaseDate' => $game->getReleaseDate() ? $game->getReleaseDate()->format('Y-m-d') : null,
            ];
        }

        return new TopYearGames($result);
    }
} 