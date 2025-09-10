<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250812114226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE corporate_identity CHANGE business_services_id business_services_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE corporate_identity ADD CONSTRAINT FK_E9D4764AEF317FA9 FOREIGN KEY (business_services_id) REFERENCES business_services (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E9D4764AEF317FA9 ON corporate_identity (business_services_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE corporate_identity DROP FOREIGN KEY FK_E9D4764AEF317FA9');
        $this->addSql('DROP INDEX UNIQ_E9D4764AEF317FA9 ON corporate_identity');
        $this->addSql('ALTER TABLE corporate_identity CHANGE business_services_id business_services_id INT NOT NULL');
    }
}
