<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'TopYearGames',
    description: 'Top jeux de l\'année (365 derniers jours, dédupliqué)',
    normalizationContext: ['groups' => ['game:read']],
    provider: \App\State\TopYearGamesProvider::class,
    paginationEnabled: false,
    operations: [
        new GetCollection()
    ]
)]
class TopYearGameCollectionOutput
{
    #[Groups(['game:read'])]
    public array $games = [];

    public function __construct(array $games)
    {
        $this->games = $games;
    }
} 