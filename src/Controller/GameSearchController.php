<?php

namespace App\Controller;

use App\Repository\GameRepository;
use App\Service\GameImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GameSearchController extends AbstractController
{
    #[Route('/games/search/{query}', name: 'games_search')]
    public function search(
        string $query,
        GameRepository $gameRepository,
        GameImporter $gameImporter
    ): Response {
        $cache = new FilesystemAdapter();

        // Clé de cache basée sur la requête utilisateur
        $cacheKey = 'search_' . md5($query);

        // Vérifie si la recherche a été faite récemment
        $cachedGames = $cache->getItem($cacheKey);

        if (!$cachedGames->isHit()) {
            // Recherche locale
            $games = $gameRepository->findByTitleLike($query);

            // Si résultats trop faibles, on complète avec l'import IGDB
            if (count($games) === 0) {
                try {
                    $importedGames = $gameImporter->importGamesBySearch($query);
                    $games = array_merge($games, $importedGames);
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Erreur lors de la récupération des jeux IGDB.');
                }
            }

            // Sauvegarde en cache pour 1h
            $cachedGames->set($games);
            $cachedGames->expiresAfter(3600); // 1h
            $cache->save($cachedGames);
        } else {
            $games = $cachedGames->get();
        }

        // Affichage des résultats
        return $this->render('games/search.html.twig', [
            'games' => $games,
            'query' => $query,
        ]);
    }
}
