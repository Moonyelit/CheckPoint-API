<?php

namespace App\Service;

class TranslationService
{
    private const TRANSLATIONS = [
        'genres' => [
            'Action' => 'Action',
            'Adventure' => 'Aventure',
            'Action-adventure' => 'Action-aventure',
            'RPG' => 'RPG',
            'Strategy' => 'Stratégie',
            'Simulation' => 'Simulation',
            'Puzzle' => 'Puzzle',
            'Racing' => 'Course',
            'Sports' => 'Sport',
            'Fighting' => 'Combat',
            'Shooter' => 'Tir',
            'Platform' => 'Plateforme',
            'Point-and-click' => 'Point-and-click',
            'Visual Novel' => 'Visual Novel',
            'Indie' => 'Indépendant',
            'Arcade' => 'Arcade',
            'Card & Board Game' => 'Jeu de cartes & plateau',
            'MOBA' => 'MOBA',
            'Music' => 'Musique',
            'Quiz/Trivia' => 'Quiz',
            'Hack and slash/Beat em up' => 'Beat them all',
            'Tactical' => 'Tactique',
            'Pinball' => 'Flipper',
            'MMORPG' => 'MMORPG',
            'MMO' => 'MMO',
            'Platformer' => 'Plateforme',
        ],
        'platforms' => [
            'PC (Microsoft Windows)' => 'PC',
            'Nintendo Switch' => 'Nintendo Switch',
            'PlayStation 4' => 'PlayStation 4',
            'PlayStation 5' => 'PlayStation 5',
            'Xbox One' => 'Xbox One',
            'Xbox Series X|S' => 'Xbox Series X|S',
            'Nintendo 3DS' => 'Nintendo 3DS',
            'Nintendo DS' => 'Nintendo DS',
            'PlayStation 3' => 'PlayStation 3',
            'PlayStation 2' => 'PlayStation 2',
            'PlayStation' => 'PlayStation',
            'Xbox 360' => 'Xbox 360',
            'Xbox' => 'Xbox',
            'Nintendo 64' => 'Nintendo 64',
            'Game Boy Advance' => 'Game Boy Advance',
            'Game Boy Color' => 'Game Boy Color',
            'Game Boy' => 'Game Boy',
            'PlayStation Vita' => 'PlayStation Vita',
            'PlayStation Portable' => 'PlayStation Portable',
            'macOS' => 'macOS',
            'Linux' => 'Linux',
            'Android' => 'Android',
            'iOS' => 'iOS',
        ],
        'gameModes' => [
            'Single player' => 'Solo',
            'Multiplayer' => 'Multijoueur',
            'Co-operative' => 'Coopératif',
            'Split screen' => 'Écran partagé',
            'Massively Multiplayer Online (MMO)' => 'MMO',
            'Battle Royale' => 'Battle Royale',
            'Online Co-op' => 'Coopératif en ligne',
            'Local Co-op' => 'Coopératif local',
            'Online PvP' => 'PvP en ligne',
            'Local PvP' => 'PvP local',
        ],
        'perspectives' => [
            'First person' => 'Vue première personne',
            'Third person' => 'Vue à la troisième personne',
            'Bird view' => 'Vue de dessus',
            'Side view' => 'Vue de côté',
            'Text' => 'Texte',
            'Virtual Reality' => 'Réalité virtuelle',
            'Augmented Reality' => 'Réalité augmentée',
            'Isometric' => 'Vue isométrique',
            'Top-down' => 'Vue de dessus',
            'Fixed camera' => 'Caméra fixe',
            'Free camera' => 'Caméra libre',
        ]
    ];

    private const FILTER_LABELS = [
        'genres' => 'Genre',
        'platforms' => 'Plateforme',
        'gameModes' => 'Mode de jeu',
        'perspectives' => 'Perspective'
    ];

    public function translate(string $filterType, array $values): array
    {
        if (!isset(self::TRANSLATIONS[$filterType])) {
            return $values;
        }

        $translations = self::TRANSLATIONS[$filterType];
        return array_map(fn($value) => $translations[$value] ?? $value, $values);
    }

    public function getFilterLabel(string $filterType): string
    {
        return self::FILTER_LABELS[$filterType] ?? ucfirst($filterType);
    }

    public function getAvailableFilterTypes(): array
    {
        return array_keys(self::TRANSLATIONS);
    }
} 