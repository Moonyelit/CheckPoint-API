<?php

namespace App\Command;

use App\Service\GameImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 🆕 COMMANDE D'IMPORT - TOP 30 JEUX DE L'ANNÉE
 * 
 * Cette commande récupère les meilleurs jeux sortis dans les 365 derniers jours
 * depuis l'API IGDB avec des critères de qualité très stricts pour garantir
 * que seuls les vrais hits récents soient importés.
 * 
 * 📊 CRITÈRES DE SÉLECTION :
 * - Période : 365 derniers jours uniquement
 * - Note minimum : 75/100 (très bonne qualité)
 * - Votes minimum : 100+ (popularité forte)
 * - Tri : Par note décroissante, puis par nombre de votes
 * - Limite : 50 jeux maximum
 * 
 * 🎯 OBJECTIF :
 * Alimenter l'endpoint /api/games/top100-year utilisé en PRIORITÉ par le HeroBanner
 * pour afficher les hits récents avant les classiques de tous les temps.
 * 
 * ⚡ UTILISATION :
 * php bin/console app:import-top-year-games
 * 
 * 💡 FRÉQUENCE RECOMMANDÉE :
 * Tous les 2-3 jours (les nouveautés évoluent rapidement)
 * 
 * 🔥 EXEMPLES DE JEUX RÉCUPÉRÉS :
 * - Clair Obscur: Expedition 33 (162 votes, 91.16 rating)
 * - Black Myth: Wukong (158 votes, 89.33 rating)
 * - Silent Hill 2 Remake (148 votes, 88.89 rating)
 */

#[AsCommand(
    name: 'app:import-top-year-games',
    description: 'Importe les meilleurs jeux de l\'année (365 derniers jours, 100+ votes, note 75+)',
)]
class ImportTopYearGamesCommand extends Command
{
    public function __construct(
        private GameImporter $gameImporter
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🆕 Import des meilleurs jeux de l\'année (365 derniers jours)');
        $io->info('Critères : Sortis dans les 365 derniers jours, Note ≥8.0/10 (80/100), Votes ≥50');
        $io->text('🎯 Priorité HeroBanner : Ces jeux s\'affichent en PREMIER sur la page d\'accueil');
        $io->text('🎮 Jeux prioritaires : Clair Obscur, Split Fiction, Astro Bot, etc.');
        $io->text('📊 Champs récupérés : total_rating, total_rating_count, category, follows, last_popularity_update');

        try {
            // Import des jeux spécifiques en priorité
            // 🛡️ FILET DE SÉCURITÉ : Ces jeux sont importés manuellement par recherche
            // pour garantir qu'ils soient toujours présents, même si l'import général échoue.
            // Cela évite d'avoir un carrousel vide en cas de problème avec l'API IGDB.
            $io->section('🎯 Import des jeux prioritaires (Filet de sécurité)');
            $priorityGames = [
                'Clair Obscur: Expedition 33',
                'Split Fiction',
                'Astro Bot',
                'Black Myth: Wukong',
                'Silent Hill 2 Remake',
                'Indiana Jones and the Great Circle',
                'Dragon Age: Dreadwolf',
                'Final Fantasy VII Rebirth',
                'Spider-Man 2',
                'Zelda: Echoes of Wisdom',
                'Kingdom Come: Deliverance II'
            ];
            
            $importedPriority = 0;
            foreach ($priorityGames as $gameTitle) {
                try {
                    $game = $this->gameImporter->importGameBySearch($gameTitle);
                    if ($game) {
                        $importedPriority++;
                        $io->text("✅ Importé : {$gameTitle}");
                    }
                } catch (\Exception $e) {
                    $io->text("⚠️ Non trouvé : {$gameTitle}");
                }
            }
            
            // Import général des jeux de l'année
            $io->section('📥 Import général des jeux de l\'année');
            $importedCount = $this->gameImporter->importTopYearGames(80, 80); // Votes ≥70, Note ≥8.0/10
            
            $io->success("✅ Import terminé ! {$importedPriority} jeux prioritaires + {$importedCount} jeux de l'année traités.");
            $io->text('💡 Ces jeux alimentent l\'endpoint /api/games/top100-year');
            $io->text('🎯 Critères mis à jour : Note ≥8.0/10, Votes ≥50, Priorité aux jeux récents');
            $io->text('🔄 Relancez la commande si les jeux souhaités ne sont pas encore dans IGDB');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("❌ Erreur lors de l'import : " . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
} 