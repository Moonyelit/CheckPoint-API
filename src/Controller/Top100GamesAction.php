<?php
// src/Controller/Top100GamesAction.php

namespace App\Controller;

use App\Repository\GameRepository;
use App\Service\IgdbClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * 🏆 ENDPOINT - TOP 100 JEUX DE TOUS LES TEMPS
 * 
 * Contrôleur qui expose l'endpoint /api/games/top100 pour récupérer les meilleurs
 * jeux de tous les temps avec des critères de qualité stricts.
 * 
 * 📊 CRITÈRES DE RÉCUPÉRATION :
 * - Note minimum : Déterminée par la base (importée avec critères stricts)
 * - Votes minimum : 50+ (filtrage au niveau repository)
 * - Tri : Note décroissante → Votes décroissants → Follows décroissants
 * - Limit par défaut : 5 jeux (paramètre ?limit=X)
 * 
 * 🎯 UTILISATION PRINCIPALE :
 * - 2ème choix du HeroBanner (fallback après jeux de l'année)
 * - Garantit l'affichage de classiques AAA (GTA V, Skyrim, Cyberpunk)
 * - Stable et fiable (change peu dans le temps)
 * 
 * 🌐 ENDPOINT :
 * GET /api/games/top100?limit=5
 * 
 * 📝 RÉPONSE :
 * Array de Game objects avec coverUrl améliorée automatiquement
 * 
 * 💡 PERFORMANCE :
 * - Lecture directe en base (pas d'appel API IGDB)
 * - Amélioration qualité images à la volée
 * - Cache recommandé côté front-end
 */

final class Top100GamesAction extends AbstractController
{
    public function __construct(
        private GameRepository $repo,
        private IgdbClient $igdbClient
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 5);

        // Récupère les jeux du Top 100 avec filtre de qualité (50+ votes)
        $games = $this->repo->findTop100Games($limit);

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