<?php

namespace App\Controller\Api;

use App\Repository\GameRepository;
use App\Service\IgdbClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class Top100GamesController extends AbstractController
{
    public function __invoke(Request $request, GameRepository $gameRepository, IgdbClient $igdbClient): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 5);
        
        // Récupère les jeux du Top 100
        $games = $gameRepository->findTop100Games($limit);
        
        // Améliore automatiquement la qualité des images pour chaque jeu
        foreach ($games as $game) {
            if ($game->getCoverUrl()) {
                // S'assurer que l'URL a le bon format
                $coverUrl = $game->getCoverUrl();
                if (strpos($coverUrl, '//') === 0) {
                    $coverUrl = 'https:' . $coverUrl;
                } elseif (!preg_match('/^https?:\/\//', $coverUrl)) {
                    $coverUrl = 'https://' . $coverUrl;
                }
                
                $improvedUrl = $igdbClient->improveImageQuality($coverUrl, 't_cover_big');
                $game->setCoverUrl($improvedUrl);
            }
        }

        return $this->json($games, 200, [], ['groups' => 'game:read']);
    }
} 