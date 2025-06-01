<?php

namespace App\Command;

use App\Service\GameImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// Pour récupérer les jeux populaires du moment (tendance), 
// faire dans le terminal: 
// php bin/console app:import-trending-games

#[AsCommand(
    name: 'app:import-trending-games',
    description: 'Importe les jeux populaires du moment depuis IGDB',
)]
class ImportTrendingGamesCommand extends Command
{
    private GameImporter $importer;

    public function __construct(GameImporter $importer)
    {
        parent::__construct();
        $this->importer = $importer;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Démarrage de l\'import des jeux populaires du moment IGDB...</info>');

        $this->importer->importTrendingGames();

        $output->writeln('<info>Import des jeux tendance terminé avec succès !</info>');

        return Command::SUCCESS;
    }
} 