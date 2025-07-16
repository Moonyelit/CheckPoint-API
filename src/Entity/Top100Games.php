<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'Top100Games',
    description: 'Top 100 jeux de tous les temps avec critères configurables',
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

    #[Groups(['top_100_games:read'])]
    public array $criteria = [];

    #[Groups(['top_100_games:read'])]
    public int $totalCount = 0;

    public function __construct(array $games = [], array $criteria = [], int $totalCount = 0)
    {
        $this->games = $games;
        $this->criteria = $criteria;
        $this->totalCount = $totalCount;
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

    public function getCriteria(): array
    {
        return $this->criteria;
    }

    public function setCriteria(array $criteria): self
    {
        $this->criteria = $criteria;
        return $this;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function setTotalCount(int $totalCount): self
    {
        $this->totalCount = $totalCount;
        return $this;
    }
} 