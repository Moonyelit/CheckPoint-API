<?php

namespace App\Command;

use App\Service\GameImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


// Pour récupérer les 500 jeux les plus populaires, 
// faire dans le terminal: 
// php bin/console app:import-games



#[AsCommand(
    name: 'app:import-games',
    description: 'Importe les jeux les plus populaires depuis IGDB',
)]
class ImportGamesCommand extends Command

{
    private GameImporter $importer;

    public function __construct(GameImporter $importer)
    {
        parent::__construct();
        $this->importer = $importer;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Démarrage de l\'import des jeux IGDB...</info>');

        $this->importer->importPopularGames();

        $output->writeln('<info>Import terminé avec succès !</info>');

        return Command::SUCCESS;
    }
}
