<?php
// src/Security/LoginRateLimiter.php
namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimit;

class LoginRateLimiter implements RequestRateLimiterInterface
{
    public function __construct(
        private RateLimiterFactory $factory
    ) {}

    public function consume(Request $request): RateLimit
    {
        // identifiant unique par IP + email (pour isoler chaque utilisateur)
        $key = $request->getClientIp()
            . '|' . ($request->request->get('email') ?? '');

        // consomme une unité dans le rate‐limiter configuré
        return $this->factory->create($key)->consume();
    }

    public function reset(Request $request): void
    {
        $key = $request->getClientIp()
            . '|' . ($request->request->get('email') ?? '');

        // réinitialise le compteur (utile en cas de succès, si tu veux remettre à zéro)
        $this->factory->create($key)->reset();
    }
}
