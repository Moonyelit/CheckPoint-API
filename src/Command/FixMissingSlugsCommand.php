<?php

namespace App\Command;

use App\Entity\Game;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Cocur\Slugify\Slugify;

#[AsCommand(
    name: 'app:fix-missing-slugs',
    description: 'Génère les slugs manquants pour tous les jeux',
)]
class FixMissingSlugsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameRepository $gameRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $slugify = new Slugify();

        // Récupère tous les jeux sans slug
        $games = $this->gameRepository->createQueryBuilder('g')
            ->where('g.slug IS NULL OR g.slug = :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();

        if (empty($games)) {
            $io->success('Aucun jeu sans slug trouvé.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Génération des slugs pour %d jeux...', count($games)));

        $updatedCount = 0;
        foreach ($games as $game) {
            if ($game->getTitle()) {
                $slug = $slugify->slugify($game->getTitle());
                $game->setSlug($slug);
                $updatedCount++;
                
                $io->text(sprintf('✓ %s → %s', $game->getTitle(), $slug));
            }
        }

        // Sauvegarde en base
        $this->entityManager->flush();

        $io->success(sprintf('%d slugs générés avec succès !', $updatedCount));

        return Command::SUCCESS;
    }
} 