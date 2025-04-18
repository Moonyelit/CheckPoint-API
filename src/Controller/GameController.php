<?php

namespace App\Controller;

use App\Service\IgdbClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

use App\Service\GameImporter;
use App\Repository\GameRepository;
use Symfony\Component\HttpFoundation\Request;

class GameController extends AbstractController
{
    private LimiterInterface $limiter;

    public function __construct(
        #[Autowire(service: 'limiter.apiSearchLimit')] RateLimiterFactory $apiSearchLimitFactory
    ) {
        $this->limiter = $apiSearchLimitFactory->create(); // Crée une instance de LimiterInterface
    }

    #[Route('/api/games/search/{name}', name: 'api_game_search')]
    public function search(string $name, IgdbClient $igdb): JsonResponse
    {
        // Limite les requêtes API.
        $limit = $this->limiter->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException('Trop de requêtes.');
        }

        // Recherche des jeux via IGDB.
        $games = $igdb->searchGames($name);
        return $this->json($games);
    }

    #[Route('/games/search/{query}', name: 'games_search')]
    public function searchView(string $query, IgdbClient $igdb): Response
    {
        // Recherche des jeux et rend une vue Twig.
        $games = $igdb->searchGames($query);

        return $this->render('games/search.html.twig', [
            'games' => $games,
            'query' => $query,
        ]);
    }

    #[Route('/admin/import-popular-games', name: 'admin_import_popular_games')]
    public function importPopularGames(GameImporter $importer): Response
    {
        // Importe les jeux populaires.
        $importer->importPopularGames();
    
        return new Response('Import terminé !');
    }

    #[Route('/api/games/search-or-import/{query}', name: 'api_game_search_or_import')]
    public function searchGame(string $query, GameImporter $gameImporter, GameRepository $gameRepository): JsonResponse
    {
        // Recherche des jeux dans la base de données
        $games = $gameRepository->findByTitleLike($query);

        // Si aucun jeu n'est trouvé, tente d'importer depuis IGDB
        if (count($games) === 0) {
            $newGame = $gameImporter->importGameBySearch($query);
            if ($newGame) {
                $games[] = $newGame;
            }
        }

        // Retourne les jeux trouvés ou importés
        return $this->json($games, 200, [], ['groups' => 'game:read']);
    }

}
