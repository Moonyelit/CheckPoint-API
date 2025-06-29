<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250629180718 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE game (id INT AUTO_INCREMENT NOT NULL, igdb_id INT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) DEFAULT NULL, release_date DATE DEFAULT NULL, developer VARCHAR(255) DEFAULT NULL, platforms JSON DEFAULT NULL, genres JSON DEFAULT NULL, game_modes JSON DEFAULT NULL, perspectives JSON DEFAULT NULL, total_rating DOUBLE PRECISION DEFAULT NULL, summary LONGTEXT DEFAULT NULL, cover_url VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', recent_hypes INT DEFAULT NULL, follows INT DEFAULT NULL, total_rating_count INT DEFAULT NULL, last_popularity_update DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', category VARCHAR(50) DEFAULT NULL, UNIQUE INDEX UNIQ_232B318C989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE screenshot (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, image VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_58991E41E48FD905 (game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE stats_user (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, favorite_genre_id INT NOT NULL, total_playtime INT DEFAULT NULL, games_completed INT DEFAULT NULL, total_achievements INT DEFAULT NULL, level INT DEFAULT NULL, xp_points INT DEFAULT NULL, user_rank VARCHAR(50) DEFAULT NULL, UNIQUE INDEX UNIQ_CCFFBE09A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE stats_user_game (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, game_id INT NOT NULL, status VARCHAR(50) NOT NULL, progress INT DEFAULT NULL, playtime INT DEFAULT NULL, last_played DATE DEFAULT NULL, INDEX IDX_5764B6E0A76ED395 (user_id), UNIQUE INDEX user_game_unique (user_id, game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, pseudo VARCHAR(15) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, email_verified TINYINT(1) DEFAULT 0 NOT NULL, profile_image VARCHAR(500) DEFAULT NULL, tutorial_completed TINYINT(1) DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_wallpaper (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, wallpaper_id INT NOT NULL, selected_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', is_active TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_D572E9C8A76ED395 (user_id), INDEX IDX_D572E9C8488626AA (wallpaper_id), UNIQUE INDEX user_wallpaper_unique (user_id, wallpaper_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE wallpaper (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, image VARCHAR(255) DEFAULT NULL, INDEX IDX_D592642CE48FD905 (game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE screenshot ADD CONSTRAINT FK_58991E41E48FD905 FOREIGN KEY (game_id) REFERENCES game (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stats_user ADD CONSTRAINT FK_CCFFBE09A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stats_user_game ADD CONSTRAINT FK_5764B6E0A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_wallpaper ADD CONSTRAINT FK_D572E9C8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_wallpaper ADD CONSTRAINT FK_D572E9C8488626AA FOREIGN KEY (wallpaper_id) REFERENCES wallpaper (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wallpaper ADD CONSTRAINT FK_D592642CE48FD905 FOREIGN KEY (game_id) REFERENCES game (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE screenshot DROP FOREIGN KEY FK_58991E41E48FD905
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stats_user DROP FOREIGN KEY FK_CCFFBE09A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stats_user_game DROP FOREIGN KEY FK_5764B6E0A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_wallpaper DROP FOREIGN KEY FK_D572E9C8A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_wallpaper DROP FOREIGN KEY FK_D572E9C8488626AA
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wallpaper DROP FOREIGN KEY FK_D592642CE48FD905
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE game
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE screenshot
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE stats_user
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE stats_user_game
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_wallpaper
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE wallpaper
        SQL);
    }
}
