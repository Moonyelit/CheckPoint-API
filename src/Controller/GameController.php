<?php

namespace App\Controller;

use App\Service\IgdbClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GameController extends AbstractController
{
    #[Route('/api/games/search/{name}', name: 'api_game_search')]
    public function search(string $name, IgdbClient $igdb): JsonResponse
    {
        $games = $igdb->searchGames($name);
        return $this->json($games);
    }

    // Début du code temporaire
    #[Route('/games/search/{query}', name: 'games_search')]
    public function searchView(string $query, IgdbClient $igdb): Response
    {
        $games = $igdb->searchGames($query);

        return $this->render('games/search.html.twig', [
            'games' => $games,
            'query' => $query,
        ]);
    }
    // Fin du code temporaire
}
