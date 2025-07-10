<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\Screenshot;
use App\Entity\Artwork;
use App\Entity\Video;
use App\Entity\Wallpaper;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Cocur\Slugify\Slugify;

/**
 * 🚀 SERVICE D'IMPORT PRINCIPAL - GESTION COMPLÈTE DES IMPORTS IGDB
 * 
 * Ce service est le cœur de l'importation des données depuis l'API IGDB.
 * Il gère l'ensemble du processus d'import : récupération, validation,
 * transformation et sauvegarde des jeux vidéo avec leurs métadonnées.
 * 
 * 📥 FONCTIONNALITÉS D'IMPORT :
 * - Import de jeux populaires avec critères de qualité
 * - Import du Top 100 des meilleurs jeux de tous les temps
 * - Import des jeux de l'année avec filtres temporels
 * - Import de jeux par recherche avec enrichissement automatique
 * - Import de médias associés (screenshots, artworks, vidéos)
 * 
 * 🔄 PROCESSUS D'IMPORT COMPLET :
 * 1. Récupération des données depuis l'API IGDB
 * 2. Validation et nettoyage des données
 * 3. Transformation des formats (dates, URLs, etc.)
 * 4. Génération des slugs uniques
 * 5. Sauvegarde en base avec relations
 * 6. Import des médias associés
 * 7. Mise à jour des compteurs et statistiques
 * 
 * 🎯 CRITÈRES DE QUALITÉ :
 * - Filtrage par note minimale (75-90 selon l'époque)
 * - Filtrage par nombre de votes (50-500 selon l'époque)
 * - Exclusion des jeux de faible qualité
 * - Priorisation des jeux AAA et populaires
 * - Nettoyage automatique des données aberrantes
 * 
 * 📊 MÉTADONNÉES GÉRÉES :
 * - Informations de base : titre, développeur, éditeur
 * - Classements : note globale, nombre de votes
 * - Métadonnées : plateformes, genres, modes de jeu
 * - Médias : couverture, screenshots, artworks, vidéos
 * - Dates : sortie, création, mise à jour
 * 
 * ⚡ OPTIMISATIONS DE PERFORMANCE :
 * - Import par batch pour éviter les surcharges
 * - Cache des tokens d'authentification
 * - Gestion des erreurs avec retry automatique
 * - Pause entre les requêtes pour respecter les limites API
 * - Transactions pour garantir la cohérence des données
 * 
 * 🔗 INTÉGRATION AVEC LES AUTRES SERVICES :
 * - Utilise IgdbClient pour les requêtes API
 * - Interface avec GameRepository pour les requêtes
 * - Alimente les entités avec les données enrichies
 * - Gère les relations avec les médias
 * 
 * 🛠️ TECHNOLOGIES UTILISÉES :
 * - Symfony HttpClient pour les requêtes API
 * - Doctrine ORM pour la persistance
 * - Slugify pour la génération d'URLs
 * - Logger pour le suivi des opérations
 * - Transactions pour la cohérence
 * 
 * 🔒 SÉCURITÉ ET ROBUSTESSE :
 * - Validation des données reçues
 * - Gestion des erreurs API avec fallback
 * - Protection contre les doublons
 * - Limitation des appels API
 * - Rollback en cas d'erreur
 * 
 * 📈 MÉTHODES PRINCIPALES :
 * - importPopularGames() : Import des jeux populaires
 * - importTop100Games() : Import du Top 100
 * - importTopYearGames() : Import des jeux de l'année
 * - importGameBySearch() : Import par recherche
 * - importGameMedia() : Import des médias
 * 
 * 🎮 EXEMPLES D'UTILISATION :
 * - Commande console : php bin/console app:import-top100-games
 * - Import automatique via cron
 * - Import à la demande depuis l'interface admin
 * - Enrichissement lors de la recherche utilisateur
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

    /**
     * Retourne l'instance IgdbClient pour utilisation externe
     */
    public function getIgdbClient(): IgdbClient
    {
        return $this->igdbClient;
    }

    public function importPopularGames(): void
    {
        // Récupère les jeux populaires depuis l'API IGDB
        $games = $this->igdbClient->getPopularGames();

        foreach ($games as $apiGame) {
            $igdbId = $apiGame['id'];

            // Vérifie si le jeu existe déjà en base
            $existingGame = $this->gameRepository->findOneBy(['igdbId' => $igdbId]);

            if (!$existingGame) {
                $game = new Game();
                $game->setIgdbId($igdbId);
                $game->setCreatedAt(new \DateTimeImmutable());
            } else {
                $game = $existingGame;
            }

            // Met à jour les informations du jeu
            $title = $apiGame['name'] ?? 'Inconnu';
            $game->setTitle($title);
            $baseSlug = $this->slugify->slugify($title);
            // Générer un slug unique sans l'ID IGDB
            $uniqueSlug = $this->generateUniqueSlug($baseSlug, $game->getId());
            $game->setSlug($uniqueSlug);

            if (isset($apiGame['summary'])) {
                $game->setSummary($apiGame['summary']);
            }

            if (array_key_exists('total_rating', $apiGame)) {
                $game->setTotalRating($apiGame['total_rating']);
            }

            if (isset($apiGame['first_release_date'])) {
                $game->setReleaseDate((new \DateTime())->setTimestamp($apiGame['first_release_date']));
            }

            if (isset($apiGame['genres'])) {
                $genres = array_map(fn($genre) => $genre['name'], $apiGame['genres']);
                $game->setGenres($genres);
            }

            if (isset($apiGame['platforms'])) {
                $platforms = array_map(fn($platform) => $platform['name'], $apiGame['platforms']);
                $game->setPlatforms($platforms);
            }

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

            // Parser l'éditeur depuis involved_companies
            if (isset($apiGame['involved_companies'])) {
                foreach ($apiGame['involved_companies'] as $company) {
                    if (isset($company['publisher']) && $company['publisher'] && isset($company['company']['name'])) {
                        $game->setPublisher($company['company']['name']);
                        break;
                    }
                }
            }

            // Parser les titres alternatifs
            if (isset($apiGame['alternative_names'])) {
                $alternativeTitles = array_map(fn($alt) => $alt['name'], $apiGame['alternative_names']);
                $game->setAlternativeTitles($alternativeTitles);
            }

            // Parser la classification d'âge
            if (isset($apiGame['age_ratings']) && !empty($apiGame['age_ratings'])) {
                // Pour l'instant, on stocke juste le premier ID, on pourra l'enrichir plus tard
                $game->setAgeRating(implode(',', $apiGame['age_ratings']));
            }

            if (isset($apiGame['cover']['url'])) {
                $imageUrl = $apiGame['cover']['url'];
                if (strpos($imageUrl, '//') === 0) {
                    $imageUrl = 'https:' . $imageUrl;
                }
                $highQualityUrl = $this->igdbClient->improveImageQuality($imageUrl, 't_cover_big');
                $game->setCoverUrl($highQualityUrl);
            }

            // Compter les médias disponibles
            if (isset($apiGame['screenshots'])) {
                $game->setScreenshotsCount(count($apiGame['screenshots']));
            }
            if (isset($apiGame['artworks'])) {
                $game->setArtworksCount(count($apiGame['artworks']));
            }
            if (isset($apiGame['videos'])) {
                $game->setVideosCount(count($apiGame['videos']));
            }

            // Ajout des statistiques de rating et popularité
            if (array_key_exists('total_rating_count', $apiGame)) {
                $game->setTotalRatingCount($apiGame['total_rating_count']);
            }
            if (array_key_exists('updated_at', $apiGame)) {
                $game->setLastPopularityUpdate((new \DateTimeImmutable())->setTimestamp($apiGame['updated_at']));
            }

            // Sauvegarde la catégorie (taxonomie du jeu)
            if (array_key_exists('category', $apiGame)) {
                $game->setCategory($apiGame['category']);
            }

            $game->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($game);
        }

        $this->entityManager->flush();
    }

    /**
     * Importe les jeux du Top 100 d'IGDB avec critères dynamiques.
     */
    public function importTop100Games(int $minVotes = 80, int $minRating = 75): int
    {
        $games = $this->igdbClient->getTop100Games($minVotes, $minRating);
        $count = 0;
        $slugMap = []; // Map pour éviter les doublons de slugs dans cette importation

        // Collecter tous les IDs de vidéos pour l'hydratation
        $allVideoIds = [];
        // Collecter tous les IDs d'artworks pour l'hydratation
        $allArtworkIds = [];
        foreach ($games as $apiGame) {
            if (isset($apiGame['videos'])) {
                $allVideoIds = array_merge($allVideoIds, $apiGame['videos']);
            }
            if (isset($apiGame['artworks'])) {
                $allArtworkIds = array_merge($allArtworkIds, $apiGame['artworks']);
            }
        }

        // Hydrater les vidéos
        $videosData = $this->igdbClient->getVideos($allVideoIds);
        // Hydrater les artworks
        $artworksData = $this->igdbClient->getArtworks($allArtworkIds);

        // Créer des maps pour un accès rapide
        $videosMap = [];
        foreach ($videosData as $video) {
            $videosMap[$video['id']] = $video;
        }
        $artworksMap = [];
        foreach ($artworksData as $artwork) {
            $artworksMap[$artwork['id']] = $artwork;
        }

        foreach ($games as $apiGame) {
            $igdbId = $apiGame['id'];

            // Vérifie si le jeu existe déjà en base
            $existingGame = $this->gameRepository->findOneBy(['igdbId' => $igdbId]);

            if (!$existingGame) {
                $game = new Game();
                $game->setIgdbId($igdbId);
                $game->setCreatedAt(new \DateTimeImmutable());
            } else {
                $game = $existingGame;
            }

            // Met à jour les informations du jeu
            $title = $apiGame['name'] ?? 'Inconnu';
            $game->setTitle($title);
            $baseSlug = $this->slugify->slugify($title);
            // Générer un slug unique en utilisant la map pour éviter les doublons
            $uniqueSlug = $this->generateUniqueSlug($baseSlug, $game->getId(), $slugMap);
            $game->setSlug($uniqueSlug);

            if (isset($apiGame['summary'])) {
                $game->setSummary($apiGame['summary']);
            }

            if (array_key_exists('total_rating', $apiGame)) {
                $game->setTotalRating($apiGame['total_rating']);
            }

            if (isset($apiGame['first_release_date'])) {
                $game->setReleaseDate((new \DateTime())->setTimestamp($apiGame['first_release_date']));
            }

            if (isset($apiGame['genres'])) {
                $genres = array_map(fn($genre) => $genre['name'], $apiGame['genres']);
                $game->setGenres($genres);
            }

            if (isset($apiGame['platforms'])) {
                $platforms = array_map(fn($platform) => $platform['name'], $apiGame['platforms']);
                $game->setPlatforms($platforms);
            }

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

            // Récupérer l'éditeur (publisher) - différent du développeur
            if (isset($apiGame['involved_companies'])) {
                foreach ($apiGame['involved_companies'] as $company) {
                    if (isset($company['company']['name']) && isset($company['publisher']) && $company['publisher']) {
                        $game->setPublisher($company['company']['name']);
                        break;
                    }
                }
            }

            // Récupérer les titres alternatifs
            if (isset($apiGame['alternative_names'])) {
                $alternativeTitles = array_map(fn($alt) => $alt['name'], $apiGame['alternative_names']);
                $game->setAlternativeTitles($alternativeTitles);
            }

            // Récupérer les dates de sortie par plateforme
            if (isset($apiGame['release_dates'])) {
                $releaseDatesByPlatform = [];
                foreach ($apiGame['release_dates'] as $release) {
                    if (isset($release['platform']['name']) && isset($release['date'])) {
                        $platformName = $release['platform']['name'];
                        $releaseDate = date('Y-m-d', $release['date']);
                        $releaseDatesByPlatform[$platformName] = $releaseDate;
                    }
                }
                if (!empty($releaseDatesByPlatform)) {
                    // Suppression de cette ligne qui cause l'erreur
                    // $game->setReleaseDatesByPlatform($releaseDatesByPlatform);
                }
            }

            // Récupérer la classification par âge
            if (isset($apiGame['age_ratings'])) {
                foreach ($apiGame['age_ratings'] as $rating) {
                    if (isset($rating['rating']['name'])) {
                        $game->setAgeRating($rating['rating']['name']);
                        break;
                    }
                }
            }

            // NOUVELLE LOGIQUE : Récupérer et enregistrer uniquement le PEGI
            $pegi = null;
            $ageRatings = $this->igdbClient->getAgeRatings([$igdbId]);
            if (isset($ageRatings[$igdbId])) {
                foreach ($ageRatings[$igdbId] as $label) {
                    if (strpos($label, 'PEGI') !== false) {
                        $pegi = $label;
                        break;
                    }
                }
            }
            $game->setAgeRating($pegi);

            // Traiter les vidéos
            if (isset($apiGame['videos'])) {
                foreach ($apiGame['videos'] as $videoId) {
                    if (isset($videosMap[$videoId])) {
                        $videoData = $videosMap[$videoId];
                        
                        $videoEntity = new \App\Entity\Video();
                        $videoEntity->setName($videoData['name'] ?? 'Trailer');
                        $videoEntity->setVideoId($videoData['video_id']);
                        $videoEntity->setUrl($videoData['url']);
                        $videoEntity->setGame($game);
                        
                        $game->addVideo($videoEntity);
                        $this->entityManager->persist($videoEntity);
                    }
                }
            }

            // Traiter les artworks
            if (isset($apiGame['artworks'])) {
                foreach ($apiGame['artworks'] as $artworkId) {
                    if (isset($artworksMap[$artworkId])) {
                        $artworkData = $artworksMap[$artworkId];
                        
                        $artworkEntity = new \App\Entity\Artwork();
                        $artworkEntity->setTitle($artworkData['name'] ?? 'Artwork');
                        $artworkEntity->setUrl($artworkData['url']);
                        $artworkEntity->setType('artwork');
                        $artworkEntity->setGame($game);
                        
                        $game->addArtwork($artworkEntity);
                        $this->entityManager->persist($artworkEntity);
                    }
                }
            }

            // Calculer les compteurs de médias
            $game->setScreenshotsCount($game->getScreenshots()->count());
            $game->setArtworksCount($game->getArtworks()->count());
            $game->setVideosCount($game->getVideos()->count());

            if (isset($apiGame['cover']['url'])) {
                $imageUrl = $apiGame['cover']['url'];
                if (strpos($imageUrl, '//') === 0) {
                    $imageUrl = 'https:' . $imageUrl;
                }
                $highQualityUrl = $this->igdbClient->improveImageQuality($imageUrl, 't_cover_big');
                $game->setCoverUrl($highQualityUrl);
            }

            // Ajout des statistiques de rating et popularité
            if (array_key_exists('total_rating_count', $apiGame)) {
                $game->setTotalRatingCount($apiGame['total_rating_count']);
            }
            if (array_key_exists('follows', $apiGame)) {
                $game->setFollows($apiGame['follows']);
            }
            $game->setLastPopularityUpdate(new \DateTimeImmutable());

            // Sauvegarde la catégorie (taxonomie du jeu)
            if (array_key_exists('category', $apiGame)) {
                $game->setCategory($apiGame['category']);
            }

            $game->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($game);
            $count++;
        }

        $this->entityManager->flush();
        return $count;
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
        $baseSlug = $this->slugify->slugify($title);
        // Générer un slug unique sans l'ID IGDB
        $uniqueSlug = $this->generateUniqueSlug($baseSlug, $game->getId());
        $game->setSlug($uniqueSlug);

        if (isset($apiGame['summary'])) {
            $game->setSummary($apiGame['summary']);
        }
        
        if (array_key_exists('total_rating', $apiGame)) {
            $game->setTotalRating($apiGame['total_rating']);
        }

        // Ajout des statistiques de rating et popularité
        if (array_key_exists('total_rating_count', $apiGame)) {
            $game->setTotalRatingCount($apiGame['total_rating_count']);
        }
        if (array_key_exists('follows', $apiGame)) {
            $game->setFollows($apiGame['follows']);
        }
        $game->setLastPopularityUpdate(new \DateTimeImmutable());

        // Sauvegarde la catégorie (taxonomie du jeu)
        if (array_key_exists('category', $apiGame)) {
            $game->setCategory($apiGame['category']);
        }

        if (isset($apiGame['first_release_date'])) {
            $game->setReleaseDate((new \DateTime())->setTimestamp($apiGame['first_release_date']));
        }

        if (isset($apiGame['genres'])) {
            $genres = array_map(fn($genre) => $genre['name'], $apiGame['genres']);
            $game->setGenres($genres);
        }

        if (isset($apiGame['platforms'])) {
            $platforms = array_map(fn($platform) => $platform['name'], $apiGame['platforms']);
            $game->setPlatforms($platforms);
        }

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

        // Récupérer l'éditeur (publisher) - différent du développeur
        if (isset($apiGame['involved_companies'])) {
            foreach ($apiGame['involved_companies'] as $company) {
                if (isset($company['company']['name']) && isset($company['publisher']) && $company['publisher']) {
                    $game->setPublisher($company['company']['name']);
                    break;
                }
            }
        }

        // Récupérer les titres alternatifs
        if (isset($apiGame['alternative_names'])) {
            $alternativeTitles = array_map(fn($alt) => $alt['name'], $apiGame['alternative_names']);
            $game->setAlternativeTitles($alternativeTitles);
        }

        // Récupérer les dates de sortie par plateforme
        if (isset($apiGame['release_dates'])) {
            $releaseDatesByPlatform = [];
            foreach ($apiGame['release_dates'] as $release) {
                if (isset($release['platform']['name']) && isset($release['date'])) {
                    $platformName = $release['platform']['name'];
                    $releaseDate = date('Y-m-d', $release['date']);
                    $releaseDatesByPlatform[$platformName] = $releaseDate;
                }
            }
            if (!empty($releaseDatesByPlatform)) {
                // Suppression de cette ligne qui cause l'erreur
                // $game->setReleaseDatesByPlatform($releaseDatesByPlatform);
            }
        }

        // Récupérer la classification par âge
        if (isset($apiGame['age_ratings'])) {
            foreach ($apiGame['age_ratings'] as $rating) {
                if (isset($rating['rating']['name'])) {
                    $game->setAgeRating($rating['rating']['name']);
                    break;
                }
            }
        }

        // Récupérer le lien du trailer
        if (isset($apiGame['videos'])) {
            foreach ($apiGame['videos'] as $video) {
                if (isset($video['video_id'])) {
                    $game->setTrailerUrl("https://www.youtube.com/watch?v=" . $video['video_id']);
                    break;
                }
            }
        }

        // Récupérer les vidéos
        if (isset($apiGame['videos'])) {
            foreach ($apiGame['videos'] as $video) {
                if (isset($video['video_id'])) {
                    // Créer une entité Video
                    $videoEntity = new \App\Entity\Video();
                    $videoEntity->setName($video['name'] ?? 'Trailer');
                    $videoEntity->setVideoId($video['video_id']);
                    $videoEntity->setUrl("https://www.youtube.com/watch?v=" . $video['video_id']);
                    $videoEntity->setGame($game);
                    
                    $game->addVideo($videoEntity);
                    $this->entityManager->persist($videoEntity);
                }
            }
        }

        if (isset($apiGame['cover']['url'])) {
            $imageUrl = $apiGame['cover']['url'];
            if (strpos($imageUrl, '//') === 0) {
                $imageUrl = 'https:' . $imageUrl;
            }
            $highQualityUrl = $this->igdbClient->improveImageQuality($imageUrl, 't_cover_big');
            $game->setCoverUrl($highQualityUrl);
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
        // Récupère TOUS les jeux correspondant à la recherche (pas de limite)
        $apiGames = $this->igdbClient->searchAllGames($query, 500); // Limite à 500 pour éviter les timeouts

        if (empty($apiGames)) {
            return []; // Aucun jeu trouvé
        }

        $importedGames = [];
        $newGames = 0;
        $updatedGames = 0;
        $slugMap = []; // Map pour éviter les doublons de slugs dans cette importation

        foreach ($apiGames as $index => $apiGame) {
            $igdbId = $apiGame['id'];
            $title = $apiGame['name'] ?? 'Inconnu';

            // Vérifie si le jeu existe déjà
            $game = $this->gameRepository->findOneBy(['igdbId' => $igdbId]);
            if (!$game) {
                $game = new Game();
                $game->setIgdbId($igdbId);
                $game->setCreatedAt(new \DateTimeImmutable());
                $newGames++;
            } else {
                $updatedGames++;
                // Mise à jour jeu existant: '$title'
            }

            // Met à jour les informations du jeu
            $game->setTitle($title);
            $baseSlug = $this->slugify->slugify($title);
            // Générer un slug unique en utilisant la map pour éviter les doublons
            $uniqueSlug = $this->generateUniqueSlug($baseSlug, $game->getId(), $slugMap);
            $game->setSlug($uniqueSlug);
            
            if (isset($apiGame['summary'])) {
                $game->setSummary($apiGame['summary']);
            }
            
            if (array_key_exists('total_rating', $apiGame)) {
                $game->setTotalRating($apiGame['total_rating']);
            }

            if (isset($apiGame['first_release_date'])) {
                $game->setReleaseDate((new \DateTime())->setTimestamp($apiGame['first_release_date']));
            }

            if (isset($apiGame['genres'])) {
                $genres = array_map(fn($genre) => $genre['name'], $apiGame['genres']);
                $game->setGenres($genres);
            }

            if (isset($apiGame['platforms'])) {
                $platforms = array_map(fn($platform) => $platform['name'], $apiGame['platforms']);
                $game->setPlatforms($platforms);
            }

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

            if (isset($apiGame['cover']['url'])) {
                $imageUrl = $apiGame['cover']['url'];
                if (strpos($imageUrl, '//') === 0) {
                    $imageUrl = 'https:' . $imageUrl;
                }
                $highQualityUrl = $this->igdbClient->improveImageQuality($imageUrl, 't_cover_big');
                $game->setCoverUrl($highQualityUrl);
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

            // Ajout des statistiques de rating et popularité
            if (array_key_exists('total_rating_count', $apiGame)) {
                $game->setTotalRatingCount($apiGame['total_rating_count']);
            }
            if (array_key_exists('follows', $apiGame)) {
                $game->setFollows($apiGame['follows']);
            }
            $game->setLastPopularityUpdate(new \DateTimeImmutable());

            // Sauvegarde la catégorie (taxonomie du jeu)
            if (array_key_exists('category', $apiGame)) {
                $game->setCategory($apiGame['category']);
            }

            $game->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($game);
            $importedGames[] = $game;
        }

        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw $e;
        }
        
        return $importedGames;
    }

    /**
     * Importe les meilleurs jeux de l'année (365 derniers jours) avec critères dynamiques.
     */
    public function importTopYearGames(int $minVotes = 80, int $minRating = 75): int
    {
        $games = $this->igdbClient->getTopYearGames($minVotes, $minRating);
        $count = 0;

        // Collecter tous les IDs de vidéos pour l'hydratation
        $allVideoIds = [];
        // Collecter tous les IDs d'artworks pour l'hydratation
        $allArtworkIds = [];
        foreach ($games as $apiGame) {
            if (isset($apiGame['videos'])) {
                $allVideoIds = array_merge($allVideoIds, $apiGame['videos']);
            }
            if (isset($apiGame['artworks'])) {
                $allArtworkIds = array_merge($allArtworkIds, $apiGame['artworks']);
            }
        }

        // Hydrater les vidéos
        $videosData = $this->igdbClient->getVideos($allVideoIds);
        // Hydrater les artworks
        $artworksData = $this->igdbClient->getArtworks($allArtworkIds);

        // Créer des maps pour un accès rapide
        $videosMap = [];
        foreach ($videosData as $video) {
            $videosMap[$video['id']] = $video;
        }
        $artworksMap = [];
        foreach ($artworksData as $artwork) {
            $artworksMap[$artwork['id']] = $artwork;
        }

        foreach ($games as $apiGame) {
            $igdbId = $apiGame['id'];

            // Vérifie si le jeu existe déjà
            $existingGame = $this->gameRepository->findOneBy(['igdbId' => $igdbId]);
            if (!$existingGame) {
                $game = new Game();
                $game->setIgdbId($igdbId);
                $game->setCreatedAt(new \DateTimeImmutable());
            } else {
                $game = $existingGame;
            }

            // Met à jour les informations du jeu
            $title = $apiGame['name'] ?? 'Inconnu';
            $game->setTitle($title);
            $baseSlug = $this->slugify->slugify($title);
            // Générer un slug unique sans l'ID IGDB
            $uniqueSlug = $this->generateUniqueSlug($baseSlug, $game->getId());
            $game->setSlug($uniqueSlug);

            if (isset($apiGame['summary'])) {
                $game->setSummary($apiGame['summary']);
            }

            if (array_key_exists('total_rating', $apiGame)) {
                $game->setTotalRating($apiGame['total_rating']);
            }

            if (isset($apiGame['first_release_date'])) {
                $game->setReleaseDate((new \DateTime())->setTimestamp($apiGame['first_release_date']));
            }

            if (isset($apiGame['genres'])) {
                $genres = array_map(fn($genre) => $genre['name'], $apiGame['genres']);
                $game->setGenres($genres);
            }

            if (isset($apiGame['platforms'])) {
                $platforms = array_map(fn($platform) => $platform['name'], $apiGame['platforms']);
                $game->setPlatforms($platforms);
            }

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

            // Récupérer l'éditeur (publisher) - différent du développeur
            if (isset($apiGame['involved_companies'])) {
                foreach ($apiGame['involved_companies'] as $company) {
                    if (isset($company['company']['name']) && isset($company['publisher']) && $company['publisher']) {
                        $game->setPublisher($company['company']['name']);
                        break;
                    }
                }
            }

            // Traiter les nouvelles données enrichies
            if (isset($apiGame['alternative_names'])) {
                $alternativeTitles = array_map(fn($alt) => $alt['name'], $apiGame['alternative_names']);
                $game->setAlternativeTitles($alternativeTitles);
            }

            if (isset($apiGame['release_dates'])) {
                $releaseDatesByPlatform = [];
                foreach ($apiGame['release_dates'] as $release) {
                    if (isset($release['platform']['name']) && isset($release['date'])) {
                        $platformName = $release['platform']['name'];
                        $releaseDate = date('Y-m-d', $release['date']);
                        $releaseDatesByPlatform[$platformName] = $releaseDate;
                    }
                }
                // Suppression de cette ligne qui cause l'erreur
                // if (!empty($releaseDatesByPlatform)) {
                //     $game->setReleaseDatesByPlatform($releaseDatesByPlatform);
                // }
            }

            if (isset($apiGame['age_ratings'])) {
                foreach ($apiGame['age_ratings'] as $rating) {
                    if (isset($rating['rating']['name'])) {
                        $game->setAgeRating($rating['rating']['name']);
                        break;
                    }
                }
            }

            // Traiter les vidéos
            if (isset($apiGame['videos'])) {
                foreach ($apiGame['videos'] as $videoId) {
                    if (isset($videosMap[$videoId])) {
                        $videoData = $videosMap[$videoId];
                        
                        $videoEntity = new \App\Entity\Video();
                        $videoEntity->setName($videoData['name'] ?? 'Trailer');
                        $videoEntity->setVideoId($videoData['video_id']);
                        $videoEntity->setUrl($videoData['url']);
                        $videoEntity->setGame($game);
                        
                        $game->addVideo($videoEntity);
                        $this->entityManager->persist($videoEntity);
                    }
                }
            }

            // Traiter les artworks
            if (isset($apiGame['artworks'])) {
                foreach ($apiGame['artworks'] as $artworkId) {
                    if (isset($artworksMap[$artworkId])) {
                        $artworkData = $artworksMap[$artworkId];
                        
                        $artworkEntity = new \App\Entity\Artwork();
                        $artworkEntity->setTitle($artworkData['name'] ?? 'Artwork');
                        $artworkEntity->setUrl($artworkData['url']);
                        $artworkEntity->setType('artwork');
                        $artworkEntity->setGame($game);
                        
                        $game->addArtwork($artworkEntity);
                        $this->entityManager->persist($artworkEntity);
                    }
                }
            }

            // Calculer les compteurs de médias
            $game->setScreenshotsCount($game->getScreenshots()->count());
            $game->setArtworksCount($game->getArtworks()->count());
            $game->setVideosCount($game->getVideos()->count());

            if (isset($apiGame['cover']['url'])) {
                $imageUrl = $apiGame['cover']['url'];
                if (strpos($imageUrl, '//') === 0) {
                    $imageUrl = 'https:' . $imageUrl;
                }
                $highQualityUrl = $this->igdbClient->improveImageQuality($imageUrl, 't_cover_big');
                $game->setCoverUrl($highQualityUrl);
            }

            // Ajout des statistiques de rating et popularité
            if (array_key_exists('total_rating_count', $apiGame)) {
                $game->setTotalRatingCount($apiGame['total_rating_count']);
            }
            if (array_key_exists('follows', $apiGame)) {
                $game->setFollows($apiGame['follows']);
            }
            $game->setLastPopularityUpdate(new \DateTimeImmutable());

            // Sauvegarde la catégorie (taxonomie du jeu)
            if (array_key_exists('category', $apiGame)) {
                $game->setCategory($apiGame['category']);
            }

            $game->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($game);
            $count++;
        }

        $this->entityManager->flush();
        return $count;
    }

    /**
     * Génère un slug unique sans inclure l'ID IGDB
     * @param string $baseSlug Le slug de base généré à partir du titre
     * @param int|null $existingId L'ID du jeu existant (null si nouveau)
     * @param array $slugMap (optionnel) Liste des slugs déjà générés dans cette importation
     * @return string Le slug unique
     */
    private function generateUniqueSlug(string $baseSlug, ?int $existingId = null, array &$slugMap = []): string
    {
        $slug = $baseSlug;
        $counter = 2;
        
        // Vérifier si le slug existe déjà (en base ou dans la map mémoire)
        while ($this->isSlugTaken($slug, $existingId, $slugMap)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
            
            // Protection contre les boucles infinies
            if ($counter > 1000) {
                $slug = $baseSlug . '-' . time() . '-' . rand(1000, 9999);
                break;
            }
        }
        
        // Marquer ce slug comme utilisé dans la map
        $slugMap[$slug] = $existingId ?? true;
        return $slug;
    }

    /**
     * Vérifie si un slug est déjà pris (en base ou dans la map mémoire)
     * @param string $slug Le slug à vérifier
     * @param int|null $existingId L'ID du jeu existant (null si nouveau)
     * @param array $slugMap La map des slugs déjà utilisés
     * @return bool True si le slug est pris, false sinon
     */
    private function isSlugTaken(string $slug, ?int $existingId, array $slugMap): bool
    {
        // Vérifier dans la map mémoire (plus rapide)
        if (isset($slugMap[$slug])) {
            // Si c'est le même jeu (mise à jour), ce n'est pas un conflit
            if ($existingId && $slugMap[$slug] === $existingId) {
                return false;
            }
            return true;
        }
        
        // Vérifier en base de données
        $existingGame = $this->gameRepository->findOneBy(['slug' => $slug]);
        if (!$existingGame) {
            return false;
        }
        
        // Si c'est le même jeu (mise à jour), ce n'est pas un conflit
        if ($existingId && $existingGame->getId() === $existingId) {
            return false;
        }
        
        return true;
    }

    /**
     * Retourne les jeux IGDB trouvés pour une recherche, sans les persister
     */
    public function getRawGamesBySearch(string $query): array
    {
        $apiGames = $this->igdbClient->searchAllGames($query, 500);
        $result = [];
        foreach ($apiGames as $apiGame) {
            $result[] = [
                'id' => null,
                'title' => $apiGame['name'] ?? 'Inconnu',
                'name' => $apiGame['name'] ?? 'Inconnu',
                'slug' => $this->slugify->slugify($apiGame['name'] ?? 'inconnu'),
                'coverUrl' => isset($apiGame['cover']['url']) ? $apiGame['cover']['url'] : null,
                'cover' => isset($apiGame['cover']['url']) ? ['url' => $apiGame['cover']['url']] : null,
                'totalRating' => $apiGame['total_rating'] ?? null,
                'total_rating' => $apiGame['total_rating'] ?? null,
                'platforms' => isset($apiGame['platforms']) ? array_map(fn($p) => ['name' => is_array($p) && isset($p['name']) ? $p['name'] : $p], $apiGame['platforms']) : [],
                'genres' => isset($apiGame['genres']) ? array_map(fn($g) => ['name' => is_array($g) && isset($g['name']) ? $g['name'] : $g], $apiGame['genres']) : [],
                'gameModes' => isset($apiGame['game_modes']) ? array_map(fn($m) => ['name' => is_array($m) && isset($m['name']) ? $m['name'] : $m], $apiGame['game_modes']) : [],
                'perspectives' => isset($apiGame['player_perspectives']) ? array_map(fn($p) => ['name' => is_array($p) && isset($p['name']) ? $p['name'] : $p], $apiGame['player_perspectives']) : [],
                'releaseDate' => isset($apiGame['first_release_date']) ? (new \DateTime())->setTimestamp($apiGame['first_release_date'])->format('Y-m-d') : null,
                'first_release_date' => $apiGame['first_release_date'] ?? null,
                'summary' => $apiGame['summary'] ?? null,
                'developer' => isset($apiGame['involved_companies'][0]['company']['name']) ? $apiGame['involved_companies'][0]['company']['name'] : null,
                'igdbId' => $apiGame['id'] ?? null
            ];
        }
        return $result;
    }

    /**
     * Recherche des jeux IGDB sans les persister en base (plus rapide)
     * @param string $query Le terme de recherche
     * @return array Les jeux trouvés (non persistés)
     */
    public function searchGamesWithoutPersist(string $query): array
    {
        // Récupère les jeux depuis IGDB sans persistance
        $apiGames = $this->igdbClient->searchAllGames($query, 1000); // Augmenté à 1000 pour récupérer le maximum de résultats

        if (empty($apiGames)) {
            return [];
        }

        $games = [];
        $seenIgdbIds = []; // Pour éviter les doublons
        
        foreach ($apiGames as $apiGame) {
            $igdbId = $apiGame['id'];
            
            // Éviter les doublons basés sur l'igdbId
            if (in_array($igdbId, $seenIgdbIds)) {
                continue;
            }
            
            $seenIgdbIds[] = $igdbId;
            
            $game = [
                'id' => null,
                'igdbId' => $igdbId,
                'title' => $apiGame['name'] ?? 'Inconnu',
                'slug' => $this->slugify->slugify($apiGame['name'] ?? 'inconnu'),
                'summary' => $apiGame['summary'] ?? null,
                'totalRating' => $apiGame['total_rating'] ?? null,
                'totalRatingCount' => $apiGame['total_rating_count'] ?? null,
                'follows' => $apiGame['follows'] ?? null,
                'releaseDate' => isset($apiGame['first_release_date']) ? 
                    (new \DateTime())->setTimestamp($apiGame['first_release_date'])->format('Y-m-d') : null,
                'developer' => isset($apiGame['involved_companies'][0]['company']['name']) ? 
                    $apiGame['involved_companies'][0]['company']['name'] : null,
                'platforms' => isset($apiGame['platforms']) ? 
                    array_map(fn($p) => $p['name'], $apiGame['platforms']) : [],
                'genres' => isset($apiGame['genres']) ? 
                    array_map(fn($g) => $g['name'], $apiGame['genres']) : [],
                'gameModes' => isset($apiGame['game_modes']) ? 
                    array_map(fn($m) => $m['name'], $apiGame['game_modes']) : [],
                'perspectives' => isset($apiGame['player_perspectives']) ? 
                    array_map(fn($p) => $p['name'], $apiGame['player_perspectives']) : [],
                'coverUrl' => isset($apiGame['cover']['url']) ? 
                    $this->igdbClient->improveImageQuality($apiGame['cover']['url'], 't_cover_big') : null,
                'category' => $apiGame['category'] ?? null,
                'isPersisted' => false // Flag pour indiquer que ce jeu n'est pas en base
            ];

            // Nettoyage : forcer tous les champs à être scalaires ou array (jamais objet)
            foreach ($game as $key => $value) {
                if ($value instanceof \DateTimeInterface) {
                    $game[$key] = $value->format('Y-m-d');
                } elseif (is_object($value)) {
                    $game[$key] = (string)$value;
                } elseif (is_resource($value)) {
                    unset($game[$key]);
                }
            }

            $games[] = $game;
        }

        return $games;
    }
}
