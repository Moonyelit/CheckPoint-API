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

/**
 * 🖼️ COMMANDE DE MAINTENANCE - MISE À JOUR QUALITÉ IMAGES EXISTANTES
 * 
 * Cette commande améliore la qualité de toutes les images de jeux déjà présentes
 * en base de données en les passant en haute résolution.
 * 
 * 🔧 FONCTIONNALITÉS :
 * 
 * 📊 CRITÈRES DE TRAITEMENT :
 * - Jeux avec coverUrl non null et non vide
 * - Exclusion des images déjà en haute qualité
 * - Amélioration vers format 't_cover_big' (264x374px)
 * 
 * 🎯 OBJECTIF :
 * - Uniformiser la qualité des images en base
 * - Améliorer l'affichage dans HeroBanner et carousels
 * - Optimiser l'expérience visuelle utilisateur
 * 
 * ⚡ UTILISATION :
 * php bin/console app:update-existing-images
 * 
 * 🔍 DÉTECTION INTELLIGENTE :
 * - Skip les images déjà optimisées (t_cover_big, t_1080p, t_original)
 * - Met à jour uniquement les images de basse qualité
 * - Progress bar pour suivi du traitement
 * 
 * 💾 SAUVEGARDE :
 * - Batch processing pour performance
 * - Mise à jour du timestamp updatedAt
 * - Flush global en fin de traitement
 * 
 * 📈 IMPACT :
 * - Amélioration visuelle immédiate
 * - Cohérence d'affichage sur tous les endpoints
 * - Meilleure qualité pour HeroBanner, carousels, etc.
 * 
 * 💡 FRÉQUENCE RECOMMANDÉE :
 * - Après gros imports de nouveaux jeux
 * - Une fois par mois pour maintenance
 * - Suite à mise à jour service IgdbClient
 */

// Pour améliorer la qualité de toutes les images existantes en base,
// faire dans le terminal dans le dossier CheckPoint-API :
// php bin/console app:update-existing-images

#[AsCommand(
    name: 'app:update-existing-images',
    description: 'Met à jour toutes les images existantes en base avec une meilleure qualité (t_cover_big)',
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
        
        $io->title('🖼️ Mise à jour de la qualité des images existantes');
        $io->info('Cette commande améliore toutes les images vers le format t_cover_big (264x374px)');

        // 📋 Récupère tous les jeux avec une coverUrl valide
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

        $io->text("📊 Traitement de {$totalGames} jeux...");
        $io->progressStart($totalGames);

        foreach ($games as $game) {
            $originalUrl = $game->getCoverUrl();
            
            // 🔍 Vérifie si l'image n'est pas déjà en haute qualité
            if (strpos($originalUrl, 't_cover_big') === false && 
                strpos($originalUrl, 't_1080p') === false && 
                strpos($originalUrl, 't_original') === false) {
                
                // ✨ Améliore la qualité vers t_cover_big
                $improvedUrl = $this->igdbClient->improveImageQuality($originalUrl, 't_cover_big');
                
                if ($improvedUrl !== $originalUrl) {
                    $game->setCoverUrl($improvedUrl);
                    $game->setUpdatedAt(new \DateTimeImmutable());
                    $updatedCount++;
                }
            }
            
            $io->progressAdvance();
        }

        // 💾 Sauvegarde toutes les modifications en une fois (performance)
        $this->entityManager->flush();
        
        $io->progressFinish();
        $io->success([
            "✅ Mise à jour terminée !",
            "📈 {$updatedCount} images sur {$totalGames} ont été améliorées.",
            "🎯 Impact : Meilleure qualité d'affichage dans HeroBanner et carousels"
        ]);

        return Command::SUCCESS;
    }
} 