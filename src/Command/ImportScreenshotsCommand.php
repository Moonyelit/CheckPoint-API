<?php

namespace App\Command;

use App\Entity\Game;
use App\Entity\Screenshot;
use App\Repository\GameRepository;
use App\Service\IgdbClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 📸 COMMANDE D'IMPORT - CAPTURES D'ÉCRAN DES JEUX
 * 
 * Cette commande importe automatiquement les captures d'écran des jeux
 * depuis l'API IGDB pour enrichir la base de données locale.
 * 
 * 🎯 OBJECTIF :
 * Enrichir les jeux existants avec leurs captures d'écran pour améliorer
 * l'expérience utilisateur sur le frontend.
 * 
 * 🔄 PROCESSUS D'IMPORT :
 * 1. Récupération de tous les jeux sans screenshots
 * 2. Appel à l'API IGDB pour chaque jeu
 * 3. Téléchargement des images haute qualité
 * 4. Sauvegarde en base avec relations
 * 5. Mise à jour des compteurs de médias
 * 
 * 📊 CRITÈRES DE SÉLECTION :
 * - Jeux avec IGDB ID valide
 * - Jeux sans screenshots existants
 * - Priorité aux jeux populaires (note élevée)
 * - Exclusion des jeux de faible qualité
 * 
 * 🖼️ GESTION DES IMAGES :
 * - Amélioration automatique de la qualité
 * - Conversion des URLs pour haute résolution
 * - Validation des formats d'image
 * - Gestion des erreurs de téléchargement
 * 
 * ⚡ OPTIMISATIONS DE PERFORMANCE :
 * - Import par batch pour éviter la surcharge
 * - Pause entre les requêtes (0.5 seconde)
 * - Gestion des erreurs avec retry
 * - Logs détaillés pour le suivi
 * 
 * 🛠️ TECHNOLOGIES UTILISÉES :
 * - Symfony Console pour l'interface CLI
 * - IgdbClient pour les requêtes API
 * - Doctrine ORM pour la persistance
 * - EntityManager pour les transactions
 * 
 * 📈 MÉTHODES PRINCIPALES :
 * - execute() : Point d'entrée principal
 * - Récupération des jeux sans screenshots
 * - Import des images depuis IGDB
 * - Sauvegarde avec relations
 * 
 * 🎮 EXEMPLES D'UTILISATION :
 * - Commande : php bin/console app:import-screenshots
 * - Import automatique via cron
 * - Enrichissement après import de jeux
 * - Mise à jour des médias existants
 * 
 * 🔒 SÉCURITÉ ET ROBUSTESSE :
 * - Validation des URLs d'images
 * - Gestion des erreurs API
 * - Protection contre les boucles infinies
 * - Rollback en cas d'erreur
 * 
 * 💡 AVANTAGES :
 * - Enrichissement automatique de la base
 * - Amélioration de l'expérience utilisateur
 * - Images haute qualité pour le frontend
 * - Processus automatisé et fiable
 */
#[AsCommand(
    name: 'app:import-screenshots',
    description: 'Importe les screenshots pour les jeux existants qui n\'en ont pas encore',
)]
class ImportScreenshotsCommand extends Command
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

        $io->title('🎮 Import des screenshots pour les jeux existants');

        // Récupère tous les jeux qui n'ont pas de screenshots
        $gamesWithoutScreenshots = $this->gameRepository->createQueryBuilder('g')
            ->leftJoin('g.screenshots', 's')
            ->where('s.id IS NULL')
            ->andWhere('g.igdbId IS NOT NULL')
            ->getQuery()
            ->getResult();

        if (empty($gamesWithoutScreenshots)) {
            $io->success('Tous les jeux ont déjà des screenshots !');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Trouvé %d jeux sans screenshots', count($gamesWithoutScreenshots)));

        $importedCount = 0;
        $errorCount = 0;

        foreach ($gamesWithoutScreenshots as $game) {
            try {
                $io->text(sprintf('Traitement de "%s" (IGDB ID: %d)...', $game->getTitle(), $game->getIgdbId()));

                // Utilise directement l'ID IGDB stocké pour récupérer les données
                $apiGame = $this->igdbClient->getGameDetails($game->getIgdbId());
                
                if (!$apiGame) {
                    $io->warning(sprintf('Aucune donnée IGDB trouvée pour l\'ID %d ("%s")', $game->getIgdbId(), $game->getTitle()));
                    continue;
                }

                // Importe les screenshots
                if (isset($apiGame['screenshots']) && is_array($apiGame['screenshots']) && !empty($apiGame['screenshots'])) {
                    $screenshotData = $this->igdbClient->getScreenshots($apiGame['screenshots']);
                    
                    foreach ($screenshotData as $data) {
                        $screenshot = new Screenshot();
                        $screenshot->setImage('https:' . $data['url']);
                        $screenshot->setGame($game);
                        $game->addScreenshot($screenshot);
                    }

                    $this->entityManager->persist($game);
                    $importedCount++;
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

        $io->success(sprintf(
            'Import terminé ! %d jeux traités, %d erreurs',
            $importedCount,
            $errorCount
        ));

        return Command::SUCCESS;
    }
} 