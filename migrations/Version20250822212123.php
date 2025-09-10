<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250822212123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access_registry DROP FOREIGN KEY FK_A0BDDC3B2DFFA14D');
        $this->addSql('DROP INDEX UNIQ_A0BDDC3B2DFFA14D ON access_registry');
        $this->addSql('ALTER TABLE access_registry DROP business_service_id');
        $this->addSql('ALTER TABLE identity ADD business_service_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE identity ADD CONSTRAINT FK_6A95E9C42DFFA14D FOREIGN KEY (business_service_id) REFERENCES business_services (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6A95E9C42DFFA14D ON identity (business_service_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access_registry ADD business_service_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE access_registry ADD CONSTRAINT FK_A0BDDC3B2DFFA14D FOREIGN KEY (business_service_id) REFERENCES business_services (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A0BDDC3B2DFFA14D ON access_registry (business_service_id)');
        $this->addSql('ALTER TABLE identity DROP FOREIGN KEY FK_6A95E9C42DFFA14D');
        $this->addSql('DROP INDEX UNIQ_6A95E9C42DFFA14D ON identity');
        $this->addSql('ALTER TABLE identity DROP business_service_id');
    }
}
