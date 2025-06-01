<?php

namespace App\Command;

use App\Service\GameImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 🆕 COMMANDE D'IMPORT - TOP 30 JEUX DE L'ANNÉE
 * 
 * Cette commande récupère les meilleurs jeux sortis dans les 365 derniers jours
 * depuis l'API IGDB avec des critères de qualité très stricts pour garantir
 * que seuls les vrais hits récents soient importés.
 * 
 * 📊 CRITÈRES DE SÉLECTION :
 * - Période : 365 derniers jours uniquement
 * - Note minimum : 75/100 (très bonne qualité)
 * - Votes minimum : 100+ (popularité forte)
 * - Tri : Par note décroissante, puis par nombre de votes
 * - Limite : 50 jeux maximum
 * 
 * 🎯 OBJECTIF :
 * Alimenter l'endpoint /api/games/top100-year utilisé en PRIORITÉ par le HeroBanner
 * pour afficher les hits récents avant les classiques de tous les temps.
 * 
 * ⚡ UTILISATION :
 * php bin/console app:import-top-year-games
 * 
 * 💡 FRÉQUENCE RECOMMANDÉE :
 * Tous les 2-3 jours (les nouveautés évoluent rapidement)
 * 
 * 🔥 EXEMPLES DE JEUX RÉCUPÉRÉS :
 * - Clair Obscur: Expedition 33 (162 votes, 91.16 rating)
 * - Black Myth: Wukong (158 votes, 89.33 rating)
 * - Silent Hill 2 Remake (148 votes, 88.89 rating)
 */

#[AsCommand(
    name: 'app:import-top-year-games',
    description: 'Importe les meilleurs jeux de l\'année (365 derniers jours, 100+ votes, note 75+)',
)]
class ImportTopYearGamesCommand extends Command
{
    public function __construct(
        private GameImporter $gameImporter
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🆕 Import des meilleurs jeux de l\'année (365 derniers jours)');
        $io->info('Critères : Sortis dans les 365 derniers jours, Note ≥75, Votes ≥100');
        $io->text('🎯 Priorité HeroBanner : Ces jeux s\'affichent en PREMIER sur la page d\'accueil');

        try {
            $importedCount = $this->gameImporter->importTopYearGames();
            
            $io->success("✅ Import terminé ! {$importedCount} jeux de l'année traités.");
            $io->text('💡 Ces jeux alimentent l\'endpoint /api/games/top100-year');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("❌ Erreur lors de l'import : " . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
} 