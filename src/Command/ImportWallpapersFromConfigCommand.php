<?php

namespace App\Command;

use App\Entity\Game;
use App\Entity\Wallpaper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * 🖼️ COMMANDE D'IMPORT - WALLPAPERS DEPUIS CONFIGURATION
 * 
 * Cette commande importe les jeux et wallpapers depuis un fichier de configuration JSON.
 * Elle permet de gérer facilement l'ajout de nouveaux wallpapers animés.
 * 
 * 📋 FONCTIONNALITÉS :
 * - Import de jeux et wallpapers depuis JSON
 * - Création ou mise à jour des jeux associés
 * - Gestion des wallpapers existants
 * - Mode dry-run pour simulation
 * 
 * ⚙️ OPTIONS DISPONIBLES :
 * --force : Force l'import même si les jeux existent
 * --config-file : Chemin personnalisé du fichier de config
 * --dry-run : Simulation sans modifications
 * 
 * 🎯 OBJECTIF :
 * Faciliter l'ajout de nouveaux wallpapers animés en base
 * tout en maintenant la cohérence avec les jeux associés.
 * 
 * ⚡ UTILISATION :
 * php bin/console app:import-wallpapers-config
 * php bin/console app:import-wallpapers-config --force
 * php bin/console app:import-wallpapers-config --dry-run
 * 
 * 💡 WORKFLOW RECOMMANDÉ :
 * 1. Générer le template avec app:scan-wallpapers
 * 2. Compléter les infos des jeux dans le JSON
 * 3. Importer avec cette commande
 * 
 * 📈 IMPACT :
 * - Ajout de nouveaux wallpapers en base
 * - Mise à jour des jeux associés
 * - Amélioration de la diversité des wallpapers
 */

