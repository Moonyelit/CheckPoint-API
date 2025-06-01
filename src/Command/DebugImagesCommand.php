<?php

namespace App\Command;

use App\Service\IgdbClient;
use App\Repository\GameRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-images',
    description: 'Debug les problèmes d\'images des jeux importés',
)]
class DebugImagesCommand extends Command
{
    private GameRepository $gameRepository;
    private IgdbClient $igdbClient;

    public function __construct(GameRepository $gameRepository, IgdbClient $igdbClient)
    {
        parent::__construct();
        $this->gameRepository = $gameRepository;
        $this->igdbClient = $igdbClient;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Analyse des images des jeux');

        // Statistiques générales
        $totalGames = $this->gameRepository->count([]);
        $gamesWithImages = $this->gameRepository->createQueryBuilder('g')
            ->select('COUNT(g)')
            ->where('g.coverUrl IS NOT NULL')
            ->andWhere('g.coverUrl != :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();

        $io->success("Total de jeux : $totalGames");
        $io->success("Jeux avec images : $gamesWithImages");
        $io->warning("Jeux sans images : " . ($totalGames - $gamesWithImages));

        // Affiche les jeux sans images
        $gamesWithoutImages = $this->gameRepository->createQueryBuilder('g')
            ->where('g.coverUrl IS NULL OR g.coverUrl = :empty')
            ->setParameter('empty', '')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        if (!empty($gamesWithoutImages)) {
            $io->section('Jeux sans images :');
            foreach ($gamesWithoutImages as $game) {
                $io->text("- {$game->getTitle()} (ID IGDB: {$game->getIgdbId()})");
            }
        }

        // Test des 5 jeux les plus populaires pour le frontend
        $io->section('Test des jeux populaires pour le frontend :');
        $popularGames = $this->gameRepository->createQueryBuilder('g')
            ->where('g.totalRating IS NOT NULL')
            ->andWhere('g.coverUrl IS NOT NULL')
            ->orderBy('g.totalRating', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($popularGames as $game) {
            $originalUrl = $game->getCoverUrl();
            $improvedUrl = $this->igdbClient->improveImageQuality($originalUrl, 't_cover_big');
            
            $io->text("🎮 {$game->getTitle()}");
            $io->text("   URL originale: $originalUrl");
            $io->text("   URL améliorée: $improvedUrl");
            $io->text("   Note: {$game->getTotalRating()}");
            $io->newLine();
        }

        // Test des jeux trending
        $io->section('Test des jeux récents (trending) :');
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
            $originalUrl = $game->getCoverUrl();
            $improvedUrl = $this->igdbClient->improveImageQuality($originalUrl, 't_cover_big');
            
            $io->text("🔥 {$game->getTitle()}");
            $io->text("   URL originale: $originalUrl");
            $io->text("   URL améliorée: $improvedUrl");
            $io->text("   Date de sortie: " . $game->getReleaseDate()?->format('Y-m-d'));
            $io->text("   Note: {$game->getTotalRating()}");
            $io->newLine();
        }

        return Command::SUCCESS;
    }
} 