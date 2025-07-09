<?php

namespace App\Security;

use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * 🛡️ LOGIN RATE LIMITER - PROTECTION CONTRE LES ATTAQUES PAR FORCE BRUTE
 * 
 * Cette classe limite le nombre de tentatives de connexion par IP
 * pour protéger contre les attaques par force brute.
 */
class LoginRateLimiter
{
    private RateLimiterFactory $factory;

    public function __construct(RateLimiterFactory $factory)
    {
        $this->factory = $factory;
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