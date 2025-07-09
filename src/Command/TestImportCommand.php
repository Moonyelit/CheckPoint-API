<?php

namespace App\Command;

use App\Service\GameImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-import',
    description: 'Teste l\'import IGDB avec un terme donné',
)]
class TestImportCommand extends Command
{
    public function __construct(
        private GameImporter $gameImporter
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('query', InputArgument::OPTIONAL, 'Terme de recherche IGDB', 'ape');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $query = $input->getArgument('query') ?? 'ape';
        
        $io->title("🧪 Test d'import IGDB pour : '{$query}'");
        
        try {
            $io->text("🔍 Import de jeux depuis IGDB...");
            
            $importedGames = $this->gameImporter->importGamesBySearch($query);
            
            if (empty($importedGames)) {
                $io->warning("❌ Aucun jeu importé depuis IGDB pour '{$query}'");
                return Command::FAILURE;
            }
            
            $io->success(sprintf("✅ Importé %d jeux depuis IGDB", count($importedGames)));
            
            $io->table(
                ['Titre', 'ID IGDB', 'Slug', 'Note'],
                array_map(function($game) {
                    return [
                        $game->getTitle(),
                        $game->getIgdbId(),
                        $game->getSlug(),
                        $game->getTotalRating() ?? 'N/A'
                    ];
                }, array_slice($importedGames, 0, 10))
            );
            
        } catch (\Throwable $e) {
            $io->error("❌ Erreur lors de l'import IGDB : " . $e->getMessage());
            $io->text("Stack trace : " . $e->getTraceAsString());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
} 