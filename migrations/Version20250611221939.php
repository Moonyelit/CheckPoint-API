<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250611221939 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE user_wallpaper (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, wallpaper_id INT NOT NULL, selected_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', is_active TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_D572E9C8A76ED395 (user_id), INDEX IDX_D572E9C8488626AA (wallpaper_id), UNIQUE INDEX user_wallpaper_unique (user_id, wallpaper_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_wallpaper ADD CONSTRAINT FK_D572E9C8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_wallpaper ADD CONSTRAINT FK_D572E9C8488626AA FOREIGN KEY (wallpaper_id) REFERENCES wallpaper (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE user_wallpaper DROP FOREIGN KEY FK_D572E9C8A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_wallpaper DROP FOREIGN KEY FK_D572E9C8488626AA
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_wallpaper
        SQL);
    }
}
