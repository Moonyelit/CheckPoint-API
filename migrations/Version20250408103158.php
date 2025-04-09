<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250408103158 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE stats_user (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, favorite_genre_id INT NOT NULL, total_playtime INT DEFAULT NULL, games_completed INT DEFAULT NULL, total_achievements INT DEFAULT NULL, level INT DEFAULT NULL, xp_points INT DEFAULT NULL, user_rank VARCHAR(50) DEFAULT NULL, UNIQUE INDEX UNIQ_CCFFBE09A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stats_user ADD CONSTRAINT FK_CCFFBE09A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE stats_user DROP FOREIGN KEY FK_CCFFBE09A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE stats_user
        SQL);
    }
}
