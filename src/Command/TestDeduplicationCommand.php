<?php

namespace App\Command;

use App\Repository\GameRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-deduplication',
    description: 'Teste la logique de déduplication des jeux'
)]
class TestDeduplicationCommand extends Command
{
    public function __construct(
        private GameRepository $gameRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🧪 Test de la déduplication');
        $io->newLine();

        // Test avec des titres spécifiques
        $testTitles = [
            'Clair Obscur: Expedition 33',
            'Clair Obscur: Expedition 33 – Deluxe Edition',
            'Astro Bot',
            'Astro Bot: Vicious Void',
            'Split Fiction',
            'Split Fiction: Friend\'s Pass'
        ];

        $io->text('🔍 Test de la regex sur les titres :');
        $io->newLine();

        foreach ($testTitles as $title) {
            $mainTitle = $this->extractMainTitle($title);
            $io->text(sprintf('   "%s" → "%s"', $title, $mainTitle));
        }

        $io->newLine();
        $io->text('📊 Test avec les vrais jeux de la base :');
        $io->newLine();

        // Récupère les jeux récents avec critères
        $oneYearAgo = new \DateTimeImmutable('-365 days');
        $games = $this->gameRepository->createQueryBuilder('g')
            ->andWhere('g.releaseDate >= :oneYearAgo')
            ->andWhere('g.totalRating IS NOT NULL')
            ->andWhere('g.totalRating >= 80')
            ->andWhere('(g.totalRatingCount >= 80 OR g.totalRatingCount IS NULL)')
            ->andWhere('(g.category = 0 OR g.category IS NULL)')
            ->setParameter('oneYearAgo', $oneYearAgo)
            ->orderBy('g.totalRating', 'DESC')
            ->addOrderBy('g.totalRatingCount', 'DESC')
            ->getQuery()
            ->getResult();

        $groupedGames = [];

        foreach ($games as $game) {
            $mainTitle = $this->extractMainTitle($game->getTitle());
            
            if (!isset($groupedGames[$mainTitle])) {
                $groupedGames[$mainTitle] = [];
            }
            
            $groupedGames[$mainTitle][] = $game;
        }

        foreach ($groupedGames as $mainTitle => $gameGroup) {
            if (count($gameGroup) > 1) {
                $io->text(sprintf('   🎯 Groupe "%s" (%d jeux) :', $mainTitle, count($gameGroup)));
                foreach ($gameGroup as $game) {
                    $io->text(sprintf('      - %s (Note: %.1f, Votes: %s)', 
                        $game->getTitle(), 
                        $game->getTotalRating(), 
                        $game->getTotalRatingCount()
                    ));
                }
                $io->newLine();
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Extrait le nom principal d'un titre de jeu avec une regex générique.
     * Supprime les suffixes courants (Edition, Remake, Remastered, DLC, Update, Pass, etc.).
     *
     * @param string $title Le titre complet du jeu
     * @return string Le nom principal du jeu
     */
    private function extractMainTitle(string $title): string
    {
        // Regex améliorée pour supprimer les suffixes courants après :, -, – (tiret long) ou (espace)
        $mainTitle = preg_replace(
            '/([:\-–]|\s)(Deluxe Edition|Ultimate Edition|Collector\'s Edition|Friend\'s Pass|Season Pass|Vicious Void|Vicious Void Galaxy|Winter Wonder|Stellar Speedway|A Big Adventure|Costume|Remastered|Remake|Definitive Edition|DLC|Update|Expansion|Galaxy|Wonder|Speedway|Pass|Edition)$/i',
            '',
            $title
        );

        return trim($mainTitle);
    }
} 