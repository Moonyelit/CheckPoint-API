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
use Psr\Log\LoggerInterface;

use App\Service\GameImporter;
use App\Repository\GameRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Service\GameSearchService;

/**
 * 🎮 CONTRÔLEUR PRINCIPAL - GESTION GLOBALE DES JEUX
 * 
 * Ce contrôleur central gère toutes les opérations principales liées aux jeux :
 * recherche, import, gestion des images, et routes d'administration.
 * 
 * 🔧 FONCTIONNALITÉS PRINCIPALES :
 * 
 * 🔍 RECHERCHE :
 * - /api/games/search/{name} : Recherche API avec rate limiting
 * - /games/search/{query} : Vue Twig pour affichage frontend
 * - /api/games/search-or-import/{query} : Recherche intelligente avec import auto
 * 
 * 📥 IMPORT ADMIN :
 * - /admin/import-popular-games : Import jeux populaires
 * - /admin/import-top100-games : Import Top 100 IGDB
 * - /admin/import-top-year-games : Import jeux de l'année
 * 
 * 🖼️ GESTION IMAGES :
 * - /api/games/improve-image-quality : API amélioration qualité
 * - /admin/update-existing-images : Mise à jour batch des images
 * 
 * 🔒 SÉCURITÉ :
 * - Rate limiting sur les recherches API (évite spam)
 * - Routes admin protégées par rôles
 * - Gestion d'erreurs robuste avec fallbacks
 * 
 * 🎯 UTILISATION :
 * - Point central pour toutes les opérations sur les jeux
 * - Interface entre frontend et services métier
 * - Routes d'administration pour la maintenance
 * 
 * 💡 ARCHITECTURE :
 * - Injection de dépendances (IgdbClient, GameImporter, etc.)
 * - Délégation vers services spécialisés
 * - Réponses JSON pour API, vues Twig pour pages
 */

class GameController extends AbstractController
{
    private LimiterInterface $limiter;
    private LoggerInterface $logger;

    public function __construct(
        #[Autowire(service: 'limiter.apiSearchLimit')] RateLimiterFactory $apiSearchLimitFactory,
        private GameSearchService $gameSearchService,
        LoggerInterface $logger
    ) {
        $this->limiter = $apiSearchLimitFactory->create(); // Crée une instance de LimiterInterface
        $this->logger = $logger;
    }

    #[Route('/api/games/search/{name}', name: 'api_game_search')]
    public function search(string $name, Request $request): JsonResponse
    {
        // Limite les requêtes API.
        $limit = $this->limiter->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException('Trop de requêtes.');
        }

        // Récupère les paramètres de pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = (int) $request->query->get('limit', 20);

        try {
            // Utilise le GameSearchService pour une recherche avec fallback
            $result = $this->gameSearchService->searchWithFallback($name);
            
            // Applique la pagination côté serveur
            $games = $result['games'];
            $totalCount = count($games);
            $offset = ($page - 1) * $limit;
            $paginatedGames = array_slice($games, $offset, $limit);
            
            return $this->json([
                'games' => $paginatedGames,
                'pagination' => [
                    'currentPage' => $page,
                    'limit' => $limit,
                    'offset' => $offset,
                    'totalCount' => $totalCount
                ],
                'source' => $result['source'],
                'message' => $result['message']
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("Erreur lors de la recherche pour '{$name}': " . $e->getMessage());
            
            return $this->json([
                'games' => [],
                'pagination' => [
                    'currentPage' => $page,
                    'limit' => $limit,
                    'offset' => 0,
                    'totalCount' => 0
                ],
                'error' => 'Erreur lors de la recherche',
                'message' => 'Impossible de récupérer les résultats'
            ], 500);
        }
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

    #[Route('/admin/import-top100-games', name: 'admin_import_top100_games')]
    public function importTop100Games(GameImporter $importer): Response
    {
        // Importe les jeux du Top 100 d'IGDB.
        $importer->importTop100Games();
    
        return new Response('Import du Top 100 IGDB terminé !');
    }

    #[Route('/admin/import-top-year-games', name: 'admin_import_top_year_games')]
    public function importTopYearGames(GameImporter $importer): Response
    {
        // Importe les meilleurs jeux de l'année (365 derniers jours).
        $count = $importer->importTopYearGames();
    
        return new Response("Import des jeux de l'année terminé ! {$count} jeux traités.");
    }

    #[Route('/api/games/search-or-import/{query}', name: 'api_game_search_or_import')]
    public function searchGame(string $query, GameImporter $gameImporter, GameRepository $gameRepository): JsonResponse
    {
        $this->logger->info("Recherche intelligente pour : '{$query}'");

        // 1. On cherche d'abord dans notre base de données (priorité aux jeux locaux)
        $localGames = $gameRepository->findByTitleLike($query);
        $this->logger->info(sprintf("Trouvé %d jeux en base locale", count($localGames)));

        // 2. On essaie d'importer depuis IGDB pour enrichir les résultats
        $importedGames = [];
        try {
            $importedGames = $gameImporter->importGamesBySearch($query);
            $this->logger->info(sprintf("Importé %d jeux depuis IGDB", count($importedGames)));
        } catch (\Throwable $e) {
            $this->logger->error("Erreur lors de l'import depuis IGDB: " . $e->getMessage());
            // Si l'import échoue, on renvoie au moins les résultats locaux
            if (!empty($localGames)) {
                return $this->json($localGames, 200, [], ['groups' => 'game:read']);
            }
            return $this->json(['error' => 'Aucun jeu trouvé localement et erreur lors de l\'import IGDB'], 404);
        }

        // 3. Fusion intelligente : priorité aux jeux locaux, puis ajout des nouveaux
        $finalGames = [];
        $localGameIds = array_map(fn($game) => $game->getIgdbId(), $localGames);
        
        // Ajoute d'abord tous les jeux locaux
        foreach ($localGames as $localGame) {
            $finalGames[] = $localGame;
        }
        
        // Ajoute les jeux importés qui ne sont pas déjà en local
        foreach ($importedGames as $importedGame) {
            if (!in_array($importedGame->getIgdbId(), $localGameIds)) {
                $finalGames[] = $importedGame;
            }
        }

        $this->logger->info(sprintf("Résultat final : %d jeux (local: %d, importé: %d)", 
            count($finalGames), count($localGames), count($importedGames) - count($localGames)));

        return $this->json($finalGames, 200, [], ['groups' => 'game:read']);
    }

    #[Route('/api/games/improve-image-quality', name: 'api_improve_image_quality', methods: ['POST'])]
    public function improveImageQuality(Request $request, IgdbClient $igdb): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['imageUrl'])) {
            return $this->json(['error' => 'imageUrl est requis'], 400);
        }
        
