<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'Top100Games',
    description: 'Top 100 jeux de tous les temps (note >= 75, votes >= 80)',
    normalizationContext: ['groups' => ['game:read']],
    provider: \App\State\Top100GamesProvider::class,
    paginationEnabled: false,
    operations: [
        new GetCollection()
    ]
)]
class Top100GameCollectionOutput
{
    #[Groups(['game:read'])]
    public array $games = [];

    public function __construct(array $games)
    {
        $this->games = $games;
    }
} 