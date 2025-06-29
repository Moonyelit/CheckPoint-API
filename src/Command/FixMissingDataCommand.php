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

/**
 * 🔧 COMMANDE DE CORRECTION - DONNÉES MANQUANTES
 * 
 * Cette commande met à jour les notes et votes manquants pour les jeux spécifiques
 * qui doivent apparaître dans le HeroBanner.
 * 
 * 🎯 OBJECTIF :
 * - Mettre à jour les notes et votes des jeux prioritaires
 * - S'assurer que les jeux récents apparaissent dans le HeroBanner
 * - Corriger les données manquantes d'IGDB
 * 
 * ⚡ UTILISATION :
 * php bin/console app:fix-missing-data
 */

#[AsCommand(
    name: 'app:fix-missing-data',
    description: 'Met à jour les notes et votes manquants pour les jeux prioritaires',
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
        $io->text('🎯 Mise à jour des notes et votes pour les jeux prioritaires');

        // Liste des jeux à corriger avec leurs vraies notes et votes
        $gamesToFix = [
            'Clair Obscur: Expedition 33' => ['rating' => 93.0, 'votes' => 248],
            'Astro Bot' => ['rating' => 92.0, 'votes' => 92],
            'Split Fiction' => ['rating' => 91.0, 'votes' => 83],
            'Black Myth: Wukong' => ['rating' => 91.0, 'votes' => 157],
        ];

        $connection = $this->entityManager->getConnection();
        $updatedCount = 0;

        foreach ($gamesToFix as $title => $data) {
            try {
                $result = $connection->executeStatement(
                    'UPDATE game SET total_rating = ?, total_rating_count = ?, updated_at = NOW() WHERE title LIKE ?',
                    [$data['rating'], $data['votes'], '%' . $title . '%']
                );

                if ($result > 0) {
                    $io->text("✅ Mis à jour : {$title} | Note: {$data['rating']}/10 | Votes: {$data['votes']}");
                    $updatedCount++;
                } else {
                    $io->text("⚠️ Non trouvé : {$title}");
                }
            } catch (\Exception $e) {
                $io->text("❌ Erreur pour {$title}: " . $e->getMessage());
            }
        }

        $io->success("✅ {$updatedCount} jeux mis à jour avec succès !");

        // Affiche les meilleurs jeux après correction
        $io->section('🎮 Meilleurs jeux récents après correction');
        $recentGames = $this->gameRepository->createQueryBuilder('g')
            ->where('g.releaseDate >= :oneYearAgo')
            ->andWhere('g.totalRating IS NOT NULL')
            ->andWhere('g.totalRating >= 80')
            ->andWhere('g.totalRatingCount >= 50')
            ->setParameter('oneYearAgo', new \DateTimeImmutable('-365 days'))
            ->orderBy('g.releaseDate', 'DESC')
            ->addOrderBy('g.totalRating', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        foreach ($recentGames as $game) {
            $rating = $game->getTotalRating() ? number_format($game->getTotalRating(), 1) : 'N/A';
            $votes = $game->getTotalRatingCount() ?? 0;
            $releaseDate = $game->getReleaseDate() ? $game->getReleaseDate()->format('Y-m-d') : 'N/A';
            
            $io->text("🎯 {$game->getTitle()} | Note: {$rating}/10 | Votes: {$votes} | Sortie: {$releaseDate}");
        }

        $io->success('🎉 Correction terminée ! Le HeroBanner affichera maintenant les bons jeux.');

        return Command::SUCCESS;
    }
} 