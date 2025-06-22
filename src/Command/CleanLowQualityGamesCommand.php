<?php

namespace App\Command;

use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 🧹 COMMANDE DE NETTOYAGE - SUPPRESSION JEUX DE TRÈS FAIBLE QUALITÉ
 * 
 * Cette commande supprime les jeux de très faible qualité de la base de données
 * pour éviter qu'ils polluent les classements et apparaissent dans le HeroBanner.
 * 
 * 📊 CRITÈRES DE SUPPRESSION :
 * - Votes < 30 OU votes = NULL (très faible popularité)
 * - Suppression en cascade des screenshots et wallpapers liés
 * - Demande de confirmation avant suppression
 * 
 * 🎯 OBJECTIF :
 * Nettoyer la base de données des jeux obscurs type "Pixadom", "Kukoro", etc.
 * qui ont des notes élevées mais très peu de votes, faussant les classements.
 * 
 * ⚡ UTILISATION :
 * php bin/console app:clean-low-quality-games
 * 
 * ⚠️ ATTENTION :
 * Cette commande SUPPRIME définitivement des données ! 
 * Toujours vérifier les exemples affichés avant de confirmer.
 * 
 * 💡 FRÉQUENCE RECOMMANDÉE :
 * Une fois par mois ou après un gros import de nouveaux jeux
 * 
 * 📈 IMPACT ATTENDU :
 * Amélioration de la qualité des endpoints /api/games/top100 et /api/games/top100-year
 */

#[AsCommand(
    name: 'app:clean-low-quality-games',
    description: 'Supprime les jeux de très faible qualité (moins de 30 votes)'
)]
class CleanLowQualityGamesCommand extends Command
{
    public function __construct(
        private GameRepository $gameRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🧹 Nettoyage des jeux de très faible qualité');

        // Compte les jeux à supprimer (moins de 30 votes)
        $lowQualityGames = $this->gameRepository->createQueryBuilder('g')
            ->where('g.totalRatingCount < :minVotes OR g.totalRatingCount IS NULL')
            ->setParameter('minVotes', 30)
            ->getQuery()
            ->getResult();

        $count = count($lowQualityGames);

        if ($count === 0) {
            $io->success('Aucun jeu de très faible qualité trouvé !');
            return Command::SUCCESS;
        }

        $io->warning("$count jeux avec moins de 30 votes trouvés");

        // Affiche quelques exemples
        $io->section('Exemples de jeux qui seront supprimés :');
        $examples = array_slice($lowQualityGames, 0, 5);
        $rows = [];
        foreach ($examples as $game) {
            $votes = $game->getTotalRatingCount() ?? 0;
            $rows[] = [
                $game->getTitle(),
                $game->getTotalRating() ?? 'N/A',
                $votes,
                $game->getReleaseDate() ? $game->getReleaseDate()->format('Y') : 'N/A'
            ];
        }
        
        $io->table(['Titre', 'Note', 'Votes', 'Année'], $rows);

        // Demande confirmation
        if (!$io->confirm("Voulez-vous supprimer ces $count jeux de très faible qualité ?", false)) {
            $io->info('Opération annulée');
            return Command::SUCCESS;
        }

        // Supprime directement avec une requête SQL pour éviter les problèmes d'entités détachées
        $connection = $this->entityManager->getConnection();
        
        $io->text('Suppression en cours...');
        
        // Supprime d'abord les entités liées dans l'ordre correct pour éviter les contraintes de clé étrangère
        $connection->executeStatement('DELETE FROM user_wallpaper WHERE wallpaper_id IN (SELECT id FROM wallpaper WHERE game_id IN (SELECT id FROM game WHERE total_rating_count < 30 OR total_rating_count IS NULL))');
        $connection->executeStatement('DELETE FROM screenshot WHERE game_id IN (SELECT id FROM game WHERE total_rating_count < 30 OR total_rating_count IS NULL)');
        $connection->executeStatement('DELETE FROM wallpaper WHERE game_id IN (SELECT id FROM game WHERE total_rating_count < 30 OR total_rating_count IS NULL)');
        
        // Supprime les jeux
        $deleted = $connection->executeStatement('DELETE FROM game WHERE total_rating_count < 30 OR total_rating_count IS NULL');

        $io->success("✅ $deleted jeux de très faible qualité supprimés avec succès !");
        
        // Affiche les statistiques finales
        $remainingCount = $this->gameRepository->count([]);
        $highQualityCount = $this->gameRepository->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.totalRatingCount >= :minVotes')
            ->setParameter('minVotes', 50)
            ->getQuery()
            ->getSingleScalarResult();

        $io->table(
            ['Statut', 'Nombre'],
            [
                ['Jeux restants au total', $remainingCount],
                ['Jeux qualité décente (≥50 votes)', $highQualityCount],
                ['Jeux supprimés', $deleted]
            ]
        );

        return Command::SUCCESS;
    }
} 