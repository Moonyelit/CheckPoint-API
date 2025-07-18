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
        try {
            $query = $this->sanitizeSearchQuery($query);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        // 🛡️ VALIDATION SUPPLÉMENTAIRE - Limiter le nombre de paramètres
        $queryParams = $request->query->all();
        if (count($queryParams) > 10) {
            return $this->json(['error' => 'Trop de paramètres de requête'], 400);
        }

        // 🔍 LOGGING - Traçabilité des recherches
        $this->logger->info('Recherche intelligente', [
            'query' => $query,
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'params_count' => count($queryParams)
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
     * Cette validation côté serveur NE PEUT PAS être contournée
     */
    private function sanitizeSearchQuery(string $query): string
    {
        // 🚨 VALIDATION STRICTE - Protection maximale
        if (empty($query)) {
            throw new \InvalidArgumentException('Requête vide non autorisée');
        }

        // 🔍 REGEX POUR FILTRER LES CARACTÈRES AUTORISÉS
        // Seuls les caractères alphanumériques, espaces, tirets, underscores et accents sont autorisés
        $allowedPattern = '/^[a-zA-Z0-9\s\-_àáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞß]+$/';
        
        // Nettoyer d'abord les caractères de contrôle
        $query = preg_replace('/[\x00-\x1F\x7F]/', '', $query);
        
        // Normaliser les espaces
        $query = preg_replace('/\s+/', ' ', $query);
        
        // Supprimer les patterns dangereux avec regex
        $dangerousPatterns = [
            '/<script[^>]*>.*?<\/script>/is',           // Scripts HTML
            '/javascript:/i',                            // Protocoles dangereux
            '/vbscript:/i',
            '/data:/i',
            '/on\w+\s*=/i',                             // Événements JavaScript
            '/eval\s*\(/i',                             // Fonctions dangereuses
            '/document\./i',
            '/window\./i',
            '/alert\s*\(/i',
            '/confirm\s*\(/i',
            '/prompt\s*\(/i',
            '/console\./i',
            '/localStorage\./i',
            '/sessionStorage\./i',
            '/cookie/i',
            '/fetch\s*\(/i',
            '/XMLHttpRequest/i',
            '/<iframe[^>]*>/i',                         // Iframes
            '/<object[^>]*>/i',                         // Objects
            '/<embed[^>]*>/i',                          // Embeds
            '/<link[^>]*>/i',                           // Links externes
            '/<meta[^>]*>/i',                           // Meta tags
            '/<style[^>]*>.*?<\/style>/is',             // Styles inline
            '/<form[^>]*>.*?<\/form>/is',               // Formulaires
            '/<input[^>]*>/i',                          // Inputs
            '/<button[^>]*>/i',                         // Boutons
            '/<select[^>]*>/i',                         // Selects
            '/<textarea[^>]*>/i',                       // Textareas
            '/union\s+select/i',                        // SQL Injection
            '/drop\s+table/i',
            '/delete\s+from/i',
            '/insert\s+into/i',
            '/update\s+set/i',
            '/alter\s+table/i',
            '/create\s+table/i',
            '/exec\s*\(/i',                             // Commandes système
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/`.*`/i',                                  // Backticks
            '/\$\(.*\)/i',                              // Command substitution
            '/\|\s*\w+/i',                              // Pipes
            '/;\s*\w+/i',                               // Semicolons
            '/&&\s*\w+/i',                              // AND operators
            '/\|\|\s*\w+/i',                            // OR operators
            '/\b(?:admin|root|test|user|moderator)\b/i', // Mots interdits
            '/\b(?:password|passwd|secret|key|token)\b/i',
            '/\b(?:config|conf|ini|cfg)\b/i',
            '/\b(?:\.\.\/|\.\.\\\)/i',                  // Path traversal
            '/\b(?:http|https|ftp|file):\/\//i',        // URLs
            '/\b(?:localhost|127\.0\.0\.1|0\.0\.0\.0)\b/i', // IPs locales
        ];
        
        // Appliquer tous les patterns dangereux
        foreach ($dangerousPatterns as $pattern) {
            $query = preg_replace($pattern, '', $query);
        }
        
        // 🔒 FILTRAGE FINAL AVEC REGEX - Seuls les caractères autorisés
        $query = preg_replace('/[^a-zA-Z0-9\s\-_àáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞß]/', '', $query);
        
        // Limiter la longueur (forcé côté serveur)
        $query = substr(trim($query), 0, 100);
        
        // Validation finale avec regex
        if (!preg_match('/^[a-zA-Z0-9\s\-_àáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞß]{2,100}$/', $query)) {
            throw new \InvalidArgumentException('Requête contient des caractères non autorisés');
        }
        
        // Validation de longueur finale
        if (strlen($query) < 2) {
            throw new \InvalidArgumentException('Requête trop courte (minimum 2 caractères)');
        }
        
        if (strlen($query) > 100) {
            throw new \InvalidArgumentException('Requête trop longue (maximum 100 caractères)');
        }
        
        return $query;
    }
} 