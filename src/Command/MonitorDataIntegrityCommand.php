<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\DBAL\Connection;

#[AsCommand(
    name: 'app:monitor-data-integrity',
    description: '🔍 Surveille l\'intégrité des données et prévient les pertes',
)]
class MonitorDataIntegrityCommand extends Command
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

        $io->title('🔍 SURVEILLANCE DE L\'INTÉGRITÉ DES DONNÉES');

        // 1. Vérification des données critiques
        $totalGames = $this->connection->fetchOne('SELECT COUNT(*) FROM game');
        $gamesWithRating = $this->connection->fetchOne('SELECT COUNT(*) FROM game WHERE total_rating IS NOT NULL');
        $gamesWithVotes = $this->connection->fetchOne('SELECT COUNT(*) FROM game WHERE total_rating_count IS NOT NULL');
        $gamesWithBoth = $this->connection->fetchOne('SELECT COUNT(*) FROM game WHERE total_rating IS NOT NULL AND total_rating_count IS NOT NULL');

        $integrityPercentage = round(($gamesWithBoth / $totalGames) * 100, 2);

        $io->section('📊 État de l\'intégrité des données');

        $io->table(
            ['Métrique', 'Valeur', 'Statut'],
            [
                ['Total jeux', $totalGames, '✅'],
                ['Avec rating ET votes', $gamesWithBoth, $integrityPercentage >= 80 ? '✅' : '⚠️'],
                ['% d\'intégrité', $integrityPercentage . '%', $integrityPercentage >= 80 ? '✅' : '❌'],
                ['Jeux sans données', $totalGames - $gamesWithBoth, $integrityPercentage >= 80 ? '✅' : '⚠️']
            ]
        );

        // 2. Vérification des jeux récents
        $recentGamesWithoutData = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM game 
             WHERE (total_rating IS NULL OR total_rating_count IS NULL) 
             AND release_date >= DATE_SUB(NOW(), INTERVAL 365 DAY)'
        );

        $io->section('🎮 Jeux récents (365 derniers jours)');
        
        if ($recentGamesWithoutData > 0) {
            $io->warning([
                "⚠️  $recentGamesWithoutData jeux récents sans données complètes",
                'Exécutez : php bin/console app:fix-missing-data'
            ]);
        } else {
            $io->success('✅ Tous les jeux récents ont des données complètes');
        }

        // 3. Vérification des jeux populaires
        $popularGamesWithoutData = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM game 
             WHERE (total_rating IS NULL OR total_rating_count IS NULL) 
             AND follows > 1000'
        );

        $io->section('🔥 Jeux populaires (follows > 1000)');
        
        if ($popularGamesWithoutData > 0) {
            $io->error([
                "❌  $popularGamesWithoutData jeux populaires sans données !",
                'CRITIQUE : Ces jeux devraient avoir des données'
            ]);
        } else {
            $io->success('✅ Tous les jeux populaires ont des données');
        }

        // 4. Recommandations
        $io->section('💡 Recommandations');

        if ($integrityPercentage < 80) {
            $io->error([
                'CRITIQUE : Intégrité des données faible !',
                'Actions recommandées :',
                '1. php bin/console app:fix-missing-data',
                '2. Vérifier les commandes de nettoyage',
                '3. Surveiller les imports automatiques'
            ]);
        } elseif ($integrityPercentage < 95) {
            $io->warning([
                'ATTENTION : Intégrité des données dégradée',
                'Action recommandée : php bin/console app:fix-missing-data'
            ]);
        } else {
            $io->success([
                'EXCELLENT : Intégrité des données optimale',
                'Continuez à surveiller régulièrement'
            ]);
        }

        // 5. Planification de surveillance
        $io->section('📅 Planification de surveillance');
        $io->text([
            'Recommandé d\'exécuter cette commande :',
            '• Quotidiennement en développement',
            '• Hebdomadairement en production',
            '• Avant et après chaque import massif'
        ]);

        return Command::SUCCESS;
    }
} 