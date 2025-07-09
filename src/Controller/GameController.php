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
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
 * - /api/games/search/{name} : [OBSOLÈTE] Ancien endpoint de recherche - remplacé par API Platform
 * - /games/search/{query} : Vue Twig pour affichage frontend
 * - /api/games/search-or-import/{query} : Recherche intelligente avec import auto
 * - /api/games/search-with-fallback/{query} : [OBSOLÈTE] Ancien endpoint avec fallback
 * - /api/games/search-local/{query} : [OBSOLÈTE] Ancien endpoint de recherche locale
 * - /api/games/search-igdb/{query} : [OBSOLÈTE] Ancien endpoint de recherche IGDB
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
 * 
 * ⚠️ MIGRATION API PLATFORM :
 * - Les anciens endpoints de recherche ont été remplacés par API Platform
 * - Utilisez /api/games avec les filtres custom pour la recherche
 * - Les endpoints obsolètes retournent 410 (Gone) avec un message explicatif
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
        $this->limiter = $apiSearchLimitFactory->create(); // Crée une instance de LimiterInterface
        $this->logger = $logger;
    }

    #[Route('/api/games/search/{name}', name: 'api_game_search')]
    public function search(string $name, Request $request): JsonResponse
    {
        // Ancien endpoint de recherche - OBSOLÈTE
        // Remplacé par API Platform avec filtres custom
            return $this->json([
            'error' => 'Endpoint obsolète. Utilisez /api/games avec les filtres API Platform.',
            'message' => 'Cet endpoint a été remplacé par API Platform'
        ], 410); // Gone - ressource supprimée
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
        // Importe les jeux du Top 100 d'IGDB
        $importer->importTop100Games(80, 75);
        return new Response('Import du Top 100 IGDB terminé !');
    }

    #[Route('/admin/import-top-year-games', name: 'admin_import_top_year_games')]
    public function importTopYearGames(GameImporter $importer): Response
    {
        // Importe les meilleurs jeux de l'année (365 derniers jours) avec critères stricts
        $count = $importer->importTopYearGames(80, 75);
        return new Response("Import des jeux de l'année terminé ! {$count} jeux traités.");
    }

    #[Route('/api/games/search-or-import/{query}', name: 'api_game_search_or_import')]
    public function searchGame(string $query, GameImporter $gameImporter, GameRepository $gameRepository): JsonResponse
    {
        $this->logger->info("Recherche intelligente pour : '{$query}'");

        // 1. On cherche d'abord dans notre base de données (priorité aux jeux locaux)
        $localGames = $gameRepository->findByTitleLike($query);
        $this->logger->info(sprintf("Trouvé %d jeux en base locale", count($localGames)));

        // 2. Si on a suffisamment de résultats locaux, on les renvoie directement
        if (count($localGames) >= 5) {
            $this->logger->info("Résultats locaux suffisants, pas d'import IGDB nécessaire");
            return $this->json($localGames, 200, [], ['groups' => 'game:read']);
        }

        // 3. Si peu de résultats locaux, on importe depuis IGDB pour enrichir
        $importedGames = [];
        try {
            $this->logger->info("Peu de résultats locaux, import IGDB pour enrichir");
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

        // 4. Fusion intelligente : priorité aux jeux locaux, puis ajout des nouveaux
        $finalGames = [];
        $localGameIds = array_map(fn($game) => $game->getIgdbId(), $localGames);
        
        // Ajoute d'abord tous les jeux locaux
        foreach ($localGames as $localGame) {
            $finalGames[] = $localGame;
        }
        
        // Ajoute les jeux importés qui ne sont pas déjà en local
        $addedIgdb = 0;
        foreach ($importedGames as $importedGame) {
            if (!in_array($importedGame->getIgdbId(), $localGameIds)) {
                $finalGames[] = $importedGame;
                $addedIgdb++;
            }
        }
        $this->logger->info(sprintf("Fusion finale : %d jeux locaux + %d jeux IGDB ajoutés (total: %d)", count($localGames), $addedIgdb, count($finalGames)));

        // 5. Si on a encore moins de 10 résultats, essayer une recherche plus large
        if (count($finalGames) < 10) {
            $this->logger->info("Peu de résultats, tentative de recherche élargie");
            
            // Essayer avec des variantes du terme de recherche
            $variants = [
                $query,
                ucfirst($query),
                str_replace('-', ' ', $query),
                str_replace('-', ' ', ucfirst($query))
            ];
            
            foreach ($variants as $variant) {
                if ($variant !== $query) {
                    try {
                        $additionalGames = $gameImporter->importGamesBySearch($variant);
                        foreach ($additionalGames as $additionalGame) {
                            if (!in_array($additionalGame->getIgdbId(), array_map(fn($g) => $g->getIgdbId(), $finalGames))) {
                                $finalGames[] = $additionalGame;
                            }
                        }
                        $this->logger->info(sprintf("Ajouté %d jeux avec la variante '%s'", count($additionalGames), $variant));
                    } catch (\Throwable $e) {
                        $this->logger->warning("Erreur avec la variante '{$variant}': " . $e->getMessage());
                    }
                }
            }
        }

        $this->logger->info(sprintf("Résultat final : %d jeux (local: %d, importé: %d)", 
            count($finalGames), count($localGames), count($importedGames) - count($localGames)));

        // On retourne TOUJOURS la fusion locale + IGDB, même si la base locale contient déjà des jeux
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

        // Sauvegarder en base
        $gameRepository->getEntityManager()->flush();

        return new Response("Mise à jour terminée ! {$updatedCount} images améliorées.");
    }

    #[Route('/api/custom/games/{slug}', name: 'api_game_details', priority: -1)]
    public function getGameBySlug(string $slug, GameRepository $gameRepository, IgdbClient $igdbClient, GameImporter $gameImporter): JsonResponse
    {
        $this->logger->info("Recherche du jeu avec le slug : '{$slug}'");

        $game = $gameRepository->findOneBy(['slug' => $slug]);

        // Fallback : si le jeu n'est pas trouvé, chercher un slug qui commence pareil (ex: final-fantasy-vi-426)
        if (!$game) {
            $this->logger->info("Slug exact non trouvé, tentative de fallback sur les slugs similaires");
            $qb = $gameRepository->createQueryBuilder('g');
            $qb->where('g.slug LIKE :slugStart')
                ->setParameter('slugStart', $slug . '%');
            $similarGames = $qb->getQuery()->getResult();
            if (!empty($similarGames)) {
                $game = $similarGames[0];
                $this->logger->info("Jeu trouvé par fallback : '{$game->getTitle()}' avec le slug '{$game->getSlug()}'");
            }
        }

        // Si le jeu n'est toujours pas trouvé, tenter l'import depuis IGDB
        if (!$game) {
            $this->logger->info("Jeu non trouvé en base, tentative d'import depuis IGDB pour le slug : '{$slug}'");
            
            try {
                // Extraire le titre du slug pour la recherche IGDB
                $title = $this->extractTitleFromSlug($slug);
                $this->logger->info("Recherche IGDB avec le titre : '{$title}'");
                
                // Tenter d'importer le jeu depuis IGDB avec plusieurs variantes
                $importedGame = $this->tryImportWithVariants($title, $gameImporter);
                
                if ($importedGame) {
                    $game = $importedGame;
                    $this->logger->info("✅ Jeu importé avec succès depuis IGDB : '{$game->getTitle()}'");
                } else {
                    $this->logger->warning("❌ Aucun jeu trouvé sur IGDB pour le titre : '{$title}'");
                }
            } catch (\Throwable $e) {
                $this->logger->error("❌ Erreur lors de l'import depuis IGDB : " . $e->getMessage());
            }
        }

        if (!$game) {
            $this->logger->warning("Jeu non trouvé pour le slug : '{$slug}' (ni en base, ni sur IGDB)");
            
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

    /**
     * Extrait le titre du slug en enlevant l'ID IGDB à la fin
     */
    private function extractTitleFromSlug(string $slug): string
    {
        // Si le slug se termine par un tiret suivi de chiffres, c'est probablement un ID IGDB
        if (preg_match('/^(.+)-\d+$/', $slug, $matches)) {
            return $matches[1];
        }
        
        return $slug;
    }

    /**
     * Génère un slug propre à partir du titre
     */
    private function generateCleanSlug(string $title): string
    {
        $slugify = new \Cocur\Slugify\Slugify();
        return $slugify->slugify($title);
    }

    #[Route('/api/test', name: 'api_test')]
    public function test(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'message' => 'API Symfony fonctionne correctement',
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    #[Route('/api/proxy/image', name: 'api_game_image_proxy')]
    public function imageProxy(Request $request): Response
    {
        $imageUrl = $request->query->get('url');
        
        if (!$imageUrl) {
            $this->logger->error('Proxy image: URL manquante');
            return new Response('URL manquante', 400);
        }
        
        // Décoder l'URL si elle est encodée
        $imageUrl = urldecode($imageUrl);
        
        // Nettoyer les protocoles dupliqués (https://https:// ou http://https://)
        $imageUrl = preg_replace('/^https?:\/\/https?:\/\/?/', 'https://', $imageUrl);
        $imageUrl = preg_replace('/^https?:\/\/http:\/\/?/', 'https://', $imageUrl);
        
        // Vérifier si l'URL contient déjà notre proxy (récursion)
        if (strpos($imageUrl, '/api/proxy/image') !== false || 
            strpos($imageUrl, '127.0.0.1:8000/api/proxy/image') !== false ||
            strpos($imageUrl, 'localhost:8000/api/proxy/image') !== false) {
            $this->logger->error('Proxy image: Récursion détectée', ['url' => $imageUrl]);
            return new Response('Récursion détectée dans l\'URL', 400);
        }
        
        // Vérifie que c'est bien une URL IGDB
        if (strpos($imageUrl, 'images.igdb.com') === false) {
            $this->logger->error('Proxy image: URL non autorisée', ['url' => $imageUrl]);
            return new Response('URL non autorisée', 403);
        }
        
        // S'assurer que l'URL a le bon format
        if (strpos($imageUrl, '//') === 0) {
            $imageUrl = 'https:' . $imageUrl;
        } elseif (!preg_match('/^https?:\/\//', $imageUrl)) {
            $imageUrl = 'https://' . $imageUrl;
        }
        
        try {
            $this->logger->info('Proxy image: Tentative de récupération', ['url' => $imageUrl]);
            
            // Récupère l'image depuis IGDB
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
                'Cache-Control' => 'public, max-age=86400', // Cache 24h
                'Access-Control-Allow-Origin' => '*'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Proxy image: Erreur lors du chargement', [
                'url' => $imageUrl,
                'error' => $e->getMessage()
            ]);
            
            // Retourner une image par défaut ou une erreur plus descriptive
            return new Response('Erreur lors du chargement de l\'image: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tente d'importer un jeu avec plusieurs variantes de recherche
     */
    private function tryImportWithVariants(string $title, GameImporter $gameImporter): ?\App\Entity\Game
    {
        // Liste des variantes à tester
        $variants = [
            $title, // Version originale
            ucfirst($title), // Première lettre en majuscule
            str_replace('-', ' ', $title), // Remplacer les tirets par des espaces
            str_replace('-', ' ', ucfirst($title)), // Combinaison des deux
        ];
        
        // Ajouter des variantes spécifiques pour certains jeux
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
        
        // Ajouter les variantes spécifiques si elles existent
        if (isset($specificVariants[$title])) {
            $variants = array_merge($variants, $specificVariants[$title]);
        }
        
        // Supprimer les doublons
        $variants = array_unique($variants);
        
        $this->logger->info("Tentative d'import avec les variantes : " . implode(', ', $variants));
        
        // Tester chaque variante
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
                // Continuer avec la variante suivante
            }
        }
        
        $this->logger->warning("❌ Aucune variante n'a fonctionné pour : '{$title}'");
        return null;
    }

    #[Route('/admin/fix-malformed-urls', name: 'admin_fix_malformed_urls')]
    public function fixMalformedUrls(GameRepository $gameRepository): Response
    {
        // Récupère tous les jeux avec une coverUrl
        $games = $gameRepository->createQueryBuilder('g')
            ->where('g.coverUrl IS NOT NULL')
            ->andWhere('g.coverUrl != :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();

        $fixedCount = 0;
        $errors = [];

        foreach ($games as $game) {
            $originalUrl = $game->getCoverUrl();
            
            // Vérifier si l'URL est malformée
            if (preg_match('/^https?:\/\/https?:\/\/?/', $originalUrl) || 
                preg_match('/^https?:\/\/http:\/\/?/', $originalUrl)) {
                
                // Nettoyer l'URL
                $cleanedUrl = preg_replace('/^https?:\/\/https?:\/\/?/', 'https://', $originalUrl);
                $cleanedUrl = preg_replace('/^https?:\/\/http:\/\/?/', 'https://', $cleanedUrl);
                
                // S'assurer que l'URL a le bon format
                if (strpos($cleanedUrl, '//') === 0) {
                    $cleanedUrl = 'https:' . $cleanedUrl;
                } elseif (!preg_match('/^https?:\/\//', $cleanedUrl)) {
                    $cleanedUrl = 'https://' . $cleanedUrl;
                }
                
                if ($cleanedUrl !== $originalUrl) {
                    try {
                        $game->setCoverUrl($cleanedUrl);
                        $game->setUpdatedAt(new \DateTimeImmutable());
                        $fixedCount++;
                        
                        $this->logger->info("URL corrigée pour '{$game->getTitle()}': {$originalUrl} -> {$cleanedUrl}");
                    } catch (\Exception $e) {
                        $errors[] = "Erreur pour '{$game->getTitle()}': " . $e->getMessage();
                    }
                }
            }
        }

        // Sauvegarder en base
        try {
            $gameRepository->getEntityManager()->flush();
            $this->logger->info("Correction terminée: {$fixedCount} URLs corrigées");
        } catch (\Exception $e) {
            $errors[] = "Erreur lors de la sauvegarde: " . $e->getMessage();
        }

        $response = "Correction terminée ! {$fixedCount} URLs malformées corrigées.";
        if (!empty($errors)) {
            $response .= "\nErreurs:\n" . implode("\n", $errors);
        }

        return new Response($response);
    }

    #[Route('/api/test-top-year', name: 'api_test_top_year')]
    public function testTopYear(GameRepository $gameRepository, IgdbClient $igdbClient): JsonResponse
    {
        $games = $gameRepository->findTopYearGamesDeduplicated(5, 80, 80);
        
        // Améliore automatiquement la qualité des images pour chaque jeu
        foreach ($games as $game) {
            if ($game->getCoverUrl()) {
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

    #[Route('/api/filters', name: 'api_filters')]
    public function getFilters(GameRepository $gameRepository): JsonResponse
    {
        // Récupère tous les filtres disponibles depuis la base de données
        $filters = [
            'genres' => [
                'label' => 'Genres',
                'values' => $gameRepository->getDistinctGenres()
            ],
            'platforms' => [
                'label' => 'Plateformes',
                'values' => $gameRepository->getDistinctPlatforms()
            ],
            'gameModes' => [
                'label' => 'Modes de jeu',
                'values' => $gameRepository->getDistinctGameModes()
            ],
            'perspectives' => [
                'label' => 'Perspectives',
                'values' => $gameRepository->getDistinctPerspectives()
            ]
        ];

        return $this->json($filters);
    }

    #[Route('/api/games/search-fast/{query}', name: 'api_game_search_fast')]
    public function searchGameFast(string $query, GameImporter $gameImporter, GameRepository $gameRepository): JsonResponse
    {
        $this->logger->info("Recherche rapide pour : '{$query}'");

        // Cache simple en mémoire (pour cette requête uniquement)
        static $cache = [];
        $cacheKey = md5($query);
        
        // Vérifier le cache
        if (isset($cache[$cacheKey])) {
            $this->logger->info("Résultats récupérés depuis le cache pour : '{$query}'");
            return $this->json($cache[$cacheKey], 200, [], ['groups' => 'game:read']);
        }

        // 1. Recherche locale d'abord (très rapide)
        $localGames = $gameRepository->findByTitleLike($query);
        $this->logger->info(sprintf("Trouvé %d jeux en base locale", count($localGames)));

        // 2. Recherche IGDB rapide (sans persistance) - TOUJOURS effectuée
        $igdbGames = [];
        try {
            $this->logger->info("Recherche IGDB pour enrichir les résultats");
            $igdbGames = $gameImporter->searchGamesWithoutPersist($query);
            $this->logger->info(sprintf("Trouvé %d jeux IGDB (non persistés)", count($igdbGames)));
        } catch (\Throwable $e) {
            $this->logger->error("Erreur lors de la recherche IGDB: " . $e->getMessage());
            // Si la recherche IGDB échoue, on renvoie au moins les résultats locaux
            if (!empty($localGames)) {
                $cache[$cacheKey] = $localGames;
                return $this->json($localGames, 200, [], ['groups' => 'game:read']);
            }
            return $this->json(['error' => 'Aucun jeu trouvé'], 404);
        }

        // 3. Fusion des résultats : jeux locaux + jeux IGDB non persistés
        $finalGames = [];
        
        // Ajoute d'abord tous les jeux locaux
        foreach ($localGames as $localGame) {
            $finalGames[] = $localGame;
        }
        
        // Ajoute les jeux IGDB qui ne sont pas déjà en local
        $localIgdbIds = array_map(fn($game) => $game->getIgdbId(), $localGames);
        $addedIgdb = 0;
        
        foreach ($igdbGames as $igdbGame) {
            if (!in_array($igdbGame['igdbId'], $localIgdbIds)) {
                $finalGames[] = $igdbGame;
                $addedIgdb++;
            }
        }
        
        $this->logger->info(sprintf("Fusion finale : %d jeux locaux + %d jeux IGDB ajoutés (total: %d)", 
            count($localGames), $addedIgdb, count($finalGames)));

        // Mettre en cache les résultats
        $cache[$cacheKey] = $finalGames;

        return $this->json($finalGames, 200, [], ['groups' => 'game:read']);
    }

    #[Route('/api/games/search-intelligent/{query}', name: 'api_game_search_intelligent')]
    public function searchIntelligent(string $query, GameImporter $gameImporter, GameRepository $gameRepository): JsonResponse
    {
        $this->logger->info("Recherche intelligente pour : '{$query}'");

        // Cache simple en mémoire
        static $cache = [];
        $cacheKey = 'intelligent_' . md5($query);
        
        if (isset($cache[$cacheKey])) {
            $this->logger->info("Résultats intelligents récupérés depuis le cache");
            return $this->json($cache[$cacheKey], 200, [], ['groups' => 'game:read']);
        }

        // 1. Recherche locale : phrase complète + mots individuels
        $localGames = $gameRepository->findByTitleLike($query);
        $this->logger->info(sprintf("Trouvé %d jeux en base locale", count($localGames)));

        // 2. Recherche IGDB : même logique
        $igdbGames = [];
        try {
            $this->logger->info("Recherche IGDB pour enrichir");
            $igdbGames = $gameImporter->searchGamesWithoutPersist($query);
            $this->logger->info(sprintf("Trouvé %d jeux IGDB", count($igdbGames)));
        } catch (\Throwable $e) {
            $this->logger->error("Erreur recherche IGDB: " . $e->getMessage());
            if (!empty($localGames)) {
                $cache[$cacheKey] = $localGames;
                return $this->json($localGames, 200, [], ['groups' => 'game:read']);
            }
            return $this->json(['error' => 'Aucun jeu trouvé'], 404);
        }

        // 3. Fusion intelligente avec déduplication basée sur l'igdbId
        $finalGames = [];
        $seenIgdbIds = [];
        
        // Ajoute d'abord tous les jeux locaux (priorité)
        foreach ($localGames as $localGame) {
            $finalGames[] = $localGame;
            $seenIgdbIds[] = $localGame->getIgdbId();
        }
        
        // Ajoute les jeux IGDB qui ne sont pas déjà en local
        $addedIgdb = 0;
        foreach ($igdbGames as $igdbGame) {
            if (!in_array($igdbGame['igdbId'], $seenIgdbIds)) {
                $finalGames[] = $igdbGame;
                $seenIgdbIds[] = $igdbGame['igdbId'];
                $addedIgdb++;
            }
        }
        
        $this->logger->info(sprintf("Fusion avec déduplication : %d jeux locaux + %d jeux IGDB ajoutés (total: %d)", 
            count($localGames), $addedIgdb, count($finalGames)));

        // 4. Filtrage intelligent avec regex pour éviter les faux positifs
        $filteredGames = $this->filterGamesIntelligently($finalGames, $query);
        $this->logger->info(sprintf("Après filtrage intelligent : %d jeux", count($filteredGames)));

        // 5. Tri intelligent par pertinence
        $finalGames = $this->sortGamesByRelevance($filteredGames, $query);
        
        $this->logger->info(sprintf("Résultats finaux : %d jeux triés par pertinence", count($finalGames)));

        // Cache les résultats
        $cache[$cacheKey] = $finalGames;

        return $this->json($finalGames, 200, [], ['groups' => 'game:read']);
    }

    /**
     * Filtrage intelligent avec regex pour éviter les faux positifs
     */
    private function filterGamesIntelligently(array $games, string $query): array
    {
        $words = preg_split('/\s+/', trim($query));
        $filteredWords = array_filter($words, function($word) {
            $shortWords = ['the', 'and', 'of', 'in', 'on', 'at', 'to', 'for', 'with', 'by', 'her', 'his', 'my', 'your', 'our', 'their'];
            return strlen($word) >= 4 && !in_array(strtolower($word), $shortWords);
        });

        if (empty($filteredWords)) {
            return $games; // Pas de filtrage si pas de mots valides
        }

        $filteredGames = [];
        foreach ($games as $game) {
            // Gérer les objets Game et les tableaux IGDB
            $title = '';
            if (is_object($game) && method_exists($game, 'getTitle')) {
                $title = $game->getTitle() ?? '';
            } elseif (is_array($game) && isset($game['title'])) {
                $title = $game['title'] ?? '';
            } elseif (is_array($game) && isset($game['name'])) {
                $title = $game['name'] ?? '';
            }
            
            $titleLower = mb_strtolower($title);
            
            $allMatch = true;
            foreach ($filteredWords as $word) {
                // Regex avec word boundary pour éviter "light" dans "twilight"
                if (!preg_match('/\\b' . preg_quote(mb_strtolower($word), '/') . '\\b/u', $titleLower)) {
                    $allMatch = false;
                    break;
                }
            }
            
            if ($allMatch) {
                $filteredGames[] = $game;
            }
        }

        return $filteredGames;
    }

    /**
     * Tri intelligent par pertinence
     */
    private function sortGamesByRelevance(array $games, string $query): array
    {
        $queryLower = mb_strtolower($query);
        
        usort($games, function($a, $b) use ($queryLower) {
            // Gérer les objets Game et les tableaux IGDB pour le titre
            $titleA = '';
            $titleB = '';
            
            if (is_object($a) && method_exists($a, 'getTitle')) {
                $titleA = $a->getTitle() ?? '';
            } elseif (is_array($a) && isset($a['title'])) {
                $titleA = $a['title'] ?? '';
            } elseif (is_array($a) && isset($a['name'])) {
                $titleA = $a['name'] ?? '';
            }
            
            if (is_object($b) && method_exists($b, 'getTitle')) {
                $titleB = $b->getTitle() ?? '';
            } elseif (is_array($b) && isset($b['title'])) {
                $titleB = $b['title'] ?? '';
            } elseif (is_array($b) && isset($b['name'])) {
                $titleB = $b['name'] ?? '';
            }
            
            $titleA = mb_strtolower($titleA);
            $titleB = mb_strtolower($titleB);
            
            // 1. Priorité : correspondance exacte de la phrase
            $exactMatchA = $titleA === $queryLower;
            $exactMatchB = $titleB === $queryLower;
            
            if ($exactMatchA && !$exactMatchB) return -1;
            if (!$exactMatchA && $exactMatchB) return 1;
            
            // 2. Priorité : commence par la phrase
            $startsWithA = strpos($titleA, $queryLower) === 0;
            $startsWithB = strpos($titleB, $queryLower) === 0;
            
            if ($startsWithA && !$startsWithB) return -1;
            if (!$startsWithA && $startsWithB) return 1;
            
            // 3. Priorité : contient la phrase
            $containsA = strpos($titleA, $queryLower) !== false;
            $containsB = strpos($titleB, $queryLower) !== false;
            
            if ($containsA && !$containsB) return -1;
            if (!$containsA && $containsB) return 1;
            
            // 4. Tri par note (si disponible)
            $ratingA = 0;
            $ratingB = 0;
            
            if (is_object($a) && method_exists($a, 'getTotalRating')) {
                $ratingA = $a->getTotalRating() ?? 0;
            } elseif (is_array($a) && isset($a['totalRating'])) {
                $ratingA = $a['totalRating'] ?? 0;
            }
            
            if (is_object($b) && method_exists($b, 'getTotalRating')) {
                $ratingB = $b->getTotalRating() ?? 0;
            } elseif (is_array($b) && isset($b['totalRating'])) {
                $ratingB = $b['totalRating'] ?? 0;
            }
            
            if ($ratingA !== $ratingB) {
                return $ratingB <=> $ratingA; // Décroissant
            }
            
            // 5. Tri alphabétique
            return $titleA <=> $titleB;
        });
        
        return $games;
    }
}
