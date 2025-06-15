<?php
// src/Security/LoginRateLimiter.php
namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class LoginRateLimiter implements RequestRateLimiterInterface
{
    private $limiter;

    public function __construct(RateLimiterFactory $factory)
    {
        $this->limiter = $factory;
    }

    public function consume(Request $request): RateLimit
    {
        $limiter = $this->limiter->create($request->getClientIp());
        $limit = $limiter->consume(1);
        
        if (false === $limit->isAccepted()) {
            throw new CustomUserMessageAuthenticationException('Trop de tentatives de connexion. Veuillez réessayer dans quelques minutes.');
        }

        return $limit;
    }

    public function reset(Request $request): void
    {
        $this->limiter->create($request->getClientIp())->reset();
    }
}
