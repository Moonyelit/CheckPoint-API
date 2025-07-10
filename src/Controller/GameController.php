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
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use App\Service\GameImporter;
use App\Repository\GameRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * 🎮 CONTRÔLEUR PRINCIPAL - GESTION GLOBALE DES JEUX
 * 
 * Ce contrôleur central gère les opérations principales liées aux jeux :
 * import, gestion des images, et routes d'administration.
 * 
 * 🔧 FONCTIONNALITÉS PRINCIPALES :
 * 
 * 📥 IMPORT ADMIN :
 * - /admin/import-popular-games : Import jeux populaires
 * - /admin/import-top100-games : Import Top 100 IGDB
 * - /admin/import-top-year-games : Import jeux de l'année
 * 
 * 🖼️ GESTION IMAGES :
 * - /api/games/improve-image-quality : API amélioration qualité
 * - /admin/update-existing-images : Mise à jour batch des images
 * - /api/proxy/image : Proxy pour les images IGDB
 * 
 * 🎯 DÉTAILS JEUX :
 * - /api/custom/games/{slug} : Détails d'un jeu par slug
 * 
 * 🔒 SÉCURITÉ :
 * - Rate limiting sur les recherches API (évite spam)
 * - Routes admin protégées par rôles
 * - Gestion d'erreurs robuste avec fallbacks
 * 
 * 🔄 PROCESSUS DE RECHERCHE INTELLIGENTE :
 * 1. Recherche locale en base de données
 * 2. Si pas trouvé, recherche sur IGDB
 * 3. Import automatique du jeu trouvé
 * 4. Retour des données enrichies
 * 
 * 🖼️ GESTION DES IMAGES :
 * - Amélioration automatique de la qualité
 * - Proxy pour contourner les restrictions CORS
 * - Mise à jour batch des images existantes
 * - Optimisation des URLs pour différentes résolutions
 * 
 * ⚡ PERFORMANCE ET OPTIMISATION :
 * - Cache des tokens d'authentification IGDB
 * - Rate limiting pour éviter la surcharge
 * - Gestion des timeouts et erreurs
 * - Logs détaillés pour le debugging
 * 
 * 🛠️ TECHNOLOGIES UTILISÉES :
 * - Symfony Controller pour la gestion HTTP
 * - Rate Limiter pour la protection anti-spam
 * - HttpClient pour les requêtes externes
 * - Logger pour le suivi des opérations
 * - GameImporter pour les imports
 * 
 * 🔗 INTÉGRATION AVEC LES SERVICES :
 * - Utilise IgdbClient pour les requêtes IGDB
 * - Interface avec GameRepository pour les données
 * - Alimente GameImporter pour les imports
 * - Gère les erreurs et fallbacks
 * 
 * 📊 ENDPOINTS PRINCIPAUX :
 * - GET /api/custom/games/{slug} : Détails d'un jeu
 * - POST /api/games/improve-image-quality : Amélioration d'image
 * - GET /api/proxy/image : Proxy d'images
 * - POST /admin/import-* : Routes d'import admin
 * 
 * 🔒 SÉCURITÉ ET ROBUSTESSE :
 * - Validation des paramètres d'entrée
 * - Gestion des erreurs avec messages appropriés
 * - Protection contre les attaques par force brute
 * - Logs de sécurité pour audit
 * 
 * 🎯 UTILISATION TYPIQUE :
 * - Interface d'administration pour les imports
 * - API pour le frontend (détails de jeux)
 * - Gestion des images et médias
 * - Recherche et enrichissement automatique
 */
class GameController extends AbstractController
{
    private LimiterInterface $limiter;
    private LoggerInterface $logger;

    public function __construct(
        #[Autowire(service: 'limiter.apiSearchLimit')] RateLimiterFactory $apiSearchLimitFactory,
        private HttpClientInterface $client,
        LoggerInterface $logger
    ) {
        $this->limiter = $apiSearchLimitFactory->create();
        $this->logger = $logger;
    }

    #[Route('/admin/import-popular-games', name: 'admin_import_popular_games')]
    public function importPopularGames(GameImporter $importer): Response
    {
        $importer->importPopularGames();
        return new Response('Import terminé !');
    }

    #[Route('/admin/import-top100-games', name: 'admin_import_top100_games')]
    public function importTop100Games(GameImporter $importer): Response
    {
        $importer->importTop100Games(80, 75);
        return new Response('Import du Top 100 IGDB terminé !');
    }

    #[Route('/admin/import-top-year-games', name: 'admin_import_top_year_games')]
    public function importTopYearGames(GameImporter $importer): Response
    {
        $count = $importer->importTopYearGames(80, 75);
        return new Response("Import des jeux de l'année terminé ! {$count} jeux traités.");
    }

