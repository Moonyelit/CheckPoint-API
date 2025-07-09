<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250709125937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE game ADD publisher VARCHAR(255) DEFAULT NULL, ADD series JSON DEFAULT NULL, ADD alternative_titles JSON DEFAULT NULL, ADD release_dates_by_platform JSON DEFAULT NULL, ADD age_rating VARCHAR(50) DEFAULT NULL, ADD trailer_url VARCHAR(255) DEFAULT NULL, ADD ratings JSON DEFAULT NULL, ADD artworks JSON DEFAULT NULL, ADD videos JSON DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE game DROP publisher, DROP series, DROP alternative_titles, DROP release_dates_by_platform, DROP age_rating, DROP trailer_url, DROP ratings, DROP artworks, DROP videos
        SQL);
    }
}
