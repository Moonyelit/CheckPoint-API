<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\Screenshot;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Cocur\Slugify\Slugify;

/**
 * 📦 SERVICE GAME IMPORTER - IMPORTATION & SYNCHRONISATION DES JEUX
 *
 * Ce service gère l'importation, la mise à jour et la synchronisation des jeux vidéo
 * depuis l'API IGDB vers la base de données locale.
 *
 * 🔧 FONCTIONNALITÉS PRINCIPALES :
 *
 * 📥 IMPORTS MASSIFS & CIBLÉS :
 * - Import du Top 100 IGDB (classiques, AAA, populaires)
 * - Import des meilleurs jeux récents (année en cours)
 * - Import des jeux populaires (votes, notes)
 * - Import ciblé par recherche utilisateur
 *
 * 🔄 SYNCHRONISATION & MISE À JOUR :
 * - Mise à jour intelligente des jeux existants (notes, images, genres, etc.)
 * - Ajout des nouveaux jeux absents de la base
 * - Gestion des doublons via l'ID IGDB
 *
 * 🖼️ GESTION DES MÉDIAS :
 * - Téléchargement et association des images de couverture et screenshots
 * - Amélioration automatique de la qualité des images
 *
 * 🎯 UTILISATION :
 * - Utilisé par les commandes d'import, les endpoints d'admin et la recherche intelligente
 * - Permet d'enrichir la base locale pour accélérer les recherches et améliorer l'expérience utilisateur
 *
 * ⚡ EXEMPLES D'USAGE :
 * - Import hebdomadaire du Top 100 pour la homepage
 * - Import des nouveautés pour garder la base à jour
 * - Import à la volée lors d'une recherche utilisateur
 *
 * 💡 AVANTAGES :
 * - Base locale enrichie et cohérente
 * - Réduction des appels à IGDB en temps réel
 * - Expérience utilisateur plus fluide et rapide
 *
 * 🔧 UTILISATION RECOMMANDÉE :
 * - Pour toute opération d'import ou de synchronisation de jeux
 * - Pour garantir la fraîcheur et la qualité des données jeux
 */
class GameImporter
{
    private IgdbClient $igdbClient;
    private GameRepository $gameRepository;
    private EntityManagerInterface $entityManager;
    private Slugify $slugify;

