<?php

namespace App\Command;

use App\Repository\GameRepository;
use App\Service\IgdbClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Pour améliorer la qualité de toutes les images existantes en base,
// faire dans le terminal dans le dossier CheckPoint-API :
// php bin/console app:update-existing-images

#[AsCommand(
    name: 'app:update-existing-images',
    description: 'Met à jour toutes les images existantes en base avec une meilleure qualité',
)]
class UpdateExistingImagesCommand extends Command
{
    private GameRepository $gameRepository;
    private IgdbClient $igdbClient;
    private EntityManagerInterface $entityManager;

    public function __construct(
        GameRepository $gameRepository,
        IgdbClient $igdbClient,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct();
        $this->gameRepository = $gameRepository;
        $this->igdbClient = $igdbClient;
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Mise à jour de la qualité des images existantes');

        // Récupère tous les jeux avec une coverUrl
        $games = $this->gameRepository->createQueryBuilder('g')
            ->where('g.coverUrl IS NOT NULL')
            ->andWhere('g.coverUrl != :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();

        $totalGames = count($games);
        $updatedCount = 0;

        if ($totalGames === 0) {
            $io->success('Aucun jeu avec image trouvé.');
            return Command::SUCCESS;
        }

        $io->text("Traitement de {$totalGames} jeux...");
        $io->progressStart($totalGames);

        foreach ($games as $game) {
            $originalUrl = $game->getCoverUrl();
            
            // Vérifie si l'image n'est pas déjà en haute qualité
            if (strpos($originalUrl, 't_cover_big') === false && 
                strpos($originalUrl, 't_1080p') === false && 
                strpos($originalUrl, 't_original') === false) {
                
                $improvedUrl = $this->igdbClient->improveImageQuality($originalUrl, 't_cover_big');
                
                if ($improvedUrl !== $originalUrl) {
                    $game->setCoverUrl($improvedUrl);
                    $game->setUpdatedAt(new \DateTimeImmutable());
                    $updatedCount++;
                }
            }
            
            $io->progressAdvance();
        }

        // Sauvegarde toutes les modifications
        $this->entityManager->flush();
        
        $io->progressFinish();
        $io->success([
            "Mise à jour terminée !",
            "{$updatedCount} images sur {$totalGames} ont été améliorées."
        ]);

        return Command::SUCCESS;
    }
} 