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

        $io->title('🔧 Correction des données manquantes pour les jeux existants');

        // Récupère tous les jeux qui ont un igdbId mais qui manquent de coverUrl ou de screenshots
        $gamesToFix = $this->gameRepository->createQueryBuilder('g')
            ->leftJoin('g.screenshots', 's')
            ->where('g.igdbId IS NOT NULL')
            ->andWhere('(g.coverUrl IS NULL OR g.coverUrl = :empty OR s.id IS NULL)')
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();

        if (empty($gamesToFix)) {
            $io->success('Tous les jeux ont déjà des données complètes !');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Trouvé %d jeux avec des données manquantes', count($gamesToFix)));

        $fixedCount = 0;
        $errorCount = 0;

        foreach ($gamesToFix as $game) {
            try {
                $io->text(sprintf('Traitement de "%s" (IGDB ID: %d)...', $game->getTitle(), $game->getIgdbId()));

                // Récupère les détails complets du jeu depuis IGDB
                $detailedGame = $this->igdbClient->getGameDetails($game->getIgdbId());
                
                if (!$detailedGame) {
                    $io->warning(sprintf('Aucune donnée IGDB trouvée pour "%s"', $game->getTitle()));
                    continue;
                }

                $hasChanges = false;

                // Corrige l'image de couverture si manquante
                if ((!$game->getCoverUrl() || $game->getCoverUrl() === '') && isset($detailedGame['cover']['url'])) {
                    $highQualityUrl = $this->igdbClient->improveImageQuality('https:' . $detailedGame['cover']['url'], 't_cover_big');
                    $game->setCoverUrl($highQualityUrl);
                    $hasChanges = true;
                    $io->text('  ✅ Couverture ajoutée');
                }

                // Corrige les screenshots si manquants
                $screenshots = $game->getScreenshots();
                if ($screenshots->count() === 0 && isset($detailedGame['screenshots']) && is_array($detailedGame['screenshots'])) {
                    $screenshotData = $this->igdbClient->getScreenshots($detailedGame['screenshots']);
                    
                    foreach ($screenshotData as $data) {
                        $screenshot = new \App\Entity\Screenshot();
                        $screenshot->setImage('https:' . $data['url']);
                        $screenshot->setGame($game);
                        $game->addScreenshot($screenshot);
                    }
                    
                    $hasChanges = true;
                    $io->text(sprintf('  ✅ %d screenshots ajoutés', count($screenshotData)));
                }

                // Met à jour d'autres champs si nécessaire
                if (!$game->getSummary() && isset($detailedGame['summary'])) {
                    $game->setSummary($detailedGame['summary']);
                    $hasChanges = true;
                    $io->text('  ✅ Résumé ajouté');
                }

                if (!$game->getTotalRating() && isset($detailedGame['total_rating'])) {
                    $game->setTotalRating($detailedGame['total_rating']);
                    $hasChanges = true;
                    $io->text('  ✅ Note ajoutée');
                }

                if ($hasChanges) {
                    $game->setUpdatedAt(new \DateTimeImmutable());
                    $this->entityManager->persist($game);
                    $fixedCount++;
                } else {
                    $io->text('  ⚠️ Aucune donnée manquante détectée');
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

        $io->success([
            "Correction terminée !",
            "Jeux corrigés: $fixedCount",
            "Erreurs: $errorCount"
        ]);

        return Command::SUCCESS;
    }
} 