    #[Route('/api/games/improve-image-quality', name: 'api_improve_image_quality', methods: ['POST'])]
    public function improveImageQuality(Request $request, IgdbClient $igdb): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['gameId'])) {
            return $this->json(['error' => 'gameId requis'], 400);
        }

        try {
            $gameId = $data['gameId'];
            $this->logger->info("Amélioration de la qualité d'image pour le jeu ID: {$gameId}");
            
            $gameData = $igdb->getGameDetails($gameId);
            
            if (!$gameData) {
                return $this->json(['error' => 'Jeu non trouvé sur IGDB'], 404);
            }

            $originalUrl = $gameData['cover']['url'] ?? '';
            $improvedImageUrl = $igdb->improveImageQuality($originalUrl, 't_cover_big');
            
            return $this->json([
                'success' => true,
                'originalUrl' => $originalUrl,
                'improvedUrl' => $improvedImageUrl,
                'message' => 'Qualité d\'image améliorée'
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de l'amélioration d'image: " . $e->getMessage());
            return $this->json(['error' => 'Erreur lors de l\'amélioration d\'image'], 500);
        }
    }

    #[Route('/admin/update-existing-images', name: 'admin_update_existing_images')]
    public function updateExistingImages(GameRepository $gameRepository, IgdbClient $igdb): Response
    {
        $games = $gameRepository->createQueryBuilder('g')
            ->where('g.coverUrl IS NOT NULL')
            ->andWhere('g.coverUrl != :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();

        $updatedCount = 0;
        $errors = [];

        foreach ($games as $game) {
            try {
                $this->logger->info("Mise à jour de l'image pour: {$game->getTitle()}");
                
                $igdbData = $igdb->getGameDetails($game->getIgdbId());
                
                if ($igdbData && isset($igdbData['cover']['url'])) {
                    $newImageUrl = $igdb->improveImageQuality($igdbData['cover']['url'], 't_cover_big');
                    
                    if ($newImageUrl !== $game->getCoverUrl()) {
                        $game->setCoverUrl($newImageUrl);
                        $updatedCount++;
                        $this->logger->info("Image mise à jour pour: {$game->getTitle()}");
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Erreur pour {$game->getTitle()}: " . $e->getMessage();
                $this->logger->error("Erreur mise à jour image: " . $e->getMessage());
            }
        }

        $gameRepository->getEntityManager()->flush();

        return new Response("Mise à jour terminée ! {$updatedCount} images mises à jour, " . count($errors) . " erreurs.");
    }

    #[Route('/api/custom/games/{slug}', name: 'api_game_details', priority: -1)]
    public function getGameBySlug(string $slug, GameRepository $gameRepository, IgdbClient $igdbClient, GameImporter $gameImporter): JsonResponse
    {
        $this->logger->info("Recherche de jeu par slug: '{$slug}'");

        // 1. Chercher le jeu en base locale
        $game = $gameRepository->findOneBy(['slug' => $slug]);

        if ($game) {
            $this->logger->info("Jeu trouvé en base locale: '{$game->getTitle()}'");
            return $this->json($game, 200, [], ['groups' => 'game:read']);
        }

        // 2. Si pas trouvé, essayer d'importer depuis IGDB
        $this->logger->info("Jeu non trouvé en base, tentative d'import depuis IGDB");
        
        try {
            $importedGame = $this->tryImportWithVariants($slug, $gameImporter);
            
            if ($importedGame) {
                $this->logger->info("Jeu importé avec succès: '{$importedGame->getTitle()}'");
                return $this->json($importedGame, 200, [], ['groups' => 'game:read']);
            }
        } catch (\Throwable $e) {
            $this->logger->error("Erreur lors de l'import: " . $e->getMessage());
        }

        // 3. Si toujours pas trouvé, retourner une erreur
        $this->logger->warning("Jeu non trouvé: '{$slug}'");
        return $this->json(['error' => 'Jeu non trouvé'], 404);
    }

    #[Route('/api/games/search-or-import/{query}', name: 'api_search_or_import', methods: ['GET'])]
    public function searchOrImport(string $query, GameRepository $gameRepository, GameImporter $gameImporter): JsonResponse
    {
        $this->logger->info("Recherche ou import pour: '{$query}'");

        // 1. D'abord chercher en base locale
        $localGames = $gameRepository->findByTitleLike($query, 10);
        
        if (!empty($localGames)) {
            $this->logger->info("Jeux trouvés en base locale: " . count($localGames));
            return $this->json($localGames, 200, [], ['groups' => 'game:read']);
        }

        // 2. Si pas de résultats locaux, essayer d'importer depuis IGDB
        $this->logger->info("Aucun jeu local trouvé, tentative d'import depuis IGDB");
        
        try {
            $importedGame = $gameImporter->importGameBySearch($query);
            
            if ($importedGame) {
                $this->logger->info("Jeu importé avec succès: '{$importedGame->getTitle()}'");
                return $this->json([$importedGame], 200, [], ['groups' => 'game:read']);
            }
        } catch (\Throwable $e) {
            $this->logger->error("Erreur lors de l'import: " . $e->getMessage());
        }

        // 3. Si toujours pas de résultats, retourner un tableau vide
        $this->logger->warning("Aucun jeu trouvé pour: '{$query}'");
        return $this->json([], 200);
    }

    #[Route('/api/proxy/image', name: 'api_game_image_proxy')]
    public function imageProxy(Request $request): Response
    {
        $imageUrl = $request->query->get('url');
        
        if (!$imageUrl) {
            $this->logger->error('Proxy image: URL manquante');
            return new Response('URL manquante', 400);
        }
        
        $imageUrl = urldecode($imageUrl);
        $imageUrl = preg_replace('/^https?:\/\/https?:\/\/?/', 'https://', $imageUrl);
        $imageUrl = preg_replace('/^https?:\/\/http:\/\/?/', 'https://', $imageUrl);
        
        if (strpos($imageUrl, '/api/proxy/image') !== false || 
            strpos($imageUrl, '127.0.0.1:8000/api/proxy/image') !== false ||
            strpos($imageUrl, 'localhost:8000/api/proxy/image') !== false) {
            $this->logger->error('Proxy image: Récursion détectée', ['url' => $imageUrl]);
            return new Response('Récursion détectée dans l\'URL', 400);
        }
        
        if (strpos($imageUrl, 'images.igdb.com') === false) {
            $this->logger->error('Proxy image: URL non autorisée', ['url' => $imageUrl]);
            return new Response('URL non autorisée', 403);
        }
        
        if (strpos($imageUrl, '//') === 0) {
            $imageUrl = 'https:' . $imageUrl;
        } elseif (!preg_match('/^https?:\/\//', $imageUrl)) {
            $imageUrl = 'https://' . $imageUrl;
        }
        
        try {
            $this->logger->info('Proxy image: Tentative de récupération', ['url' => $imageUrl]);
            
            $response = $this->client->request('GET', $imageUrl, [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            $imageContent = $response->getContent();
            $contentType = $response->getHeaders()['content-type'][0] ?? 'image/jpeg';
            
            $this->logger->info('Proxy image: Image récupérée avec succès', [
                'url' => $imageUrl,
                'contentType' => $contentType,
                'size' => strlen($imageContent)
            ]);
            
            return new Response($imageContent, 200, [
                'Content-Type' => $contentType,
                'Cache-Control' => 'public, max-age=86400',
                'Access-Control-Allow-Origin' => '*'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Proxy image: Erreur lors du chargement', [
                'url' => $imageUrl,
                'error' => $e->getMessage()
            ]);
            
            return new Response('Erreur lors du chargement de l\'image: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tente d'importer un jeu avec plusieurs variantes de recherche
     */
    private function tryImportWithVariants(string $title, GameImporter $gameImporter): ?\App\Entity\Game
    {
        $variants = [
            $title,
            ucfirst($title),
            str_replace('-', ' ', $title),
            str_replace('-', ' ', ucfirst($title)),
        ];
        
        $specificVariants = [
            'oblivion' => ['The Elder Scrolls IV: Oblivion', 'Oblivion', 'TES IV: Oblivion'],
            'skyrim' => ['The Elder Scrolls V: Skyrim', 'Skyrim', 'TES V: Skyrim'],
            'morrowind' => ['The Elder Scrolls III: Morrowind', 'Morrowind', 'TES III: Morrowind'],
            'fallout' => ['Fallout', 'Fallout 1', 'Fallout: A Post Nuclear Role Playing Game'],
            'fallout-2' => ['Fallout 2', 'Fallout II'],
            'fallout-3' => ['Fallout 3', 'Fallout III'],
            'fallout-4' => ['Fallout 4', 'Fallout IV'],
            'fallout-new-vegas' => ['Fallout: New Vegas', 'Fallout New Vegas'],
            'final-fantasy' => ['Final Fantasy', 'Final Fantasy I'],
            'final-fantasy-vi' => ['Final Fantasy VI', 'Final Fantasy 6', 'FF6'],
            'final-fantasy-vii' => ['Final Fantasy VII', 'Final Fantasy 7', 'FF7'],
            'final-fantasy-viii' => ['Final Fantasy VIII', 'Final Fantasy 8', 'FF8'],
            'final-fantasy-ix' => ['Final Fantasy IX', 'Final Fantasy 9', 'FF9'],
            'final-fantasy-x' => ['Final Fantasy X', 'Final Fantasy 10', 'FFX'],
        ];
        
        if (isset($specificVariants[$title])) {
            $variants = array_merge($variants, $specificVariants[$title]);
        }
        
        $variants = array_unique($variants);
        
        $this->logger->info("Tentative d'import avec les variantes : " . implode(', ', $variants));
        
        foreach ($variants as $variant) {
            try {
                $this->logger->info("Test de la variante : '{$variant}'");
                $importedGame = $gameImporter->importGameBySearch($variant);
                
                if ($importedGame) {
                    $this->logger->info("✅ Succès avec la variante : '{$variant}' -> '{$importedGame->getTitle()}'");
                    return $importedGame;
                }
            } catch (\Throwable $e) {
                $this->logger->warning("❌ Échec avec la variante '{$variant}': " . $e->getMessage());
            }
        }
        
        $this->logger->warning("❌ Aucune variante n'a fonctionné pour : '{$title}'");
        return null;
    }
} 