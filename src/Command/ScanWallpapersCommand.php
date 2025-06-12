<?php

namespace App\Command;

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
 * 🔍 COMMANDE DE SCAN - DÉTECTION WALLPAPERS MANQUANTS
 * 
 * Cette commande scanne le dossier Animate pour détecter les wallpapers
 * qui ne sont pas encore en base de données.
 * 
 * 📋 FONCTIONNALITÉS :
 * - Scan du dossier /images/Animate/
 * - Détection des fichiers GIF manquants
 * - Génération de template de configuration
 * - Rapport détaillé des wallpapers manquants
 * 
 * ⚙️ OPTIONS DISPONIBLES :
 * --generate-config : Génère un template JSON
 * --output-file : Chemin du fichier de sortie
 * 
 * 🎯 OBJECTIF :
 * Faciliter l'ajout de nouveaux wallpapers en générant
 * un template de configuration prêt à être complété.
 * 
 * ⚡ UTILISATION :
 * php bin/console app:scan-wallpapers
 * php bin/console app:scan-wallpapers --generate-config
 * 
 * 💡 WORKFLOW RECOMMANDÉ :
 * 1. Scanner les wallpapers manquants
 * 2. Générer le template de configuration
 * 3. Compléter les infos des jeux
 * 4. Importer avec app:import-wallpapers-config
 * 
 * 📈 IMPACT :
 * - Détection automatique des nouveaux wallpapers
 * - Simplification de l'ajout de wallpapers
 * - Maintien de la cohérence base/fichiers
 */

#[AsCommand(
    name: 'app:scan-wallpapers',
    description: 'Scan the Animate folder and show missing wallpapers',
)]
class ScanWallpapersCommand extends Command
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
            ->addOption('generate-config', 'g', InputOption::VALUE_NONE, 'Generate configuration template for missing wallpapers')
            ->addOption('output-file', 'o', InputOption::VALUE_OPTIONAL, 'Output file for generated config', 'config/missing_wallpapers.json')
            ->setHelp('This command scans the /images/Animate/ folder and shows which wallpapers are missing from the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $generateConfig = $input->getOption('generate-config');
        $outputFile = $input->getOption('output-file');

        $io->title('Scan des wallpapers dans le dossier Animate');

        // Chemin vers le dossier Animate
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $animatePath = $projectDir . '/../CheckPoint-Next.JS/public/images/Animate/';

        if (!is_dir($animatePath)) {
            $io->error("Le dossier Animate n'existe pas : $animatePath");
            return Command::FAILURE;
        }

        // Scanner le dossier pour les fichiers GIF
        $files = glob($animatePath . '*.gif');
        $io->note("Fichiers GIF trouvés : " . count($files));

        // Récupérer les wallpapers existants en base
        $existingWallpapers = $this->entityManager->getRepository(Wallpaper::class)->findAll();
        $existingPaths = array_map(fn($w) => $w->getImage(), $existingWallpapers);

        $missingWallpapers = [];
        $existingCount = 0;

        foreach ($files as $file) {
            $filename = basename($file);
            $relativePath = '/images/Animate/' . $filename;
            
            if (in_array($relativePath, $existingPaths)) {
                $io->text("✅ $filename (déjà en base)");
                $existingCount++;
            } else {
                $io->text("❌ $filename (manquant en base)");
                $missingWallpapers[] = [
                    'filename' => $filename,
                    'path' => $relativePath,
                    'fullPath' => $file
                ];
            }
        }

        $io->newLine();
        $io->section('Résumé');
        $io->text([
            "Total fichiers GIF : " . count($files),
            "Wallpapers en base : $existingCount",
            "Wallpapers manquants : " . count($missingWallpapers)
        ]);

        if (empty($missingWallpapers)) {
            $io->success('Tous les wallpapers sont déjà en base de données !');
            return Command::SUCCESS;
        }

        $io->newLine();
        $io->section('Wallpapers manquants');
        foreach ($missingWallpapers as $missing) {
            $io->text("• {$missing['filename']}");
        }

        if ($generateConfig) {
            $this->generateConfigFile($missingWallpapers, $outputFile, $io);
        } else {
            $io->note([
                'Pour générer un fichier de configuration template, utilisez :',
                'php bin/console app:scan-wallpapers --generate-config'
            ]);
        }

        return Command::SUCCESS;
    }

    private function generateConfigFile(array $missingWallpapers, string $outputFile, SymfonyStyle $io): void
    {
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $outputPath = $projectDir . '/' . $outputFile;

        $config = ['wallpapers' => []];

        foreach ($missingWallpapers as $missing) {
            $config['wallpapers'][] = [
                'filename' => $missing['filename'],
                'path' => $missing['path'],
                'game' => [
                    'igdbId' => null,
                    'title' => 'TITRE_DU_JEU_ICI',
                    'releaseDate' => 'YYYY-MM-DD',
                    'developer' => 'DEVELOPPEUR_ICI',
                    'platforms' => ['PLATEFORMES_ICI'],
                    'genres' => ['GENRES_ICI'],
                    'totalRating' => 0.0,
                    'summary' => 'RESUME_DU_JEU_ICI',
                    'coverUrl' => 'URL_JAQUETTE_ICI',
                    'comment' => 'Complétez les informations du jeu pour ' . $missing['filename']
                ]
            ];
        }

        // Créer le dossier si nécessaire
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        file_put_contents($outputPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $io->success([
            "Fichier de configuration généré : $outputPath",
            "Éditez ce fichier pour ajouter les informations des jeux, puis utilisez :",
            "php bin/console app:import-wallpapers-config --config-file=$outputFile"
        ]);
    }
} 