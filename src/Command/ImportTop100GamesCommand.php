<?php

namespace App\Command;

use App\Service\GameImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 🏆 COMMANDE D'IMPORT - TOP 100 JEUX DE TOUS LES TEMPS
 * 
 * Cette commande récupère les 100 meilleurs jeux de tous les temps depuis l'API IGDB
 * avec des critères de qualité stricts pour garantir que seuls les vrais AAA et 
 * hits populaires soient importés.
 * 
 * 📊 CRITÈRES DE SÉLECTION :
 * - Note minimum : 85/100 (excellente qualité)
 * - Votes minimum : 50+ (popularité confirmée)
 * - Tri : Par note décroissante, puis par nombre de votes
 * - Limite : 100 jeux maximum
 * 
 * 🎯 OBJECTIF :
 * Alimenter l'endpoint /api/games/top100 utilisé par le HeroBanner comme fallback
 * quand les jeux de l'année ne sont pas disponibles.
 * 
 * ⚡ UTILISATION :
 * php bin/console app:import-top100-games
 * 
 * 💡 FRÉQUENCE RECOMMANDÉE :
 * Une fois par semaine (les classiques changent peu)
 */

// Pour récupérer les jeux du Top 100 d'IGDB, 
// faire dans le terminal dans le dossier CheckPoint-API : 
// php bin/console app:import-top100-games

#[AsCommand(
    name: 'app:import-top100-games',
    description: 'Importe les 100 meilleurs jeux de tous les temps depuis IGDB (50+ votes, note 85+)',
)]
class ImportTop100GamesCommand extends Command
{
    private GameImporter $importer;

    public function __construct(GameImporter $importer)
    {
        parent::__construct();
        $this->importer = $importer;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>🏆 Démarrage de l\'import du Top 100 de tous les temps...</info>');
        $output->writeln('<comment>Critères : Note ≥85, Votes ≥50, Tri par note décroissante</comment>');

        $this->importer->importTop100Games();

        $output->writeln('<info>✅ Import du Top 100 terminé avec succès !</info>');
        $output->writeln('<comment>💡 Ces jeux alimentent l\'endpoint /api/games/top100</comment>');

        return Command::SUCCESS;
    }
} 