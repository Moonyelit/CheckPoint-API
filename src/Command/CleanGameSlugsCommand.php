<?php

namespace App\Command;

use App\Repository\GameRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Cocur\Slugify\Slugify;

#[AsCommand(
    name: 'app:clean-game-slugs',
    description: 'Nettoie les slugs des jeux en enlevant les IDs IGDB et gère les doublons',
)]
class CleanGameSlugsCommand extends Command
{
    public function __construct(
        private GameRepository $gameRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🧹 Nettoyage automatique des slugs des jeux');
        $io->text('Cette commande va nettoyer tous les slugs qui contiennent des IDs IGDB');
        $io->text('🔄 Peut être relancée sans risque (gestion des doublons automatique)');

        // Récupérer tous les jeux
        $games = $this->gameRepository->findAll();
        $io->text(sprintf('📊 %d jeux trouvés dans la base de données', count($games)));

        $slugify = new Slugify();
        $updatedCount = 0;
        $errors = [];
        $skippedCount = 0;

        // Première passe : identifier tous les slugs à nettoyer
        $gamesToUpdate = [];
        $slugMap = []; // Pour tracker les slugs déjà utilisés
        
        foreach ($games as $game) {
            $oldSlug = $game->getSlug();
            $title = $game->getTitle();

            // Vérifier si le slug contient un ID IGDB (se termine par -nombre)
            if (preg_match('/^(.+)-\d+$/', $oldSlug, $matches)) {
                $baseSlug = $matches[1];
                $newSlug = $this->generateUniqueSlugWithMap($baseSlug, $game->getId(), $slugMap);
                
                if ($newSlug !== $oldSlug) {
                    $gamesToUpdate[] = [
                        'game' => $game,
                        'oldSlug' => $oldSlug,
                        'newSlug' => $newSlug,
                        'title' => $title
                    ];
                    $slugMap[$newSlug] = $game->getId(); // Marquer ce slug comme utilisé
                } else {
                    $skippedCount++;
                    $io->text(sprintf('⏭️  %s : slug déjà optimal (%s)', $title, $oldSlug));
                }
            } else {
                $skippedCount++;
                $io->text(sprintf('⏭️  %s : slug déjà propre (%s)', $title, $oldSlug));
                $slugMap[$oldSlug] = $game->getId(); // Marquer ce slug comme utilisé
            }
        }

        if (empty($gamesToUpdate)) {
            $io->success('✅ Tous les slugs sont déjà propres ! Aucune modification nécessaire.');
            return Command::SUCCESS;
        }

        $io->section(sprintf('🔄 Mise à jour de %d slugs', count($gamesToUpdate)));

        // Deuxième passe : appliquer les modifications
        foreach ($gamesToUpdate as $update) {
            try {
                $update['game']->setSlug($update['newSlug']);
                $this->gameRepository->getEntityManager()->persist($update['game']);
                $updatedCount++;
                
                $io->text(sprintf('✅ %s : %s → %s', $update['title'], $update['oldSlug'], $update['newSlug']));
            } catch (\Exception $e) {
                $errors[] = sprintf('❌ Erreur pour %s : %s', $update['title'], $e->getMessage());
            }
        }

        // Sauvegarder les modifications
        try {
            $this->gameRepository->getEntityManager()->flush();
            $io->success(sprintf('✅ Nettoyage terminé ! %d slugs mis à jour, %d ignorés', $updatedCount, $skippedCount));
        } catch (\Exception $e) {
            $io->error('❌ Erreur lors de la sauvegarde : ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Statistiques finales
        $totalGames = count($games);
        $cleanSlugs = $totalGames - $updatedCount;
        
        $io->table(
            ['Statut', 'Nombre'],
            [
                ['Jeux au total', $totalGames],
                ['Slugs nettoyés', $updatedCount],
                ['Slugs déjà propres', $cleanSlugs],
                ['Erreurs', count($errors)]
            ]
        );

        // Afficher les erreurs s'il y en a
        if (!empty($errors)) {
            $io->section('⚠️ Erreurs rencontrées');
            foreach ($errors as $error) {
                $io->text($error);
            }
        }

        $io->text('💡 Les slugs sont maintenant propres et uniques !');
        $io->text('🔄 Cette commande peut être relancée sans risque.');

        return Command::SUCCESS;
    }

    /**
     * Génère un slug unique sans inclure l'ID IGDB en utilisant une map des slugs existants
     */
    private function generateUniqueSlugWithMap(string $baseSlug, ?int $existingId, array &$slugMap): string
    {
        $slug = $baseSlug;
        $counter = 1;
        
        // Vérifier si le slug existe déjà dans notre map ou en base
        while (true) {
            $existingGame = $this->gameRepository->findOneBy(['slug' => $slug]);
            $inMap = isset($slugMap[$slug]);
            
            // Si aucun jeu avec ce slug, ou si c'est le même jeu (mise à jour)
            if ((!$existingGame && !$inMap) || ($existingId && $existingGame && $existingGame->getId() === $existingId)) {
                break;
            }
            
            // Sinon, ajouter un suffixe numérique
            $slug = $baseSlug . '-' . $counter;
            $counter++;
            
            // Éviter les boucles infinies
            if ($counter > 100) {
                // Si on a trop de tentatives, ajouter un timestamp pour garantir l'unicité
                $slug = $baseSlug . '-' . time();
                break;
            }
        }
        
        return $slug;
    }
} 