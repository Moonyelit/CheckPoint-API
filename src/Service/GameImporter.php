<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\Screenshot;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;

class GameImporter
{
    private IgdbClient $igdbClient;
    private GameRepository $gameRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        IgdbClient $igdbClient,
        GameRepository $gameRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->igdbClient = $igdbClient;
        $this->gameRepository = $gameRepository;
        $this->entityManager = $entityManager;
    }

    public function importPopularGames(): void
    {
        // Récupère les jeux populaires depuis l'API IGDB
        $games = $this->igdbClient->getPopularGames();

        foreach ($games as $apiGame) {
            $igdbId = $apiGame['id'];

            // Vérifie si le jeu existe déjà en base
            $existingGame = $this->gameRepository->findOneBy(['igdbId' => $igdbId]);

            if ($existingGame) {
                // Mise à jour des champs modifiables
                $existingGame->setTitle($apiGame['name'] ?? $existingGame->getTitle());
                $existingGame->setSummary($apiGame['summary'] ?? $existingGame->getSummary());
                
                // Améliore la qualité de l'image si disponible
                if (isset($apiGame['cover']['url'])) {
                    $highQualityUrl = $this->igdbClient->improveImageQuality('https:' . $apiGame['cover']['url'], 't_cover_big');
                    $existingGame->setCoverUrl($highQualityUrl);
                }
                
                $existingGame->setTotalRating($apiGame['total_rating'] ?? $existingGame->getTotalRating());

                if (isset($apiGame['first_release_date'])) {
                    $existingGame->setReleaseDate((new \DateTime())->setTimestamp($apiGame['first_release_date']));
                }

                if (isset($apiGame['platforms'])) {
                    $platforms = array_map(fn($platform) => $platform['name'], $apiGame['platforms']);
                    $existingGame->setPlatforms($platforms);
                }

                if (isset($apiGame['genres'])) {
                    $genres = array_map(fn($genre) => $genre['name'], $apiGame['genres']);
                    $existingGame->setGenres($genres);
                }

                $existingGame->setUpdatedAt(new \DateTimeImmutable());

                // Pas besoin de créer un nouveau jeu, on passe au suivant
                continue;
            }

            // Sinon, on crée un nouveau jeu
            $game = new Game();
            $game->setIgdbId($igdbId);
            $game->setTitle($apiGame['name'] ?? 'Inconnu');
            $game->setSummary($apiGame['summary'] ?? null);
            
            // Améliore la qualité de l'image si disponible
            if (isset($apiGame['cover']['url'])) {
                $highQualityUrl = $this->igdbClient->improveImageQuality('https:' . $apiGame['cover']['url'], 't_cover_big');
                $game->setCoverUrl($highQualityUrl);
            }
            
            $game->setTotalRating($apiGame['total_rating'] ?? null);

            if (isset($apiGame['first_release_date'])) {
                $game->setReleaseDate((new \DateTime())->setTimestamp($apiGame['first_release_date']));
            }

            $genres = isset($apiGame['genres']) ? array_map(fn($genre) => $genre['name'], $apiGame['genres']) : [];
            $game->setGenres($genres);

            $platforms = isset($apiGame['platforms']) ? array_map(fn($platform) => $platform['name'], $apiGame['platforms']) : [];
            $game->setPlatforms($platforms);

            if (isset($apiGame['involved_companies'][0]['company']['name'])) {
                $game->setDeveloper($apiGame['involved_companies'][0]['company']['name']);
            }

            if (isset($apiGame['screenshots']) && is_array($apiGame['screenshots'])) {
                $screenshotData = $this->igdbClient->getScreenshots($apiGame['screenshots']);
                foreach ($screenshotData as $data) {
                    $screenshot = new Screenshot();
                    $screenshot->setImage('https:' . $data['url']);
                    $screenshot->setGame($game);
                    $game->addScreenshot($screenshot);
                }
            }

            $game->setCreatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($game);
        }

        // Sauvegarde toutes les modifications
        $this->entityManager->flush();
    }

    /**
     * Importe les jeux populaires du moment depuis IGDB.
     * 
     * Cette méthode récupère les jeux tendance (récents et populaires)
     * et les sauvegarde en base de données.
     */
    public function importTrendingGames(): void
    {
        // Récupère les jeux populaires du moment depuis l'API IGDB
        $games = $this->igdbClient->getTrendingGames();

        foreach ($games as $apiGame) {
            $igdbId = $apiGame['id'];

            // Vérifie si le jeu existe déjà en base
            $existingGame = $this->gameRepository->findOneBy(['igdbId' => $igdbId]);

            if ($existingGame) {
                // Mise à jour des champs modifiables
                $existingGame->setTitle($apiGame['name'] ?? $existingGame->getTitle());
                $existingGame->setSummary($apiGame['summary'] ?? $existingGame->getSummary());
                
                // Améliore la qualité de l'image si disponible
                if (isset($apiGame['cover']['url'])) {
                    $highQualityUrl = $this->igdbClient->improveImageQuality('https:' . $apiGame['cover']['url'], 't_cover_big');
                    $existingGame->setCoverUrl($highQualityUrl);
                }
                
                $existingGame->setTotalRating($apiGame['total_rating'] ?? $existingGame->getTotalRating());

                if (isset($apiGame['first_release_date'])) {
                    $existingGame->setReleaseDate((new \DateTime())->setTimestamp($apiGame['first_release_date']));
                }

                if (isset($apiGame['platforms'])) {
                    $platforms = array_map(fn($platform) => $platform['name'], $apiGame['platforms']);
                    $existingGame->setPlatforms($platforms);
                }

                if (isset($apiGame['genres'])) {
                    $genres = array_map(fn($genre) => $genre['name'], $apiGame['genres']);
                    $existingGame->setGenres($genres);
                }

                $existingGame->setUpdatedAt(new \DateTimeImmutable());
                continue;
            }

            // Sinon, on crée un nouveau jeu
            $game = new Game();
            $game->setIgdbId($igdbId);
            $game->setTitle($apiGame['name'] ?? 'Inconnu');
            $game->setSummary($apiGame['summary'] ?? null);
            
            // Améliore la qualité de l'image si disponible
            if (isset($apiGame['cover']['url'])) {
                $highQualityUrl = $this->igdbClient->improveImageQuality('https:' . $apiGame['cover']['url'], 't_cover_big');
                $game->setCoverUrl($highQualityUrl);
            }
            
            $game->setTotalRating($apiGame['total_rating'] ?? null);

            if (isset($apiGame['first_release_date'])) {
                $game->setReleaseDate((new \DateTime())->setTimestamp($apiGame['first_release_date']));
            }

            $genres = isset($apiGame['genres']) ? array_map(fn($genre) => $genre['name'], $apiGame['genres']) : [];
            $game->setGenres($genres);

            $platforms = isset($apiGame['platforms']) ? array_map(fn($platform) => $platform['name'], $apiGame['platforms']) : [];
            $game->setPlatforms($platforms);

            if (isset($apiGame['involved_companies'][0]['company']['name'])) {
                $game->setDeveloper($apiGame['involved_companies'][0]['company']['name']);
            }

            if (isset($apiGame['screenshots']) && is_array($apiGame['screenshots'])) {
                $screenshotData = $this->igdbClient->getScreenshots($apiGame['screenshots']);
                foreach ($screenshotData as $data) {
                    $screenshot = new Screenshot();
                    $screenshot->setImage('https:' . $data['url']);
                    $screenshot->setGame($game);
                    $game->addScreenshot($screenshot);
                }
            }

            $game->setCreatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($game);
        }

        // Sauvegarde toutes les modifications
        $this->entityManager->flush();
    }

    public function importGameBySearch(string $search): ?Game
    {
        // Recherche des jeux correspondant au terme
        $results = $this->igdbClient->searchGames($search);
        if (empty($results)) {
            return null;
        }

        // Prend le jeu le plus pertinent
        $apiGame = $results[0];
        $existingGame = $this->gameRepository->findOneBy(['igdbId' => $apiGame['id']]);

        if ($existingGame) {
            return $existingGame;
        }

        // Création d'un nouveau jeu
        $game = new Game();
        $game->setIgdbId($apiGame['id']);
        $game->setTitle($apiGame['name'] ?? 'Inconnu');
        $game->setSummary($apiGame['summary'] ?? null);
        
        // Améliore la qualité de l'image si disponible
        if (isset($apiGame['cover']['url'])) {
            $highQualityUrl = $this->igdbClient->improveImageQuality('https:' . $apiGame['cover']['url'], 't_cover_big');
            $game->setCoverUrl($highQualityUrl);
        }
        
        $game->setTotalRating($apiGame['total_rating'] ?? null);

        if (isset($apiGame['first_release_date'])) {
            $game->setReleaseDate((new \DateTime())->setTimestamp($apiGame['first_release_date']));
        }

        $genres = isset($apiGame['genres']) ? array_map(fn($genre) => $genre['name'], $apiGame['genres']) : [];
        $game->setGenres($genres);

        $platforms = isset($apiGame['platforms']) ? array_map(fn($platform) => $platform['name'], $apiGame['platforms']) : [];
        $game->setPlatforms($platforms);

        if (isset($apiGame['involved_companies'][0]['company']['name'])) {
            $game->setDeveloper($apiGame['involved_companies'][0]['company']['name']);
        }

        if (isset($apiGame['screenshots']) && is_array($apiGame['screenshots'])) {
            $screenshotData = $this->igdbClient->getScreenshots($apiGame['screenshots']);
            foreach ($screenshotData as $data) {
                $screenshot = new Screenshot();
                $screenshot->setImage('https:' . $data['url']);
                $screenshot->setGame($game);
                $game->addScreenshot($screenshot);
            }
        }

        $game->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $game;
    }

    public function importGamesBySearch(string $query): array
    {
        $results = $this->igdbClient->searchGames($query);
        $importedGames = [];

        foreach ($results as $apiGame) {
            $existing = $this->gameRepository->findOneBy(['igdbId' => $apiGame['id']]);
            if ($existing) {
                $importedGames[] = $existing;
                continue;
            }

            // Création d'un nouveau jeu
            $game = new Game();
            $game->setIgdbId($apiGame['id']);
            $game->setTitle($apiGame['name'] ?? 'Inconnu');
            $game->setSummary($apiGame['summary'] ?? null);
            
            // Améliore la qualité de l'image si disponible
            if (isset($apiGame['cover']['url'])) {
                $highQualityUrl = $this->igdbClient->improveImageQuality('https:' . $apiGame['cover']['url'], 't_cover_big');
                $game->setCoverUrl($highQualityUrl);
            }
            
            $game->setTotalRating($apiGame['total_rating'] ?? null);

            if (isset($apiGame['first_release_date'])) {
                $game->setReleaseDate((new \DateTime())->setTimestamp($apiGame['first_release_date']));
            }

            $genres = isset($apiGame['genres']) ? array_map(fn($genre) => $genre['name'], $apiGame['genres']) : [];
            $game->setGenres($genres);

            $platforms = isset($apiGame['platforms']) ? array_map(fn($platform) => $platform['name'], $apiGame['platforms']) : [];
            $game->setPlatforms($platforms);

            if (isset($apiGame['involved_companies'][0]['company']['name'])) {
                $game->setDeveloper($apiGame['involved_companies'][0]['company']['name']);
            }

            if (isset($apiGame['screenshots'])) {
                $screens = $this->igdbClient->getScreenshots($apiGame['screenshots']);
                foreach ($screens as $s) {
                    $screenshot = new Screenshot();
                    $screenshot->setImage('https:' . $s['url']);
                    $screenshot->setGame($game);
                    $game->addScreenshot($screenshot);
                }
            }

            $this->entityManager->persist($game);
            $importedGames[] = $game;
        }

        $this->entityManager->flush();

        return $importedGames;
    }
}
