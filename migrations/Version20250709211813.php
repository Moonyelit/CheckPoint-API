<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250709211813 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE game ADD screenshots_count INT DEFAULT NULL, ADD artworks_count INT DEFAULT NULL, ADD videos_count INT DEFAULT NULL, DROP recent_hypes, DROP follows, DROP release_dates_by_platform, DROP trailer_url, DROP ratings
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE game ADD recent_hypes INT DEFAULT NULL, ADD follows INT DEFAULT NULL, ADD release_dates_by_platform JSON DEFAULT NULL, ADD trailer_url VARCHAR(255) DEFAULT NULL, ADD ratings JSON DEFAULT NULL, DROP screenshots_count, DROP artworks_count, DROP videos_count
        SQL);
    }
}
