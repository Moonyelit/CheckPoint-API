<?php
// src/Controller/TrendingGamesAction.php

namespace App\Controller;

use App\Repository\GameRepository;
use App\Service\IgdbClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class TrendingGamesAction extends AbstractController
{
    public function __construct(
        private GameRepository $repo,
        private IgdbClient $igdbClient
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 5);
        
        // Essaie d'abord les jeux récents (moins de 2 ans) avec une bonne note
        $twoYearsAgo = new \DateTimeImmutable('-2 years');
        
        $trendingGames = $this->repo->createQueryBuilder('g')
            ->where('g.releaseDate >= :twoYearsAgo')
            ->andWhere('g.totalRating >= :minRating')
            ->andWhere('g.coverUrl IS NOT NULL')
            ->setParameter('twoYearsAgo', $twoYearsAgo)
            ->setParameter('minRating', 70)
            ->orderBy('g.totalRating', 'DESC')
            ->addOrderBy('g.releaseDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Si pas assez de jeux récents, complète avec les meilleurs jeux de tous les temps
        if (count($trendingGames) < $limit) {
            $needed = $limit - count($trendingGames);
            
            $topGames = $this->repo->createQueryBuilder('g')
                ->where('g.totalRating IS NOT NULL')
                ->andWhere('g.coverUrl IS NOT NULL')
                ->orderBy('g.totalRating', 'DESC')
                ->setMaxResults($needed)
                ->getQuery()
                ->getResult();
                
            // Merge les deux listes en évitant les doublons
            $existingIds = array_map(fn($game) => $game->getId(), $trendingGames);
            foreach ($topGames as $topGame) {
                if (!in_array($topGame->getId(), $existingIds) && count($trendingGames) < $limit) {
                    $trendingGames[] = $topGame;
                }
            }
        }

        // Améliore automatiquement la qualité des images
        foreach ($trendingGames as $game) {
            if ($game->getCoverUrl()) {
                $improvedUrl = $this->igdbClient->improveImageQuality($game->getCoverUrl(), 't_cover_big');
                $game->setCoverUrl($improvedUrl);
            }
        }

        return $this->json($trendingGames, 200, [], ['groups' => 'game:read']);
    }
} 