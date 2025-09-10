<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250822233954 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE corporate_identity DROP INDEX UNIQ_E9D4764AEF317FA9, ADD INDEX IDX_E9D4764AEF317FA9 (business_services_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE corporate_identity DROP INDEX IDX_E9D4764AEF317FA9, ADD UNIQUE INDEX UNIQ_E9D4764AEF317FA9 (business_services_id)');
    }
}
