<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250408101505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE stats_user_game (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, game_id INT NOT NULL, status VARCHAR(50) NOT NULL, progress INT DEFAULT NULL, playtime INT DEFAULT NULL, last_played DATE DEFAULT NULL, INDEX IDX_5764B6E0A76ED395 (user_id), UNIQUE INDEX user_game_unique (user_id, game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stats_user_game ADD CONSTRAINT FK_5764B6E0A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE stats_user_game DROP FOREIGN KEY FK_5764B6E0A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE stats_user_game
        SQL);
    }
}
