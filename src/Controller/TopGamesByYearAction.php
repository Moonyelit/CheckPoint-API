<?php
// src/Controller/TopGamesByYearAction.php

namespace App\Controller;

use App\Repository\GameRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class TopGamesByYearAction extends AbstractController
{
    public function __construct(private GameRepository $repo) {}

    public function __invoke(Request $request): JsonResponse
    {
        $year  = (int) $request->get('year');
        $limit = (int) $request->query->get('limit', 5);

        $games = $this->repo->findTopRatedByYear($year, $limit);

        // renvoie sous le context "game:read" pour serialiser coverUrl, totalRating, etc.
        return $this->json($games, 200, [], ['groups' => 'game:read']);
    }
}
