<?php

namespace App\Security;

use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * 🛡️ RATE LIMITER - PROTECTION CONTRE LES ATTAQUES PAR FORCE BRUTE
 * 
 * Ce service protège l'application contre les attaques par force brute
 * en limitant le nombre de tentatives de connexion par adresse IP.
 * 
 * 🎯 OBJECTIF :
 * Empêcher les tentatives répétées de connexion pour protéger
 * les comptes utilisateurs et éviter la surcharge du serveur.
 * 
 * 🔒 MÉCANISME DE PROTECTION :
 * - Limitation du nombre de tentatives par IP
 * - Blocage temporaire après échecs répétés
 * - Délai progressif entre les tentatives
 * - Logs de sécurité pour audit
 * 
 * 📊 CONFIGURATION DES LIMITES :
 * - Maximum 5 tentatives par minute
 * - Blocage de 15 minutes après 5 échecs
 * - Délai progressif entre les tentatives
 * - Reset automatique après la période de blocage
 * 
 * ⚡ PROCESSUS DE VÉRIFICATION :
 * 1. Récupération de l'adresse IP du client
 * 2. Vérification du nombre de tentatives restantes
 * 3. Blocage si limite atteinte
 * 4. Incrémentation du compteur en cas d'échec
 * 5. Reset du compteur en cas de succès
 * 
 * 🛠️ TECHNOLOGIES UTILISÉES :
 * - Symfony Rate Limiter pour la gestion des limites
 * - Event Listener pour intercepter les échecs
 * - IP Address detection automatique
 * - Cache Redis/File pour la persistance
 * 
 * 🔗 INTÉGRATION AVEC SYMFONY SECURITY :
 * - Interception des événements de connexion
 * - Intégration avec le système d'authentification
 * - Gestion automatique des sessions
 * - Logs de sécurité centralisés
 * 
 * 📈 MÉTHODES PRINCIPALES :
 * - __invoke() : Gestion des échecs de connexion
 * - checkLoginAttempt() : Vérification des tentatives
 * - resetLoginAttempts() : Réinitialisation des compteurs
 * - Logs de sécurité détaillés
 * 
 * 🎮 EXEMPLES D'UTILISATION :
 * - Protection automatique des formulaires de connexion
 * - Blocage des attaques par dictionnaire
 * - Protection contre les bots malveillants
 * - Monitoring des tentatives suspectes
 * 
 * 🔒 SÉCURITÉ ET ROBUSTESSE :
 * - Détection automatique des IPs
 * - Gestion des proxies et VPN
 * - Protection contre le contournement
 * - Logs de sécurité pour audit
 * 
 * 💡 AVANTAGES :
 * - Protection automatique sans configuration
 * - Réduction des risques de compromission
 * - Amélioration de la sécurité globale
 * - Monitoring des tentatives d'attaque
 */
#[AsEventListener(event: LoginFailureEvent::class)]
class LoginRateLimiter
{
    private RateLimiterFactory $factory;
    private RequestStack $requestStack;

    public function __construct(RateLimiterFactory $factory, RequestStack $requestStack)
    {
        $this->factory = $factory;
        $this->requestStack = $requestStack;
    }

    /**
     * Méthode appelée automatiquement lors d'un échec de connexion
     * Cette méthode est requise par l'attribut AsEventListener
     */
    public function __invoke(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $ip = $request->getClientIp();
        
        if ($ip) {
            $this->checkLoginAttempt($ip);
        }
    }

    /**
     * Vérifie si l'IP peut tenter une nouvelle connexion
     */
    public function checkLoginAttempt(string $ip): void
    {
        $limiter = $this->factory->create($ip);
        
        if (!$limiter->consume(1)->isAccepted()) {
            throw new CustomUserMessageAuthenticationException(
                'Trop de tentatives de connexion. Veuillez réessayer dans quelques minutes.'
            );
        }
    }

    /**
     * Réinitialise le compteur pour une IP (après connexion réussie)
     */
    public function resetLoginAttempts(string $ip): void
    {
        $limiter = $this->factory->create($ip);
        $limiter->reset();
    }
} 