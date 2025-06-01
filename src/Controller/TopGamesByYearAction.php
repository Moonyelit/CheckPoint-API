<?php
// src/Controller/TopGamesByYearAction.php

namespace App\Controller;

use App\Repository\GameRepository;
use App\Service\IgdbClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class TopGamesByYearAction extends AbstractController
{
    public function __construct(
        private GameRepository $repo,
        private IgdbClient $igdbClient
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $year  = (int) $request->get('year');
        $limit = (int) $request->query->get('limit', 5);

        $games = $this->repo->findTopRatedByYear($year, $limit);

        // Améliore automatiquement la qualité des images pour chaque jeu
        foreach ($games as $game) {
            if ($game->getCoverUrl()) {
                $improvedUrl = $this->igdbClient->improveImageQuality($game->getCoverUrl(), 't_cover_big');
                $game->setCoverUrl($improvedUrl);
            }
        }

        // renvoie sous le context "game:read" pour serialiser coverUrl, totalRating, etc.
        return $this->json($games, 200, [], ['groups' => 'game:read']);
    }
}
