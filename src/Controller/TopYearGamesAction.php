<?php
// src/Controller/TopYearGamesAction.php

namespace App\Controller;

use App\Repository\GameRepository;
use App\Service\IgdbClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * 🆕 ENDPOINT - TOP 30 JEUX DE L'ANNÉE
 * 
 * Contrôleur qui expose l'endpoint /api/games/top100-year pour récupérer les meilleurs
 * jeux sortis dans les 365 derniers jours avec des critères de qualité très stricts.
 * 
 * 📊 CRITÈRES DE RÉCUPÉRATION :
 * - Période : 365 derniers jours uniquement (calculé dynamiquement)
 * - Note minimum : 75/100 (très bonne qualité)
 * - Votes minimum : 100+ (filtrage au niveau repository)
 * - Tri : Note décroissante → Votes décroissants
 * - Limit par défaut : 5 jeux (paramètre ?limit=X)
 * 
 * 🎯 UTILISATION PRINCIPALE :
 * - 1ER CHOIX du HeroBanner (priorité absolue)
 * - Affiche les hits récents (Clair Obscur, Black Myth Wukong, Silent Hill 2)
 * - Met en avant les nouveautés de qualité avant les classiques
 * 
 * 🌐 ENDPOINT :
 * GET /api/games/top100-year?limit=5
 * 
 * 📝 RÉPONSE :
 * Array de Game objects avec coverUrl améliorée automatiquement
 * 
 * 💡 PERFORMANCE :
 * - Lecture directe en base avec calcul de date dynamique
 * - Amélioration qualité images à la volée
 * - Données plus volatiles (recommandé: cache 1-2h max)
 * 
 * 🔥 AVANTAGE :
 * Privilégie la fraîcheur et l'actualité gaming sur la page d'accueil
 */

final class TopYearGamesAction extends AbstractController
{
    public function __construct(
        private GameRepository $repo,
        private IgdbClient $igdbClient
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 5);

        // Récupère les jeux de l'année (365 derniers jours) avec filtre de qualité (100+ votes)
        $games = $this->repo->findTopYearGames($limit);

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