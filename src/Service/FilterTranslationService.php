<?php

namespace App\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

class FilterTranslationService
{
    public function __construct(
        private TranslatorInterface $translator
    ) {}

    /**
     * Traduit un tableau de valeurs pour un type de filtre donné
     */
    public function translateFilterValues(string $filterType, array $values): array
    {
        // Éliminer les doublons avant la traduction
        $uniqueValues = array_unique($values);
        
        $translatedValues = array_map(
            fn($value) => $this->translator->trans("filter.{$filterType}.{$value}", [], 'filters'),
            $uniqueValues
        );
        
        // Éliminer les doublons après traduction et retourner un tableau trié
        return array_unique($translatedValues);
    }

    /**
     * Récupère le label traduit d'un type de filtre
     */
    public function getFilterLabel(string $filterType): string
    {
        return $this->translator->trans("filter.labels.{$filterType}", [], 'filters');
    }

    /**
     * Récupère tous les types de filtres disponibles
     */
    public function getAvailableFilterTypes(): array
    {
        return ['genres', 'platforms', 'gameModes', 'perspectives'];
    }

    /**
     * Récupère toutes les traductions pour un type de filtre
     */
    public function getAllTranslationsForFilter(string $filterType): array
    {
        $translations = [];
        $availableValues = $this->getAvailableValuesForFilter($filterType);
        
        foreach ($availableValues as $value) {
            $translatedValue = $this->translator->trans("filter.{$filterType}.{$value}", [], 'filters');
            $translations[$value] = $translatedValue;
        }
        
        return $translations;
    }

    /**
     * Récupère les valeurs disponibles pour un type de filtre
     * Cette méthode pourrait être étendue pour récupérer depuis la base de données
     */
    private function getAvailableValuesForFilter(string $filterType): array
    {
        $values = [
            'genres' => [
                'Action', 'Adventure', 'Action-adventure', 'RPG', 'Action RPG', 'Strategy', 'Simulation', 'Simulator',
                'Puzzle', 'Racing', 'Sports', 'Sport', 'Fighting', 'Shooter', 'Platform',
                'Point-and-click', 'Visual Novel', 'Indie', 'Arcade', 'Card & Board Game',
                'MOBA', 'Music', 'Quiz/Trivia', 'Hack and slash/Beat em up', 'Hack and slash', "Hack and slash/Beat 'em up", 'Tactical',
                'Pinball', 'MMORPG', 'MMO', 'Platformer', 'Survival horror'
            ],
            'platforms' => [
                'PC (Microsoft Windows)', 'PC', 'Nintendo Switch', 'Nintendo Switch 2', 'PlayStation 4', 'PlayStation 5',
                'Xbox One', 'Xbox Series X|S', 'Nintendo 3DS', 'New Nintendo 3DS', 'Nintendo DS', 'PlayStation 3',
                'PlayStation 2', 'PlayStation', 'Xbox 360', 'Xbox', 'Nintendo 64',
                'Game Boy Advance', 'Game Boy Color', 'Game Boy', 'PlayStation Vita',
                'PlayStation Portable', 'macOS', 'Mac', 'Linux', 'Android', 'iOS',
                '64DD', 'Arcade', 'Google Stadia', 'Meta Quest 2', 'Oculus Quest', 'Oculus Rift',
                'PlayStation VR2', 'Satellaview', 'SteamVR', 'Super Famicom', 'Super Nintendo Entertainment System',
                'Web browser', 'Wii', 'Wii U', 'Windows Mixed Reality'
            ],
            'gameModes' => [
                'Single player', 'Multiplayer', 'Co-operative', 'Split screen',
                'Massively Multiplayer Online (MMO)', 'Battle Royale', 'Online Co-op',
                'Local Co-op', 'Online PvP', 'Local PvP'
            ],
            'perspectives' => [
                'First person', 'Third person', 'Bird view', 'Bird view / Isometric', 'Side view', 'Text',
                'Virtual Reality', 'Augmented Reality', 'Isometric', 'Top-down',
                'Fixed camera', 'Free camera'
            ]
        ];

        return $values[$filterType] ?? [];
    }
} 