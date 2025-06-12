<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Wallpaper;
use App\Entity\UserWallpaper;
use App\Repository\WallpaperRepository;
use App\Repository\UserWallpaperRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/wallpapers', name: 'api_wallpapers_')]
class WallpaperController extends AbstractController
{
    public function __construct(
        private WallpaperRepository $wallpaperRepository,
        private UserWallpaperRepository $userWallpaperRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Récupère tous les wallpapers disponibles avec les informations du jeu
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $wallpapers = $this->wallpaperRepository->createQueryBuilder('w')
            ->leftJoin('w.game', 'g')
            ->addSelect('g')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($wallpapers as $wallpaper) {
            $game = $wallpaper->getGame();
            $data[] = [
                'id' => $wallpaper->getId(),
                'image' => $wallpaper->getImage(),
                'game' => [
                    'id' => $game->getId(),
                    'title' => $game->getTitle(),
                    'coverUrl' => $game->getCoverUrl(),
                    'developer' => $game->getDeveloper(),
                    'genres' => $game->getGenres(),
                    'totalRating' => $game->getTotalRating()
                ]
            ];
        }

        return $this->json([
            'wallpapers' => $data,
            'total' => count($data)
        ]);
    }

    /**
     * Récupère les wallpapers sélectionnés par l'utilisateur connecté
     */
    #[Route('/my-selection', name: 'my_selection', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function mySelection(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $userWallpapers = $this->userWallpaperRepository->findActiveWallpapersByUser($user);
        
        $data = [];
        foreach ($userWallpapers as $userWallpaper) {
            $wallpaper = $userWallpaper->getWallpaper();
            $game = $wallpaper->getGame();
            
            $data[] = [
                'id' => $userWallpaper->getId(),
                'selectedAt' => $userWallpaper->getSelectedAt()->format('c'),
                'wallpaper' => [
                    'id' => $wallpaper->getId(),
                    'image' => $wallpaper->getImage(),
                    'game' => [
                        'id' => $game->getId(),
                        'title' => $game->getTitle(),
                        'coverUrl' => $game->getCoverUrl()
                    ]
                ]
            ];
        }

        return $this->json([
            'userWallpapers' => $data,
            'total' => count($data)
        ]);
    }

    /**
     * Ajoute un wallpaper à la sélection de l'utilisateur
     */
    #[Route('/{id}/select', name: 'select', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function selectWallpaper(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $wallpaper = $this->wallpaperRepository->find($id);
        if (!$wallpaper) {
            return $this->json(['error' => 'Wallpaper not found'], 404);
        }

        // Vérifier si l'utilisateur a déjà sélectionné ce wallpaper
        $existingSelection = $this->userWallpaperRepository->findByUserAndWallpaper($user, $wallpaper);
        
        if ($existingSelection) {
            if ($existingSelection->isActive()) {
                return $this->json(['message' => 'Wallpaper already selected'], 200);
            } else {
                // Réactiver la sélection
                $existingSelection->setIsActive(true);
                $existingSelection->setSelectedAt(new \DateTimeImmutable());
            }
        } else {
            // Créer une nouvelle sélection
            $userWallpaper = new UserWallpaper();
            $userWallpaper->setUser($user);
            $userWallpaper->setWallpaper($wallpaper);
            $this->entityManager->persist($userWallpaper);
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Wallpaper selected successfully',
            'wallpaper' => [
                'id' => $wallpaper->getId(),
                'image' => $wallpaper->getImage(),
                'game' => [
                    'title' => $wallpaper->getGame()->getTitle()
                ]
            ]
        ]);
    }

    /**
     * Retire un wallpaper de la sélection de l'utilisateur
     */
    #[Route('/{id}/unselect', name: 'unselect', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function unselectWallpaper(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $wallpaper = $this->wallpaperRepository->find($id);
        if (!$wallpaper) {
            return $this->json(['error' => 'Wallpaper not found'], 404);
        }

        $userWallpaper = $this->userWallpaperRepository->findByUserAndWallpaper($user, $wallpaper);
        
        if (!$userWallpaper || !$userWallpaper->isActive()) {
            return $this->json(['error' => 'Wallpaper not selected'], 404);
        }

        $userWallpaper->setIsActive(false);
        $this->entityManager->flush();

        return $this->json(['message' => 'Wallpaper unselected successfully']);
    }

    /**
     * Récupère un wallpaper aléatoire parmi ceux sélectionnés par l'utilisateur
     * Si aucun n'est sélectionné, retourne un wallpaper aléatoire parmi tous
     */
    #[Route('/random', name: 'random', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function randomWallpaper(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Récupérer les wallpapers sélectionnés par l'utilisateur
        $userWallpapers = $this->userWallpaperRepository->findActiveWallpapersByUser($user);
        
        if (!empty($userWallpapers)) {
            // Sélectionner un wallpaper aléatoire parmi ceux de l'utilisateur
            $randomUserWallpaper = $userWallpapers[array_rand($userWallpapers)];
            $wallpaper = $randomUserWallpaper->getWallpaper();
        } else {
            // Sélectionner un wallpaper aléatoire parmi tous
            $allWallpapers = $this->wallpaperRepository->findAll();
            if (empty($allWallpapers)) {
                return $this->json(['error' => 'No wallpapers available'], 404);
            }
            $wallpaper = $allWallpapers[array_rand($allWallpapers)];
        }

        $game = $wallpaper->getGame();
        
        return $this->json([
            'wallpaper' => [
                'id' => $wallpaper->getId(),
                'image' => $wallpaper->getImage(),
                'game' => [
                    'id' => $game->getId(),
                    'title' => $game->getTitle(),
                    'coverUrl' => $game->getCoverUrl(),
                    'developer' => $game->getDeveloper()
                ]
            ],
            'isUserSelected' => !empty($userWallpapers)
        ]);
    }
} 