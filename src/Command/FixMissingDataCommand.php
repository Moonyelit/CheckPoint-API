<?php

namespace App\Command;

use App\Entity\Game;
use App\Repository\GameRepository;
use App\Service\IgdbClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-missing-data',
    description: 'Corrige les données manquantes (coverUrl, screenshots) pour les jeux existants',
)]
class FixMissingDataCommand extends Command
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
        $io->title('🔧 Correction des données manquantes');

        $gameRepository = $this->entityManager->getRepository(Game::class);
        $games = $gameRepository->findAll();

        $io->text(sprintf('📊 Analyse de %d jeux...', count($games)));

        $updatedCount = 0;
        $errorCount = 0;

        foreach ($games as $game) {
            $io->text(sprintf('🎮 Traitement de : %s', $game->getTitle()));
            
            $hasChanges = false;

            try {
                // Récupère les détails du jeu depuis IGDB
                $detailedGame = $this->igdbClient->getGameDetails($game->getIgdbId());
                
                if ($detailedGame) {
                    // Corrige la note si manquante
                    if ((!$game->getTotalRating() || $game->getTotalRating() === 0) && isset($detailedGame['total_rating'])) {
                        $game->setTotalRating($detailedGame['total_rating']);
                        $hasChanges = true;
                        $io->text('  ✅ Note ajoutée');
                    }

                    // Corrige le nombre de votes si manquant
                    if ((!$game->getTotalRatingCount() || $game->getTotalRatingCount() === 0) && isset($detailedGame['total_rating_count'])) {
                        $game->setTotalRatingCount($detailedGame['total_rating_count']);
                        $hasChanges = true;
                        $io->text('  ✅ Nombre de votes ajouté: ' . $detailedGame['total_rating_count']);
                    }

                    // Corrige l'image de couverture si manquante
                    if ((!$game->getCoverUrl() || $game->getCoverUrl() === '') && isset($detailedGame['cover']['url'])) {
                        $imageUrl = $detailedGame['cover']['url'];
                        if (strpos($imageUrl, '//') === 0) {
                            $imageUrl = 'https:' . $imageUrl;
                        }
                        $highQualityUrl = $this->igdbClient->improveImageQuality($imageUrl, 't_cover_big');
                        $game->setCoverUrl($highQualityUrl);
                        $hasChanges = true;
                        $io->text('  ✅ Couverture ajoutée');
                    }

                    if ($hasChanges) {
                        $game->setUpdatedAt(new \DateTimeImmutable());
                        $this->entityManager->persist($game);
                        $updatedCount++;
                    }
                }
            } catch (\Exception $e) {
                $io->error(sprintf('❌ Erreur pour %s : %s', $game->getTitle(), $e->getMessage()));
                $errorCount++;
            }
        }

        // Sauvegarde en base
        $this->entityManager->flush();

        $io->success(sprintf('✅ Correction terminée ! %d jeux mis à jour, %d erreurs', $updatedCount, $errorCount));

        return Command::SUCCESS;
    }
} 