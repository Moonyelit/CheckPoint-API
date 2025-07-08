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
                'totalRating' => $game->getTotalRating(),
                'platforms' => $game->getPlatforms(),
                'coverUrl' => $coverUrl,
            ];
        }

        return new Top100Games($result);
    }
} 