        $originalUrl = $data['imageUrl'];
        $size = $data['size'] ?? 't_1080p'; // taille par défaut
        
        $improvedUrl = $igdb->improveImageQuality($originalUrl, $size);
        
        return $this->json([
            'originalUrl' => $originalUrl,
            'improvedUrl' => $improvedUrl,
            'availableSizes' => [
                't_cover_small' => 'Petite couverture (90x128)',
                't_cover_big' => 'Grande couverture (264x374)', 
                't_720p' => 'HD 720p',
                't_1080p' => 'Full HD 1080p',
                't_original' => 'Taille originale'
            ]
        ]);
    }

    #[Route('/admin/update-existing-images', name: 'admin_update_existing_images')]
    public function updateExistingImages(GameRepository $gameRepository, IgdbClient $igdb): Response
    {
        // Récupère tous les jeux avec une coverUrl
        $games = $gameRepository->createQueryBuilder('g')
            ->where('g.coverUrl IS NOT NULL')
            ->andWhere('g.coverUrl != :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();

        $updatedCount = 0;

        foreach ($games as $game) {
            $originalUrl = $game->getCoverUrl();
            
            // Vérifie si l'image n'est pas déjà en haute qualité
            if (strpos($originalUrl, 't_cover_big') === false && 
                strpos($originalUrl, 't_1080p') === false && 
                strpos($originalUrl, 't_original') === false) {
                
                $improvedUrl = $igdb->improveImageQuality($originalUrl, 't_cover_big');
                
                if ($improvedUrl !== $originalUrl) {
                    $game->setCoverUrl($improvedUrl);
                    $game->setUpdatedAt(new \DateTimeImmutable());
                    $updatedCount++;
                }
            }
        }

        // Sauvegarde en base
        $gameRepository->getEntityManager()->flush();

        return new Response("Mise à jour terminée ! {$updatedCount} images améliorées.");
    }

    #[Route('/api/custom/games/top100', name: 'api_games_top100')]
    public function getTop100Games(Request $request, GameRepository $gameRepository, IgdbClient $igdbClient): JsonResponse
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

    #[Route('/api/custom/games/year/top100', name: 'api_games_top100_year')]
    public function getTopYearGames(Request $request, GameRepository $gameRepository, IgdbClient $igdbClient): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 5);
        
        // Récupère les jeux de l'année (365 derniers jours) avec critères stricts
        $games = $gameRepository->findTopYearGamesWithCriteria($limit, 80, 80);
        
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

    #[Route('/api/games/{slug}', name: 'api_game_details', priority: -1)]
    public function getGameBySlug(string $slug, GameRepository $gameRepository, IgdbClient $igdbClient): JsonResponse
    {
        $this->logger->info("Recherche du jeu avec le slug : '{$slug}'");

        // Test direct en base de données
        $connection = $gameRepository->getEntityManager()->getConnection();
        $stmt = $connection->prepare('SELECT COUNT(*) as count FROM game WHERE slug = ?');
        $result = $stmt->executeQuery([$slug]);
        $count = $result->fetchAssociative()['count'];
        
        $this->logger->info("Nombre de jeux trouvés en base avec le slug '{$slug}' : {$count}");

        $game = $gameRepository->findOneBy(['slug' => $slug]);

        if (!$game) {
            $this->logger->warning("Jeu non trouvé pour le slug : '{$slug}'");
            
            // Log tous les slugs disponibles pour debug
            $allSlugs = $gameRepository->createQueryBuilder('g')
                ->select('g.slug, g.title')
                ->where('g.slug IS NOT NULL')
                ->andWhere('g.slug != :empty')
                ->setParameter('empty', '')
                ->getQuery()
                ->getResult();
            
            $this->logger->info("Slugs disponibles : " . json_encode(array_slice($allSlugs, 0, 10)));
            
            return $this->json(['message' => 'Jeu non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Améliorer la qualité de l'image de couverture si nécessaire
        if ($game->getCoverUrl()) {
            $originalCoverUrl = $game->getCoverUrl();
            $improvedCoverUrl = $igdbClient->improveImageQuality($originalCoverUrl, 't_cover_big');
            
            if ($improvedCoverUrl !== $originalCoverUrl) {
                $game->setCoverUrl($improvedCoverUrl);
                $game->setUpdatedAt(new \DateTimeImmutable());
                
                // Sauvegarder l'amélioration en base
                $gameRepository->getEntityManager()->persist($game);
                $gameRepository->getEntityManager()->flush();
                
                $this->logger->info("Image de couverture améliorée pour '{$game->getTitle()}'");
            }
        }

        // Récupérer le premier screenshot pour le champ firstScreenshotUrl
        $screenshots = $game->getScreenshots();
        $firstScreenshotUrl = null;
        if ($screenshots->count() > 0) {
            $firstScreenshot = $screenshots->first();
            $firstScreenshotUrl = $igdbClient->improveImageQuality($firstScreenshot->getImage(), 't_1080p');
        }

        // Préparer les données de réponse avec tous les champs nécessaires
        $gameData = [
            'id' => $game->getId(),
            'title' => $game->getTitle(),
            'slug' => $game->getSlug(),
            'coverUrl' => $game->getCoverUrl(),
            'summary' => $game->getSummary(),
            'totalRating' => $game->getTotalRating(),
            'platforms' => $game->getPlatforms(),
            'genres' => $game->getGenres(),
            'developer' => $game->getDeveloper(),
            'releaseDate' => $game->getReleaseDate() ? $game->getReleaseDate()->format('Y-m-d') : null,
            'gameModes' => $game->getGameModes(),
            'perspectives' => $game->getPerspectives(),
            'year' => $game->getReleaseDate() ? (int) $game->getReleaseDate()->format('Y') : null,
            'studio' => $game->getDeveloper(), // Alias pour compatibilité
            'backgroundUrl' => $game->getCoverUrl(), // Fallback sur la couverture
            'firstScreenshotUrl' => $firstScreenshotUrl,
            'synopsis' => $game->getSummary(), // Alias pour compatibilité
            'playerPerspective' => $game->getPerspectives() ? implode(', ', $game->getPerspectives()) : null,
            'publisher' => $game->getDeveloper(), // Fallback sur le développeur
            'igdbId' => $game->getIgdbId(),
            'series' => null, // À implémenter si nécessaire
            'titles' => $game->getTitle(), // Alias pour compatibilité
            'releaseDates' => [], // À implémenter si nécessaire
            'ageRatings' => null, // À implémenter si nécessaire
            'stats' => null, // À implémenter si nécessaire
            'createdAt' => $game->getCreatedAt() ? $game->getCreatedAt()->format('Y-m-d H:i:s') : null,
            'updatedAt' => $game->getUpdatedAt() ? $game->getUpdatedAt()->format('Y-m-d H:i:s') : null
        ];

        $this->logger->info("Jeu trouvé : '{$game->getTitle()}' pour le slug '{$slug}'");
        return $this->json($gameData, Response::HTTP_OK);
    }

    #[Route('/api/games/search-with-fallback/{query}', name: 'api_game_search_with_fallback')]
    public function searchWithFallback(string $query, Request $request): JsonResponse
    {
        $forceIgdb = $request->query->get('force_igdb', false);
        $result = $this->gameSearchService->searchWithFallback($query, $forceIgdb);
        
        $statusCode = $result['source'] === 'error' ? 500 : 
                     ($result['total'] === 0 ? 404 : 200);
        
        return $this->json($result, $statusCode, [], ['groups' => 'game:read']);
    }

    #[Route('/api/games/search-local/{query}', name: 'api_game_search_local')]
    public function searchLocal(string $query): JsonResponse
    {
        $result = $this->gameSearchService->searchLocal($query);
        
        $statusCode = $result['total'] === 0 ? 404 : 200;
        return $this->json($result, $statusCode, [], ['groups' => 'game:read']);
    }

    #[Route('/api/games/search-igdb/{query}', name: 'api_game_search_igdb')]
    public function searchIgdb(string $query): JsonResponse
    {
        $result = $this->gameSearchService->searchIgdb($query);
        
        $statusCode = $result['source'] === 'error' ? 500 : 
                     ($result['total'] === 0 ? 404 : 200);
        return $this->json($result, $statusCode, [], ['groups' => 'game:read']);
    }
}
