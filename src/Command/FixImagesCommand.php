<?php

namespace App\Command;

use App\Service\IgdbClient;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-images',
    description: 'Corrige et améliore toutes les images des jeux en base',
)]
class FixImagesCommand extends Command
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
        
        $io->title('Correction et amélioration de toutes les images');

        // Récupère tous les jeux
        $games = $this->gameRepository->findAll();
        $totalGames = count($games);
        
        $io->info("Traitement de $totalGames jeux...");

        $improvedCount = 0;
        $errorCount = 0;
        $nullCount = 0;

        foreach ($games as $game) {
            $title = $game->getTitle();
            $originalUrl = $game->getCoverUrl();

            if (!$originalUrl) {
                $nullCount++;
                $io->text("⚠️  $title : Pas d'URL d'image");
                continue;
            }

            try {
                // Vérifie si l'URL est valide
                if (!filter_var($originalUrl, FILTER_VALIDATE_URL)) {
                    $io->error("❌ URL invalide pour $title : $originalUrl");
                    $errorCount++;
                    continue;
                }

                // Améliore la qualité de l'image
                $improvedUrl = $this->igdbClient->improveImageQuality($originalUrl, 't_cover_big');

                // Met à jour seulement si l'URL a changé
                if ($improvedUrl !== $originalUrl) {
                    $game->setCoverUrl($improvedUrl);
                    $game->setUpdatedAt(new \DateTimeImmutable());
                    $improvedCount++;
                    $io->text("✅ Améliorée: $title");
                    $io->text("   Avant: $originalUrl");
                    $io->text("   Après: $improvedUrl");
                } else {
                    $io->text("🔍 Déjà optimisée: $title");
                }

            } catch (\Exception $e) {
                $errorCount++;
                $io->error("❌ Erreur pour $title : " . $e->getMessage());
            }
        }

        // Sauvegarde toutes les modifications
        if ($improvedCount > 0) {
            $this->entityManager->flush();
            $io->success("$improvedCount images améliorées et sauvegardées !");
        }

        // Affichage du résumé
        $io->section('Résumé :');
        $io->listing([
            "Total de jeux : $totalGames",
            "Images améliorées : $improvedCount",
            "Jeux sans images : $nullCount",
            "Erreurs : $errorCount",
            "Déjà optimisées : " . ($totalGames - $improvedCount - $nullCount - $errorCount)
        ]);

        // Test des jeux trending après amélioration
        $io->section('Test des 5 jeux trending après amélioration :');
        $twoYearsAgo = new \DateTimeImmutable('-2 years');
        
        $trendingGames = $this->gameRepository->createQueryBuilder('g')
            ->where('g.releaseDate >= :twoYearsAgo')
            ->andWhere('g.totalRating >= :minRating')
            ->andWhere('g.coverUrl IS NOT NULL')
            ->setParameter('twoYearsAgo', $twoYearsAgo)
            ->setParameter('minRating', 70)
            ->orderBy('g.totalRating', 'DESC')
            ->addOrderBy('g.releaseDate', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($trendingGames as $game) {
            $io->text("🔥 {$game->getTitle()}");
            $io->text("   URL: {$game->getCoverUrl()}");
            $io->text("   Note: {$game->getTotalRating()}");
        }

        return Command::SUCCESS;
    }
} 