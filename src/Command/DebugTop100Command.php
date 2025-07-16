<?php

namespace App\Command;

use App\Repository\GameRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-top100',
    description: 'Debug le Top 100 pour vérifier les critères et données',
)]
class DebugTop100Command extends Command
{
    public function __construct(
        private GameRepository $gameRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('🔍 Debug Top 100 - Vérification des Critères');
        
        // Test avec différents critères
        $testCases = [
            ['minVotes' => 200, 'minRating' => 80, 'limit' => 10],
            ['minVotes' => 100, 'minRating' => 75, 'limit' => 10],
            ['minVotes' => 50, 'minRating' => 70, 'limit' => 10],
        ];
        
        foreach ($testCases as $testCase) {
            $io->section("Test avec critères: minVotes={$testCase['minVotes']}, minRating={$testCase['minRating']}");
            
            $games = $this->gameRepository->findTopGamesWithCriteria(
                $testCase['minVotes'], 
                $testCase['minRating'], 
                $testCase['limit']
            );
            
            $totalCount = $this->gameRepository->countTopGamesWithCriteria(
                $testCase['minVotes'], 
                $testCase['minRating']
            );
            
            $io->text("Total count: $totalCount");
            $io->text("Jeux trouvés: " . count($games));
            
            if (!empty($games)) {
                $tableData = [];
                foreach ($games as $game) {
                    $tableData[] = [
                        $game->getTitle(),
                        $game->getTotalRating(),
                        $game->getTotalRatingCount(),
                        $game->getTotalRating() >= $testCase['minRating'] ? '✅' : '❌',
                        $game->getTotalRatingCount() >= $testCase['minVotes'] ? '✅' : '❌'
                    ];
                }
                
                $io->table(
                    ['Titre', 'Note', 'Votes', 'Note OK', 'Votes OK'],
                    $tableData
                );
            } else {
                $io->warning('Aucun jeu trouvé avec ces critères');
            }
            
            $io->newLine();
        }
        
        // Recherche spécifique de Skyrim
        $io->section('🔍 Recherche spécifique de Skyrim');
        
        $skyrimGames = $this->gameRepository->createQueryBuilder('g')
            ->where('g.title LIKE :title')
            ->setParameter('title', '%Skyrim%')
            ->getQuery()
            ->getResult();
        
        if (!empty($skyrimGames)) {
            $tableData = [];
            foreach ($skyrimGames as $game) {
                $tableData[] = [
                    $game->getTitle(),
                    $game->getTotalRating(),
                    $game->getTotalRatingCount(),
                    $game->getId()
                ];
            }
            
            $io->table(
                ['Titre', 'Note', 'Votes', 'ID'],
                $tableData
            );
        } else {
            $io->warning('Aucun Skyrim trouvé en base');
        }
        
        // Test avec les critères par défaut du provider (80 votes, 75 rating)
        $io->section('🔍 Test avec critères par défaut du provider (minVotes=80, minRating=75)');
        
        $defaultGames = $this->gameRepository->findTopGamesWithCriteria(80, 75, 10);
        $defaultCount = $this->gameRepository->countTopGamesWithCriteria(80, 75);
        
        $io->text("Total count avec critères par défaut: $defaultCount");
        $io->text("Jeux trouvés avec critères par défaut: " . count($defaultGames));
        
        if (!empty($defaultGames)) {
            $tableData = [];
            foreach ($defaultGames as $game) {
                $tableData[] = [
                    $game->getTitle(),
                    $game->getTotalRating(),
                    $game->getTotalRatingCount(),
                    $game->getTotalRating() >= 75 ? '✅' : '❌',
                    $game->getTotalRatingCount() >= 80 ? '✅' : '❌'
                ];
            }
            
            $io->table(
                ['Titre', 'Note', 'Votes', 'Note >= 75', 'Votes >= 80'],
                $tableData
            );
        }
        
        return Command::SUCCESS;
    }
} 