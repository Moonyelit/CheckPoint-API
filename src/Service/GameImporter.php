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
     * Importe les jeux du Top 100 d'IGDB.
     * 
     * Cette méthode récupère les jeux du top 100 avec les meilleures notes
     * et les sauvegarde en base de données avec leurs statistiques de rating.
     */
    public function importTop100Games(): void
    {
        // Récupère les jeux du top 100 depuis l'API IGDB
        $games = $this->igdbClient->getTop100Games();

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

                // Mise à jour des statistiques de rating
                $existingGame->setTotalRatingCount($apiGame['total_rating_count'] ?? null);
                $existingGame->setFollows($apiGame['follows'] ?? $existingGame->getFollows());
                $existingGame->setLastPopularityUpdate(new \DateTimeImmutable());

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

            // Ajout des statistiques de rating du top 100
            $game->setTotalRatingCount($apiGame['total_rating_count'] ?? null);
            $game->setFollows($apiGame['follows'] ?? null);
            $game->setLastPopularityUpdate(new \DateTimeImmutable());

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

    /**
     * Importe les meilleurs jeux de l'année (365 derniers jours).
     */
    public function importTopYearGames(): int
    {
        echo "🎮 Début de l'import des meilleurs jeux de l'année...\n";

        $games = $this->igdbClient->getTopYearGames();
        $processedGames = 0;

        foreach ($games as $gameData) {
            try {
                // Vérifie si le jeu existe déjà dans la base
                $existingGame = $this->gameRepository->findOneBy(['igdbId' => $gameData['id']]);

                if ($existingGame) {
                    // Met à jour le jeu existant
                    $existingGame->setTitle($gameData['name'] ?? $existingGame->getTitle());
                    $existingGame->setSummary($gameData['summary'] ?? $existingGame->getSummary());
                    
                    // Améliore la qualité de l'image si disponible
                    if (isset($gameData['cover']['url'])) {
                        $highQualityUrl = $this->igdbClient->improveImageQuality('https:' . $gameData['cover']['url'], 't_cover_big');
                        $existingGame->setCoverUrl($highQualityUrl);
                    }
                    
                    $existingGame->setTotalRating($gameData['total_rating'] ?? $existingGame->getTotalRating());
                    $existingGame->setTotalRatingCount($gameData['total_rating_count'] ?? $existingGame->getTotalRatingCount());

                    if (isset($gameData['first_release_date'])) {
                        $existingGame->setReleaseDate((new \DateTime())->setTimestamp($gameData['first_release_date']));
                    }

                    if (isset($gameData['platforms'])) {
                        $platforms = array_map(fn($platform) => $platform['name'], $gameData['platforms']);
                        $existingGame->setPlatforms($platforms);
                    }

                    if (isset($gameData['genres'])) {
                        $genres = array_map(fn($genre) => $genre['name'], $gameData['genres']);
                        $existingGame->setGenres($genres);
                    }

                    $existingGame->setUpdatedAt(new \DateTimeImmutable());
                    echo "📝 Mis à jour : {$gameData['name']}\n";
                } else {
                    // Crée un nouveau jeu
                    $game = new Game();
                    $game->setIgdbId($gameData['id']);
                    $game->setTitle($gameData['name'] ?? 'Inconnu');
                    $game->setSummary($gameData['summary'] ?? null);
                    
                    // Améliore la qualité de l'image si disponible
                    if (isset($gameData['cover']['url'])) {
                        $highQualityUrl = $this->igdbClient->improveImageQuality('https:' . $gameData['cover']['url'], 't_cover_big');
                        $game->setCoverUrl($highQualityUrl);
                    }
                    
                    $game->setTotalRating($gameData['total_rating'] ?? null);
                    $game->setTotalRatingCount($gameData['total_rating_count'] ?? null);

                    if (isset($gameData['first_release_date'])) {
                        $game->setReleaseDate((new \DateTime())->setTimestamp($gameData['first_release_date']));
                    }

                    $genres = isset($gameData['genres']) ? array_map(fn($genre) => $genre['name'], $gameData['genres']) : [];
                    $game->setGenres($genres);

                    $platforms = isset($gameData['platforms']) ? array_map(fn($platform) => $platform['name'], $gameData['platforms']) : [];
                    $game->setPlatforms($platforms);

                    if (isset($gameData['involved_companies'][0]['company']['name'])) {
                        $game->setDeveloper($gameData['involved_companies'][0]['company']['name']);
                    }

                    if (isset($gameData['screenshots']) && is_array($gameData['screenshots'])) {
                        $screenshotData = $this->igdbClient->getScreenshots($gameData['screenshots']);
                        foreach ($screenshotData as $data) {
                            $screenshot = new Screenshot();
                            $screenshot->setImage('https:' . $data['url']);
                            $screenshot->setGame($game);
                            $game->addScreenshot($screenshot);
                        }
                    }

                    $game->setCreatedAt(new \DateTimeImmutable());
                    $this->entityManager->persist($game);
                    echo "✨ Créé : {$gameData['name']}\n";
                }

                $processedGames++;

                // Sauvegarde toutes les 10 opérations pour éviter la surcharge mémoire
                if ($processedGames % 10 === 0) {
                    $this->entityManager->flush();
                }
            } catch (\Exception $e) {
                echo "❌ Erreur lors du traitement de {$gameData['name']}: {$e->getMessage()}\n";
            }
        }

        // Sauvegarde finale
        $this->entityManager->flush();

        echo "✅ Import des jeux de l'année terminé ! Jeux traités : $processedGames\n";
        return $processedGames;
    }
}
