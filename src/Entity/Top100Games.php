<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'Top100Games',
    description: 'Top 100 jeux de tous les temps (note >= 75, votes >= 80)',
    normalizationContext: ['groups' => ['top_100_games:read']],
    provider: \App\State\Top100GamesProvider::class,
    paginationEnabled: false,
    operations: [
        new GetCollection()
    ]
)]
class Top100Games
{
    #[Groups(['top_100_games:read'])]
    public array $games = [];

    public function __construct(array $games = [])
    {
        $this->games = $games;
    }

    public function getGames(): array
    {
        return $this->games;
    }

    public function setGames(array $games): self
    {
        $this->games = $games;
        return $this;
    }
} 