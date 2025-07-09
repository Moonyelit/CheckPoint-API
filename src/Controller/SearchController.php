<?php

namespace App\Controller;

use App\Service\GameImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;

/**
 * 🔍 CONTRÔLEUR DE RECHERCHE INTELLIGENTE
 * 
 * Endpoint sécurisé pour la recherche de jeux avec protection contre les injections
 * et rate limiting pour éviter le spam.
 */
class SearchController extends AbstractController
{
    private RateLimiterFactory $rateLimiterFactory;
    private LoggerInterface $logger;

    public function __construct(
        #[Autowire(service: 'limiter.apiSearchLimit')] RateLimiterFactory $apiSearchLimitFactory,
        LoggerInterface $logger
    ) {
        $this->rateLimiterFactory = $apiSearchLimitFactory;
        $this->logger = $logger;
    }

    #[Route('/api/games/search-intelligent/{query}', name: 'api_search_intelligent', methods: ['GET'])]
    public function searchIntelligent(string $query, GameImporter $gameImporter, Request $request): JsonResponse
    {
        // 🔒 RATE LIMITING - Protection contre le spam
        $limiter = $this->rateLimiterFactory->create($request->getClientIp());
        $limiter->consume(1);

        // 🛡️ VALIDATION ET NETTOYAGE - Protection contre les injections
        $query = $this->sanitizeSearchQuery($query);
        
        if (empty($query) || strlen($query) < 2) {
            return $this->json(['error' => 'Requête de recherche invalide'], 400);
        }

        if (strlen($query) > 100) {
            return $this->json(['error' => 'Requête de recherche trop longue'], 400);
        }

        // 🔍 LOGGING - Traçabilité des recherches
        $this->logger->info('Recherche intelligente', [
            'query' => $query,
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent')
        ]);

        try {
            // Recherche intelligente via GameImporter
            $games = $gameImporter->searchGamesWithoutPersist($query);
            
            $this->logger->info('Recherche terminée', [
                'query' => $query,
                'results_count' => count($games)
            ]);

            return $this->json($games);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la recherche', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            return $this->json(['error' => 'Erreur lors de la recherche'], 500);
        }
    }

    /**
     * 🛡️ NETTOYAGE ET VALIDATION DE LA REQUÊTE
     * Protection contre les injections et caractères malveillants
     */
    private function sanitizeSearchQuery(string $query): string
    {
        // Supprimer les caractères dangereux
        $query = preg_replace('/[<>"\']/', '', $query);
        
        // Supprimer les espaces multiples
        $query = preg_replace('/\s+/', ' ', $query);
        
        // Nettoyer les caractères de contrôle
        $query = preg_replace('/[\x00-\x1F\x7F]/', '', $query);
        
        // Limiter la longueur
        $query = substr(trim($query), 0, 100);
        
        return $query;
    }
} 