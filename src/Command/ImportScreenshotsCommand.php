<?php

namespace App\Command;

use App\Entity\Game;
use App\Entity\Screenshot;
use App\Repository\GameRepository;
use App\Service\IgdbClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-screenshots',
    description: 'Importe les screenshots pour les jeux existants qui n\'en ont pas encore',
)]
class ImportScreenshotsCommand extends Command
{
    public function __construct(
        private IgdbClient $igdbClient,
        private GameRepository $gameRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🎮 Import des screenshots pour les jeux existants');

        // Récupère tous les jeux qui n'ont pas de screenshots
        $gamesWithoutScreenshots = $this->gameRepository->createQueryBuilder('g')
            ->leftJoin('g.screenshots', 's')
            ->where('s.id IS NULL')
            ->andWhere('g.igdbId IS NOT NULL')
            ->getQuery()
            ->getResult();

        if (empty($gamesWithoutScreenshots)) {
            $io->success('Tous les jeux ont déjà des screenshots !');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Trouvé %d jeux sans screenshots', count($gamesWithoutScreenshots)));

        $importedCount = 0;
        $errorCount = 0;

        foreach ($gamesWithoutScreenshots as $game) {
            try {
                $io->text(sprintf('Traitement de "%s" (IGDB ID: %d)...', $game->getTitle(), $game->getIgdbId()));

                // Récupère les données du jeu depuis IGDB
                $apiGames = $this->igdbClient->searchGames($game->getTitle(), 1);
                
                if (empty($apiGames)) {
                    $io->warning(sprintf('Aucune donnée IGDB trouvée pour "%s"', $game->getTitle()));
                    continue;
                }

                $apiGame = $apiGames[0];

                // Vérifie si le jeu IGDB correspond (même ID)
                if ($apiGame['id'] !== $game->getIgdbId()) {
                    $io->warning(sprintf('ID IGDB différent pour "%s" (local: %d, IGDB: %d)', 
                        $game->getTitle(), $game->getIgdbId(), $apiGame['id']));
                    continue;
                }

                // Importe les screenshots
                if (isset($apiGame['screenshots']) && is_array($apiGame['screenshots'])) {
                    $screenshotData = $this->igdbClient->getScreenshots($apiGame['screenshots']);
                    
                    foreach ($screenshotData as $data) {
                        $screenshot = new Screenshot();
                        $screenshot->setImage('https:' . $data['url']);
                        $screenshot->setGame($game);
                        $game->addScreenshot($screenshot);
                    }

                    $this->entityManager->persist($game);
                    $importedCount++;

                    $io->text(sprintf('  ✅ %d screenshots importés', count($screenshotData)));
                } else {
                    $io->text('  ⚠️ Aucun screenshot disponible');
                }

                // Pause pour éviter de surcharger l'API IGDB
                usleep(500000); // 0.5 seconde

            } catch (\Throwable $e) {
                $io->error(sprintf('Erreur pour "%s": %s', $game->getTitle(), $e->getMessage()));
                $errorCount++;
            }
        }

        // Sauvegarde toutes les modifications
        $this->entityManager->flush();

        $io->success(sprintf(
            'Import terminé ! %d jeux traités, %d erreurs',
            $importedCount,
            $errorCount
        ));

        return Command::SUCCESS;
    }
} 