<?php

namespace App\Command;

use App\Repository\GameRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * 🧪 COMMANDE DE TEST - API HERO BANNER
 * 
 * Cette commande teste l'API du HeroBanner pour vérifier
 * que les données sont bien retournées avec les coverUrl.
 * 
 * 🎯 OBJECTIF :
 * - Vérifier que l'API retourne les coverUrl
 * - Tester la sérialisation des données
 * - Déboguer les problèmes d'affichage d'images
 * 
 * ⚡ UTILISATION :
 * php bin/console app:test-api
 */

#[AsCommand(
    name: 'app:test-api',
    description: 'Test de l\'API HeroBanner pour vérifier les coverUrl'
)]
class TestApiCommand extends Command
{
    public function __construct(
        private GameRepository $gameRepository,
        private SerializerInterface $serializer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🧪 Test de l\'API HeroBanner');
        $io->text('🎯 Vérification des coverUrl dans la réponse JSON');

        // Récupère les jeux du HeroBanner
        $games = $this->gameRepository->findTopYearGames(3);
        
        $io->section('📊 Jeux récupérés :');
        foreach ($games as $game) {
            $io->text("• {$game->getTitle()} - coverUrl: " . ($game->getCoverUrl() ?: 'NULL'));
        }

        // Test de sérialisation
        $io->section('🔍 Test de sérialisation :');
        try {
            $json = $this->serializer->serialize($games, 'json', ['groups' => 'game:read']);
            $io->text('✅ Sérialisation réussie');
            
            // Vérifie si coverUrl est dans le JSON
            if (strpos($json, 'coverUrl') !== false) {
                $io->success('✅ coverUrl trouvé dans le JSON !');
            } else {
                $io->error('❌ coverUrl manquant dans le JSON !');
            }
            
            // Affiche un extrait du JSON
            $io->text('📄 Extrait du JSON :');
            $io->text(substr($json, 0, 500) . '...');
            
        } catch (\Exception $e) {
            $io->error('❌ Erreur de sérialisation : ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
} 