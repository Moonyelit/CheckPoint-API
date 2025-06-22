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
        LoggerInterface $logger
    ) {
        $this->limiter = $apiSearchLimitFactory->create(); // Crée une instance de LimiterInterface
        $this->logger = $logger;
    }

    #[Route('/api/games/search/{name}', name: 'api_game_search')]
    public function search(string $name, Request $request, IgdbClient $igdb): JsonResponse
    {
        // Limite les requêtes API.
        $limit = $this->limiter->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException('Trop de requêtes.');
        }

        // Récupère les paramètres de pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = (int) $request->query->get('limit', 20);
        $offset = ($page - 1) * $limit;

        // Recherche des jeux via IGDB avec pagination
        $games = $igdb->searchGames($name, $limit, $offset);
        $totalCount = $igdb->countGames($name);
        
        return $this->json([
            'games' => $games,
            'pagination' => [
                'currentPage' => $page,
                'limit' => $limit,
                'offset' => $offset,
                'totalCount' => $totalCount
            ]
        ]);
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
        // 1. On cherche d'abord dans notre base de données.
        $localGames = $gameRepository->findByTitleLike($query);

        // 2. Ensuite, on importe les jeux depuis IGDB.
        // La méthode importGamesBySearch est intelligente : elle met à jour les jeux existants
        // et ajoute les nouveaux.
        try {
            $importedGames = $gameImporter->importGamesBySearch($query);
        } catch (\Throwable $e) {
            $this->logger->error("Erreur lors de l'import depuis IGDB: " . $e->getMessage());
            // Si l'import échoue, on renvoie au moins les résultats locaux.
            return $this->json($localGames, 200, [], ['groups' => 'game:read']);
        }

        // 3. On retourne la liste complète des jeux importés/mis à jour.
        // Le front-end se chargera de la pagination côté client.
        return $this->json($importedGames, 200, [], ['groups' => 'game:read']);
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

    #[Route('/api/games/top100', name: 'api_games_top100')]
    public function getTop100Games(Request $request, GameRepository $gameRepository, IgdbClient $igdbClient): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 5);
        
        // Récupère les jeux du Top 100
        $games = $gameRepository->findTop100Games($limit);
        
        // Améliore automatiquement la qualité des images pour chaque jeu
        foreach ($games as $game) {
            if ($game->getCoverUrl()) {
                $improvedUrl = $igdbClient->improveImageQuality($game->getCoverUrl(), 't_cover_big');
                $game->setCoverUrl($improvedUrl);
            }
        }

        return $this->json($games, 200, [], ['groups' => 'game:read']);
    }

    #[Route('/api/games/{slug}', name: 'api_game_details', priority: -1)]
    public function getGameBySlug(string $slug, GameRepository $gameRepository): JsonResponse
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

        $this->logger->info("Jeu trouvé : '{$game->getTitle()}' pour le slug '{$slug}'");
        return $this->json($game, Response::HTTP_OK, [], ['groups' => ['game:read', 'game:details']]);
    }

}
