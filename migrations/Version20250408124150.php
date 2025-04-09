<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250408124150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE game (id INT AUTO_INCREMENT NOT NULL, igdb_id INT NOT NULL, title VARCHAR(255) NOT NULL, release_date DATE DEFAULT NULL, developer VARCHAR(255) DEFAULT NULL, platforms JSON DEFAULT NULL, genres JSON DEFAULT NULL, total_rating DOUBLE PRECISION DEFAULT NULL, summary LONGTEXT DEFAULT NULL, cover_url VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE wallpaper (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, image VARCHAR(255) DEFAULT NULL, INDEX IDX_D592642CE48FD905 (game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wallpaper ADD CONSTRAINT FK_D592642CE48FD905 FOREIGN KEY (game_id) REFERENCES game (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE wallpaper DROP FOREIGN KEY FK_D592642CE48FD905
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE game
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE wallpaper
        SQL);
    }
}