#[AsCommand(
    name: 'app:import-wallpapers-config',
    description: 'Import games and wallpapers from configuration file',
)]
class ImportWallpapersFromConfigCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $parameterBag
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force import even if games already exist')
            ->addOption('config-file', 'c', InputOption::VALUE_OPTIONAL, 'Custom config file path', 'config/wallpapers.json')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be imported without actually importing')
            ->setHelp('This command imports games and wallpapers from a JSON configuration file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');
        $configFile = $input->getOption('config-file');

        $io->title('Import des jeux et wallpapers depuis le fichier de configuration');

        // Chemin vers le fichier de configuration
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $configPath = $projectDir . '/' . $configFile;

        if (!file_exists($configPath)) {
            $io->error("Le fichier de configuration n'existe pas : $configPath");
            return Command::FAILURE;
        }

        // Lire le fichier de configuration
        $configContent = file_get_contents($configPath);
        $config = json_decode($configContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error('Erreur lors de la lecture du fichier JSON : ' . json_last_error_msg());
            return Command::FAILURE;
        }

        if (!isset($config['wallpapers']) || !is_array($config['wallpapers'])) {
            $io->error('Le fichier de configuration doit contenir un tableau "wallpapers"');
            return Command::FAILURE;
        }

        $importedGames = 0;
        $importedWallpapers = 0;
        $skippedWallpapers = 0;

        if ($dryRun) {
            $io->note('MODE DRY-RUN : Aucune modification ne sera apportée à la base de données');
        }

        foreach ($config['wallpapers'] as $wallpaperConfig) {
            $io->section("Traitement de {$wallpaperConfig['filename']}");

            // Vérifier si le fichier wallpaper existe
            $wallpaperPath = $projectDir . '/../CheckPoint-Next.JS/public' . $wallpaperConfig['path'];
            if (!file_exists($wallpaperPath)) {
                $io->warning("Le fichier wallpaper n'existe pas : {$wallpaperConfig['path']}");
                continue;
            }

            $game = null;

            // Cas 1: Jeu existant par ID
            if (isset($wallpaperConfig['game']['existingGameId'])) {
                $gameId = $wallpaperConfig['game']['existingGameId'];
                $game = $this->entityManager->getRepository(Game::class)->find($gameId);
                
                if (!$game) {
                    $io->warning("Le jeu avec l'ID $gameId n'a pas été trouvé en base");
                    continue;
                }
                
                $io->info("Jeu existant trouvé : {$game->getTitle()} (ID: {$game->getId()})");
            }
            // Cas 2: Nouveau jeu ou jeu existant par IGDB ID
            elseif (isset($wallpaperConfig['game']['igdbId'])) {
                $igdbId = $wallpaperConfig['game']['igdbId'];
                
                // Chercher par IGDB ID
                $existingGame = $this->entityManager->getRepository(Game::class)
                    ->findOneBy(['igdbId' => $igdbId]);

                if ($existingGame && !$force) {
                    $io->warning("Le jeu '{$wallpaperConfig['game']['title']}' existe déjà (IGDB ID: $igdbId, ID: {$existingGame->getId()})");
                    $game = $existingGame;
                } elseif ($existingGame && $force) {
                    $io->note("Mise à jour du jeu '{$wallpaperConfig['game']['title']}'...");
                    $game = $existingGame;
                    $this->updateGameFromConfig($game, $wallpaperConfig['game']);
                    if (!$dryRun) {
                        $this->entityManager->persist($game);
                    }
                } else {
                    $io->note("Création du nouveau jeu '{$wallpaperConfig['game']['title']}'...");
                    if (!$dryRun) {
                        $game = $this->createGameFromConfig($wallpaperConfig['game']);
                        $this->entityManager->persist($game);
                        $this->entityManager->flush(); // Flush pour obtenir l'ID
                        $importedGames++;
                    } else {
                        $io->text("[DRY-RUN] Création du jeu '{$wallpaperConfig['game']['title']}'");
                    }
                }
            } else {
                $io->error("Configuration invalide pour {$wallpaperConfig['filename']} : igdbId ou existingGameId requis");
                continue;
            }

            if (!$game && $dryRun) {
                // En mode dry-run, on simule la création du jeu
                $io->text("[DRY-RUN] Le wallpaper serait associé au jeu '{$wallpaperConfig['game']['title']}'");
                continue;
            }

            if (!$game) {
                continue;
            }

            // Vérifier si le wallpaper existe déjà
            $existingWallpaper = $this->entityManager->getRepository(Wallpaper::class)
                ->findOneBy(['image' => $wallpaperConfig['path'], 'game' => $game]);

            if ($existingWallpaper) {
                $io->info("Wallpaper déjà existant : {$wallpaperConfig['path']}");
                $skippedWallpapers++;
            } else {
                if (!$dryRun) {
                    $wallpaper = new Wallpaper();
                    $wallpaper->setImage($wallpaperConfig['path']);
                    $wallpaper->setGame($game);
                    
                    $this->entityManager->persist($wallpaper);
                    $importedWallpapers++;
                    
                    $io->success("Wallpaper ajouté : {$wallpaperConfig['path']}");
                } else {
                    $io->text("[DRY-RUN] Wallpaper à ajouter : {$wallpaperConfig['path']}");
                    $importedWallpapers++;
                }
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->success([
            $dryRun ? "Simulation terminée !" : "Import terminé !",
            "Jeux " . ($dryRun ? "à importer" : "importés") . ": $importedGames",
            "Wallpapers " . ($dryRun ? "à importer" : "importés") . ": $importedWallpapers",
            "Wallpapers ignorés (déjà existants): $skippedWallpapers"
        ]);

        return Command::SUCCESS;
    }

    private function createGameFromConfig(array $gameConfig): Game
    {
        $game = new Game();
        $this->updateGameFromConfig($game, $gameConfig);
        return $game;
    }

    private function updateGameFromConfig(Game $game, array $gameConfig): void
    {
        $game->setIgdbId($gameConfig['igdbId']);
        $game->setTitle($gameConfig['title']);
        $game->setReleaseDate(new \DateTime($gameConfig['releaseDate']));
        $game->setDeveloper($gameConfig['developer']);
        $game->setPlatforms($gameConfig['platforms']);
        $game->setGenres($gameConfig['genres']);
        $game->setTotalRating($gameConfig['totalRating']);
        $game->setSummary($gameConfig['summary']);
        $game->setCoverUrl($gameConfig['coverUrl']);
    }
} 