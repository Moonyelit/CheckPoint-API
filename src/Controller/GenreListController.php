<?php

namespace App\Controller;

use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class GenreListController extends AbstractController
{
    #[Route('/api/genres', name: 'api_genres', methods: ['GET'])]
    public function __invoke(GameRepository $gameRepository): JsonResponse
    {
        $allGenres = $gameRepository->createQueryBuilder('g')
            ->select('g.genres')
            ->getQuery()
            ->getResult();

        $flatGenres = array_unique(array_merge(...array_filter(array_map(fn($g) => $g['genres'] ?? [], $allGenres))));
        sort($flatGenres);

        // Tableau de correspondance anglais => français
        $translations = [
            'Action' => 'Action',
            'Adventure' => 'Aventure',
            'Action-adventure' => 'Action-aventure',
            'RPG' => 'RPG',
            'Strategy' => 'Stratégie',
            'Simulation' => 'Simulation',
            'Puzzle' => 'Puzzle',
            'Racing' => 'Course',
            'Sports' => 'Sport',
            'Fighting' => 'Combat',
            'Shooter' => 'Tir',
            'Platform' => 'Plateforme',
            'Point-and-click' => 'Point-and-click',
            'Visual Novel' => 'Visual Novel',
            'Indie' => 'Indépendant',
            'Arcade' => 'Arcade',
            'Card & Board Game' => 'Jeu de cartes & plateau',
            'MOBA' => 'MOBA',
            'Music' => 'Musique',
            'Quiz/Trivia' => 'Quiz',
            'Hack and slash/Beat em up' => 'Beat them all',
            'Tactical' => 'Tactique',
            'Pinball' => 'Flipper',
            'MMORPG' => 'MMORPG',
            'MMO' => 'MMO',
            'Shooter' => 'Tir',
            'Platformer' => 'Plateforme',
            // Ajoute d'autres traductions si besoin
        ];

        $translated = array_map(fn($g) => $translations[$g] ?? $g, $flatGenres);
        sort($translated);

        return $this->json($translated);
    }
} 