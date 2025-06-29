<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\DBAL\Connection;

#[AsCommand(
    name: 'app:diagnose-data-loss',
    description: '🔍 Diagnostique les pertes de données dans la base de données',
)]
class DiagnoseDataLossCommand extends Command
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔍 DIAGNOSTIC DES PERTES DE DONNÉES');
        $io->section('Analyse de la base de données');

        // 1. Statistiques générales
        $totalGames = $this->connection->fetchOne('SELECT COUNT(*) FROM game');
        $gamesWithRating = $this->connection->fetchOne('SELECT COUNT(*) FROM game WHERE total_rating IS NOT NULL');
        $gamesWithVotes = $this->connection->fetchOne('SELECT COUNT(*) FROM game WHERE total_rating_count IS NOT NULL');
        $gamesWithBoth = $this->connection->fetchOne('SELECT COUNT(*) FROM game WHERE total_rating IS NOT NULL AND total_rating_count IS NOT NULL');

        $io->table(
            ['Métrique', 'Valeur'],
            [
                ['Total jeux', $totalGames],
                ['Avec rating', $gamesWithRating],
                ['Avec votes', $gamesWithVotes],
                ['Avec rating ET votes', $gamesWithBoth],
                ['Jeux sans données', $totalGames - $gamesWithBoth],
                ['% de perte', round((($totalGames - $gamesWithBoth) / $totalGames) * 100, 2) . '%']
            ]
        );

        // 2. Jeux récents sans données (365 derniers jours)
        $recentGamesWithoutData = $this->connection->fetchAllAssociative(
            'SELECT title, total_rating, total_rating_count, release_date 
             FROM game 
             WHERE (total_rating IS NULL OR total_rating_count IS NULL) 
             AND release_date >= DATE_SUB(NOW(), INTERVAL 365 DAY)
             ORDER BY release_date DESC
             LIMIT 10'
        );

        if (!empty($recentGamesWithoutData)) {
            $io->section('🎮 Jeux récents sans données (365 derniers jours)');
            $io->table(
                ['Titre', 'Rating', 'Votes', 'Date de sortie'],
                array_map(fn($game) => [
                    $game['title'],
                    $game['total_rating'] ?? 'NULL',
                    $game['total_rating_count'] ?? 'NULL',
                    $game['release_date'] ?? 'NULL'
                ], $recentGamesWithoutData)
            );
        }

        // 3. Jeux populaires sans données
        $popularGamesWithoutData = $this->connection->fetchAllAssociative(
            'SELECT title, total_rating, total_rating_count, follows 
             FROM game 
             WHERE (total_rating IS NULL OR total_rating_count IS NULL) 
             AND follows > 1000
             ORDER BY follows DESC
             LIMIT 10'
        );

        if (!empty($popularGamesWithoutData)) {
            $io->section('🔥 Jeux populaires sans données (follows > 1000)');
            $io->table(
                ['Titre', 'Rating', 'Votes', 'Follows'],
                array_map(fn($game) => [
                    $game['title'],
                    $game['total_rating'] ?? 'NULL',
                    $game['total_rating_count'] ?? 'NULL',
                    $game['follows'] ?? 'NULL'
                ], $popularGamesWithoutData)
            );
        }

        // 4. Jeux spécifiques mentionnés
        $specificGames = $this->connection->fetchAllAssociative(
            'SELECT title, total_rating, total_rating_count, category, release_date 
             FROM game 
             WHERE title LIKE "%Split Fiction%" 
             OR title LIKE "%Indiana Jones%" 
             OR title LIKE "%Astro Bot%"
             ORDER BY total_rating DESC'
        );

        if (!empty($specificGames)) {
            $io->section('🎯 Jeux spécifiques');
            $io->table(
                ['Titre', 'Rating', 'Votes', 'Catégorie', 'Date de sortie'],
                array_map(fn($game) => [
                    $game['title'],
                    $game['total_rating'] ?? 'NULL',
                    $game['total_rating_count'] ?? 'NULL',
                    $game['category'] ?? 'NULL',
                    $game['release_date'] ?? 'NULL'
                ], $specificGames)
            );
        }

        // 5. Recommandations
        $io->section('💡 Recommandations');

        if ($totalGames - $gamesWithBoth > 0) {
            $io->warning([
                'Des données sont manquantes !',
                'Exécutez : php bin/console app:fix-missing-data'
            ]);
        }

        if ($gamesWithBoth < 50) {
            $io->error([
                'CRITIQUE : Très peu de jeux ont des données complètes !',
                'Vérifiez les commandes de nettoyage qui suppriment des données'
            ]);
        }

        $io->success('Diagnostic terminé !');

        return Command::SUCCESS;
    }
} 