    public function __construct(
        IgdbClient $igdbClient,
        GameRepository $gameRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->igdbClient = $igdbClient;
        $this->gameRepository = $gameRepository;
        $this->entityManager = $entityManager;
        $this->slugify = new Slugify();
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
                if (isset($apiGame['name'])) {
                    $existingGame->setTitle($apiGame['name']);
                    $existingGame->setSlug($this->slugify->slugify($apiGame['name']));
                }
                $existingGame->setSummary($apiGame['summary'] ?? $existingGame->getSummary());
                
                // Améliore la qualité de l'image si disponible
                if (isset($apiGame['cover']['url'])) {
                    $imageUrl = $apiGame['cover']['url'];
                    if (strpos($imageUrl, '//') === 0) {
                        $imageUrl = 'https:' . $imageUrl;
                    }
                    $highQualityUrl = $this->igdbClient->improveImageQuality($imageUrl, 't_cover_big');
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

                if (isset($apiGame['game_modes'])) {
                    $gameModes = array_map(fn($mode) => $mode['name'], $apiGame['game_modes']);
                    $existingGame->setGameModes($gameModes);
                }

                if (isset($apiGame['player_perspectives'])) {
                    $perspectives = array_map(fn($perspective) => $perspective['name'], $apiGame['player_perspectives']);
                    $existingGame->setPerspectives($perspectives);
                }

                $existingGame->setUpdatedAt(new \DateTimeImmutable());

                // Pas besoin de créer un nouveau jeu, on passe au suivant
                continue;
            }

            // Sinon, on crée un nouveau jeu
            $game = new Game();
            $game->setIgdbId($igdbId);
            $title = $apiGame['name'] ?? 'Inconnu';
            $game->setTitle($title);
            $game->setSlug($this->slugify->slugify($title));
            $game->setSummary($apiGame['summary'] ?? null);
            
            // Améliore la qualité de l'image si disponible
            if (isset($apiGame['cover']['url'])) {
                $imageUrl = $apiGame['cover']['url'];
                if (strpos($imageUrl, '//') === 0) {
                    $imageUrl = 'https:' . $imageUrl;
                }
                $highQualityUrl = $this->igdbClient->improveImageQuality($imageUrl, 't_cover_big');
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

            if (isset($apiGame['game_modes'])) {
                $gameModes = array_map(fn($mode) => $mode['name'], $apiGame['game_modes']);
                $game->setGameModes($gameModes);
            }

            if (isset($apiGame['player_perspectives'])) {
                $perspectives = array_map(fn($perspective) => $perspective['name'], $apiGame['player_perspectives']);
                $game->setPerspectives($perspectives);
            }

            if (isset($apiGame['involved_companies'][0]['company']['name'])) {
                $game->setDeveloper($apiGame['involved_companies'][0]['company']['name']);
            }

            if (isset($apiGame['screenshots']) && is_array($apiGame['screenshots'])) {
                $screenshotData = $this->igdbClient->getScreenshots($apiGame['screenshots']);
                foreach ($screenshotData as $data) {
                    $screenshot = new Screenshot();
                    $imageUrl = $data['url'];
                    if (strpos($imageUrl, '//') === 0) {
                        $imageUrl = 'https:' . $imageUrl;
                    }
                    $screenshot->setImage($imageUrl);
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
     * Importe les jeux du Top 100 d'IGDB avec critères dynamiques.
     */
    public function importTop100Games(int $minVotes = 80, int $minRating = 75): void
    {
        $games = $this->igdbClient->getTop100Games($minVotes, $minRating);

        foreach ($games as $apiGame) {
            $igdbId = $apiGame['id'];

            // Vérifie si le jeu existe déjà en base
            $existingGame = $this->gameRepository->findOneBy(['igdbId' => $igdbId]);

            if ($existingGame) {
                // Mise à jour des champs modifiables
                if (isset($apiGame['name'])) {
                    $existingGame->setTitle($apiGame['name']);
                    $existingGame->setSlug($this->slugify->slugify($apiGame['name']));
                }
                $existingGame->setSummary($apiGame['summary'] ?? $existingGame->getSummary());
                
                // Améliore la qualité de l'image si disponible
                if (isset($apiGame['cover']['url'])) {
                    $imageUrl = $apiGame['cover']['url'];
                    if (strpos($imageUrl, '//') === 0) {
                        $imageUrl = 'https:' . $imageUrl;
                    }
                    $highQualityUrl = $this->igdbClient->improveImageQuality($imageUrl, 't_cover_big');
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

                if (isset($apiGame['game_modes'])) {
                    $gameModes = array_map(fn($mode) => $mode['name'], $apiGame['game_modes']);
                    $existingGame->setGameModes($gameModes);
                }

                if (isset($apiGame['player_perspectives'])) {
                    $perspectives = array_map(fn($perspective) => $perspective['name'], $apiGame['player_perspectives']);
                    $existingGame->setPerspectives($perspectives);
                }

                $existingGame->setUpdatedAt(new \DateTimeImmutable());
                continue;
            }

            // Sinon, on crée un nouveau jeu
            $game = new Game();
            $game->setIgdbId($igdbId);
            $title = $apiGame['name'] ?? 'Inconnu';
            $game->setTitle($title);
            $game->setSlug($this->slugify->slugify($title));
            $game->setSummary($apiGame['summary'] ?? null);
            
            // Améliore la qualité de l'image si disponible
            if (isset($apiGame['cover']['url'])) {
                $imageUrl = $apiGame['cover']['url'];
                if (strpos($imageUrl, '//') === 0) {
                    $imageUrl = 'https:' . $imageUrl;
                }
                $highQualityUrl = $this->igdbClient->improveImageQuality($imageUrl, 't_cover_big');
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

            if (isset($apiGame['game_modes'])) {
                $gameModes = array_map(fn($mode) => $mode['name'], $apiGame['game_modes']);
                $game->setGameModes($gameModes);
            }

            if (isset($apiGame['player_perspectives'])) {
                $perspectives = array_map(fn($perspective) => $perspective['name'], $apiGame['player_perspectives']);
                $game->setPerspectives($perspectives);
            }

            if (isset($apiGame['involved_companies'][0]['company']['name'])) {
                $game->setDeveloper($apiGame['involved_companies'][0]['company']['name']);
            }

            if (isset($apiGame['screenshots']) && is_array($apiGame['screenshots'])) {
                $screenshotData = $this->igdbClient->getScreenshots($apiGame['screenshots']);
                foreach ($screenshotData as $data) {
                    $screenshot = new Screenshot();
                    $imageUrl = $data['url'];
                    if (strpos($imageUrl, '//') === 0) {
                        $imageUrl = 'https:' . $imageUrl;
                    }
                    $screenshot->setImage($imageUrl);
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
        // Récupère le premier jeu correspondant à la recherche
        $apiGames = $this->igdbClient->searchGames($search, 1);

        if (empty($apiGames)) {
            return null; // Aucun jeu trouvé
        }

        $apiGame = $apiGames[0];
        $igdbId = $apiGame['id'];

        // Vérifie si le jeu existe déjà
        $game = $this->gameRepository->findOneBy(['igdbId' => $igdbId]);
        if (!$game) {
            $game = new Game();
            $game->setIgdbId($igdbId);
        }

        // Met à jour les informations du jeu
        $title = $apiGame['name'] ?? 'Inconnu';
        $game->setTitle($title);
        $game->setSlug($this->slugify->slugify($title));

        if (isset($apiGame['cover']['url'])) {
            $imageUrl = $apiGame['cover']['url'];
            if (strpos($imageUrl, '//') === 0) {
                $imageUrl = 'https:' . $imageUrl;
            }
            $highQualityUrl = $this->igdbClient->improveImageQuality($imageUrl, 't_cover_big');
            $game->setCoverUrl($highQualityUrl);
        }
        
        $game->setSummary($apiGame['summary'] ?? null);
        $game->setTotalRating($apiGame['total_rating'] ?? null);

        if (isset($apiGame['first_release_date'])) {
            $game->setReleaseDate((new \DateTime())->setTimestamp($apiGame['first_release_date']));
        }

        $genres = isset($apiGame['genres']) ? array_map(fn($genre) => $genre['name'], $apiGame['genres']) : [];
        $game->setGenres($genres);

        $platforms = isset($apiGame['platforms']) ? array_map(fn($platform) => $platform['name'], $apiGame['platforms']) : [];
        $game->setPlatforms($platforms);

        if (isset($apiGame['game_modes'])) {
            $gameModes = array_map(fn($mode) => $mode['name'], $apiGame['game_modes']);
            $game->setGameModes($gameModes);
        }

        if (isset($apiGame['player_perspectives'])) {
            $perspectives = array_map(fn($perspective) => $perspective['name'], $apiGame['player_perspectives']);
            $game->setPerspectives($perspectives);
        }

        if (isset($apiGame['involved_companies'][0]['company']['name'])) {
            $game->setDeveloper($apiGame['involved_companies'][0]['company']['name']);
        }

        if (isset($apiGame['screenshots']) && is_array($apiGame['screenshots'])) {
            $screenshotData = $this->igdbClient->getScreenshots($apiGame['screenshots']);
            foreach ($screenshotData as $data) {
                $screenshot = new Screenshot();
                $imageUrl = $data['url'];
                if (strpos($imageUrl, '//') === 0) {
                    $imageUrl = 'https:' . $imageUrl;
                }
                $screenshot->setImage($imageUrl);
                $screenshot->setGame($game);
                $game->addScreenshot($screenshot);
            }
        }

        $game->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $game;
    }

    public function importGamesBySearch(string $query): array
    {
        // Recherche des jeux via IGDB
        $apiGames = $this->igdbClient->searchGames($query);
        $importedGames = [];

        foreach ($apiGames as $apiGame) {
            $igdbId = $apiGame['id'];

            // Vérifie si le jeu existe déjà
            $game = $this->gameRepository->findOneBy(['igdbId' => $igdbId]);
            if (!$game) {
                $game = new Game();
                $game->setIgdbId($igdbId);
                $game->setCreatedAt(new \DateTimeImmutable());
            }

            // Met à jour les informations du jeu
            $title = $apiGame['name'] ?? 'Inconnu';
            $game->setTitle($title);
            $game->setSlug($this->slugify->slugify($title));
            
            $game->setSummary($apiGame['summary'] ?? null);
            $game->setTotalRating($apiGame['total_rating'] ?? null);

            if (isset($apiGame['first_release_date'])) {
                $game->setReleaseDate((new \DateTime())->setTimestamp($apiGame['first_release_date']));
            }

            $genres = isset($apiGame['genres']) ? array_map(fn($genre) => $genre['name'], $apiGame['genres']) : [];
            $game->setGenres($genres);

            $platforms = isset($apiGame['platforms']) ? array_map(fn($platform) => $platform['name'], $apiGame['platforms']) : [];
            $game->setPlatforms($platforms);

            if (isset($apiGame['game_modes'])) {
                $gameModes = array_map(fn($mode) => $mode['name'], $apiGame['game_modes']);
                $game->setGameModes($gameModes);
            }

            if (isset($apiGame['player_perspectives'])) {
                $perspectives = array_map(fn($perspective) => $perspective['name'], $apiGame['player_perspectives']);
                $game->setPerspectives($perspectives);
            }

            if (isset($apiGame['involved_companies'][0]['company']['name'])) {
                $game->setDeveloper($apiGame['involved_companies'][0]['company']['name']);
            }

            // Gestion améliorée de l'image de couverture
            if (isset($apiGame['cover']['url'])) {
                $imageUrl = $apiGame['cover']['url'];
                if (strpos($imageUrl, '//') === 0) {
                    $imageUrl = 'https:' . $imageUrl;
                }
                $highQualityUrl = $this->igdbClient->improveImageQuality($imageUrl, 't_cover_big');
                $game->setCoverUrl($highQualityUrl);
            } else {
                // Si pas de couverture, essayer de récupérer depuis IGDB avec l'ID
                try {
                    $detailedGame = $this->igdbClient->getGameDetails($igdbId);
                    if (isset($detailedGame['cover']['url'])) {
                        $imageUrl = $detailedGame['cover']['url'];
                        if (strpos($imageUrl, '//') === 0) {
                            $imageUrl = 'https:' . $imageUrl;
                        }
                        $highQualityUrl = $this->igdbClient->improveImageQuality($imageUrl, 't_cover_big');
                        $game->setCoverUrl($highQualityUrl);
                    }
                } catch (\Exception $e) {
                    // Log l'erreur mais continue
                    error_log("Impossible de récupérer les détails du jeu {$igdbId}: " . $e->getMessage());
                }
            }

            // Gestion améliorée des screenshots
            if (isset($apiGame['screenshots']) && is_array($apiGame['screenshots'])) {
                $screenshotData = $this->igdbClient->getScreenshots($apiGame['screenshots']);
                foreach ($screenshotData as $data) {
                    $screenshot = new Screenshot();
                    $imageUrl = $data['url'];
                    if (strpos($imageUrl, '//') === 0) {
                        $imageUrl = 'https:' . $imageUrl;
                    }
                    $screenshot->setImage($imageUrl);
                    $screenshot->setGame($game);
                    $game->addScreenshot($screenshot);
                }
            } else {
                // Si pas de screenshots, essayer de récupérer depuis IGDB avec l'ID
                try {
                    $detailedGame = $this->igdbClient->getGameDetails($igdbId);
                    if (isset($detailedGame['screenshots']) && is_array($detailedGame['screenshots'])) {
                        $screenshotData = $this->igdbClient->getScreenshots($detailedGame['screenshots']);
                        foreach ($screenshotData as $data) {
                            $screenshot = new Screenshot();
                            $imageUrl = $data['url'];
                            if (strpos($imageUrl, '//') === 0) {
                                $imageUrl = 'https:' . $imageUrl;
                            }
                            $screenshot->setImage($imageUrl);
                            $screenshot->setGame($game);
                            $game->addScreenshot($screenshot);
                        }
                    }
                } catch (\Exception $e) {
                    // Log l'erreur mais continue
                    error_log("Impossible de récupérer les screenshots du jeu {$igdbId}: " . $e->getMessage());
                }
            }

            $game->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($game);
            $importedGames[] = $game;
        }

        $this->entityManager->flush();
        return $importedGames;
    }

    /**
     * Importe les meilleurs jeux de l'année (365 derniers jours) avec critères dynamiques.
     */
    public function importTopYearGames(int $minVotes = 80, int $minRating = 75): int
    {
        $games = $this->igdbClient->getTopYearGames($minVotes, $minRating);
        $count = 0;

        foreach ($games as $apiGame) {
            $count++;
            $igdbId = $apiGame['id'];

            $game = $this->gameRepository->findOneBy(['igdbId' => $igdbId]);

            if (!$game) {
                $game = new Game();
                $game->setIgdbId($igdbId);
            }

            $title = $apiGame['name'] ?? 'Titre inconnu';
            $game->setTitle($title);
            $game->setSlug($this->slugify->slugify($title));

            if (isset($apiGame['cover']['url'])) {
                $imageUrl = $apiGame['cover']['url'];
                if (strpos($imageUrl, '//') === 0) {
                    $imageUrl = 'https:' . $imageUrl;
                }
                $highQualityUrl = $this->igdbClient->improveImageQuality($imageUrl, 't_cover_big');
                $game->setCoverUrl($highQualityUrl);
            }

            $game->setSummary($apiGame['summary'] ?? null);
            $game->setTotalRating($apiGame['total_rating'] ?? null);
            $game->setTotalRatingCount($apiGame['total_rating_count'] ?? null);

            if (isset($apiGame['first_release_date'])) {
                $game->setReleaseDate((new \DateTime())->setTimestamp($apiGame['first_release_date']));
            }

            $genres = isset($apiGame['genres']) ? array_map(fn($genre) => $genre['name'], $apiGame['genres']) : [];
            $game->setGenres($genres);

            $platforms = isset($apiGame['platforms']) ? array_map(fn($platform) => $platform['name'], $apiGame['platforms']) : [];
            $game->setPlatforms($platforms);

            if (isset($apiGame['game_modes'])) {
                $gameModes = array_map(fn($mode) => $mode['name'], $apiGame['game_modes']);
                $game->setGameModes($gameModes);
            }

            if (isset($apiGame['player_perspectives'])) {
                $perspectives = array_map(fn($perspective) => $perspective['name'], $apiGame['player_perspectives']);
                $game->setPerspectives($perspectives);
            }

            if (isset($apiGame['involved_companies'][0]['company']['name'])) {
                $game->setDeveloper($apiGame['involved_companies'][0]['company']['name']);
            }

            if (isset($apiGame['screenshots']) && is_array($apiGame['screenshots'])) {
                $screenshotData = $this->igdbClient->getScreenshots($apiGame['screenshots']);
                foreach ($screenshotData as $data) {
                    $screenshot = new Screenshot();
                    $imageUrl = $data['url'];
                    if (strpos($imageUrl, '//') === 0) {
                        $imageUrl = 'https:' . $imageUrl;
                    }
                    $screenshot->setImage($imageUrl);
                    $screenshot->setGame($game);
                    $game->addScreenshot($screenshot);
                }
            }

            $game->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($game);
        }

        $this->entityManager->flush();
        return $count;
    }
}
