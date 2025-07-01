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

        foreach ($apiGames as $index => $apiGame) {
            $igdbId = $apiGame['id'];
            $title = $apiGame['name'] ?? 'Inconnu';

            // Vérifie si le jeu existe déjà
            $game = $this->gameRepository->findOneBy(['igdbId' => $igdbId]);
            if (!$game) {
                $game = new Game();
                $game->setIgdbId($igdbId);
                $game->setCreatedAt(new \DateTimeImmutable());
            } else {
                // Mise à jour jeu existant: '$title'
            }

            // Met à jour les informations du jeu
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
     * 
     * @param string $baseSlug Le slug de base généré à partir du titre
     * @param int|null $existingId L'ID du jeu existant (null si nouveau)
     * @return string Le slug unique
     */
    public function generateUniqueSlug(string $baseSlug, ?int $existingId = null): string
    {
        $slug = $baseSlug;
        $counter = 1;
        
        // Vérifier si le slug existe déjà (sauf pour le jeu actuel)
        while (true) {
            $existingGame = $this->gameRepository->findOneBy(['slug' => $slug]);
            
            // Si aucun jeu avec ce slug, ou si c'est le même jeu (mise à jour)
            if (!$existingGame || ($existingId && $existingGame->getId() === $existingId)) {
                break;
            }
            
            // Sinon, ajouter un suffixe numérique
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
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
}
