<?php

namespace App\Command;

use App\Service\IgdbClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-igdb-search',
    description: 'Teste la recherche IGDB avec un terme donné',
)]
class TestIgdbSearchCommand extends Command
{
    public function __construct(
        private IgdbClient $igdbClient
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
        
        $io->title("🧪 Test de recherche IGDB pour : '{$query}'");
        
        try {
            $io->text("🔍 Recherche de jeux sur IGDB...");
            
            $games = $this->igdbClient->searchAllGames($query, 10);
            
            if (empty($games)) {
                $io->warning("❌ Aucun jeu trouvé sur IGDB pour '{$query}'");
                return Command::FAILURE;
            }
            
            $io->success(sprintf("✅ Trouvé %d jeux sur IGDB", count($games)));
            
            $io->table(
                ['Titre', 'ID IGDB', 'Note', 'Votes', 'Date'],
                array_map(function($game) {
                    return [
                        $game['name'] ?? 'N/A',
                        $game['id'] ?? 'N/A',
                        $game['total_rating'] ?? 'N/A',
                        $game['total_rating_count'] ?? 'N/A',
                        isset($game['first_release_date']) ? 
                            date('Y-m-d', $game['first_release_date']) : 'N/A'
                    ];
                }, array_slice($games, 0, 10))
            );
            
        } catch (\Throwable $e) {
            $io->error("❌ Erreur lors de la recherche IGDB : " . $e->getMessage());
            $io->text("Stack trace : " . $e->getTraceAsString());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
} 