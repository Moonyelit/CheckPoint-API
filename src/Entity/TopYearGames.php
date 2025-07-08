<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'TopYearGames',
    description: 'Top jeux de l\'année (365 derniers jours, dédupliqué)',
    normalizationContext: ['groups' => ['top_year_games:read']],
    provider: \App\State\TopYearGamesProvider::class,
    paginationEnabled: false,
    operations: [
        new GetCollection()
    ]
)]
class TopYearGames
{
    #[Groups(['top_year_games:read'])]
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