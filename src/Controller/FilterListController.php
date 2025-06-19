<?php

namespace App\Controller;

use App\Repository\GameRepository;
use App\Service\FilterTranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class FilterListController extends AbstractController
{
    public function __construct(
        private GameRepository $gameRepository,
        private FilterTranslationService $filterTranslationService
    ) {}

    #[Route('/api/filters/{filterType}', name: 'api_filters', methods: ['GET'])]
    public function __invoke(string $filterType): JsonResponse
    {
        // Vérifier que le type de filtre est valide
        if (!in_array($filterType, $this->filterTranslationService->getAvailableFilterTypes())) {
            return $this->json(['error' => 'Type de filtre non valide'], 400);
        }

        // Récupérer les valeurs depuis la base de données
        $allValues = $this->gameRepository->createQueryBuilder('g')
            ->select("g.{$filterType}")
            ->getQuery()
            ->getResult();

        // Extraire et aplatir les valeurs
        $flatValues = array_unique(array_merge(...array_filter(array_map(fn($g) => $g[$filterType] ?? [], $allValues))));
        sort($flatValues);

        // Traduire les valeurs
        $translatedValues = $this->filterTranslationService->translateFilterValues($filterType, $flatValues);
        
        // Éliminer les doublons après traduction et trier
        $translatedValues = array_unique($translatedValues);
        sort($translatedValues);

        return $this->json([
            'filterType' => $filterType,
            'label' => $this->filterTranslationService->getFilterLabel($filterType),
            'values' => $translatedValues
        ]);
    }

    #[Route('/api/filters', name: 'api_all_filters', methods: ['GET'])]
    public function getAllFilters(): JsonResponse
    {
        $filters = [];
        
        foreach ($this->filterTranslationService->getAvailableFilterTypes() as $filterType) {
            $allValues = $this->gameRepository->createQueryBuilder('g')
                ->select("g.{$filterType}")
                ->getQuery()
                ->getResult();

            $flatValues = array_unique(array_merge(...array_filter(array_map(fn($g) => $g[$filterType] ?? [], $allValues))));
            sort($flatValues);

            $translatedValues = $this->filterTranslationService->translateFilterValues($filterType, $flatValues);
            
            // Éliminer les doublons après traduction et trier
            $translatedValues = array_unique($translatedValues);
            sort($translatedValues);

            $filters[$filterType] = [
                'label' => $this->filterTranslationService->getFilterLabel($filterType),
                'values' => $translatedValues
            ];
        }

        return $this->json($filters);
    }
} 