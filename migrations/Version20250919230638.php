<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250919230638 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE auth_bridge (id INT AUTO_INCREMENT NOT NULL, user_credential VARCHAR(500) DEFAULT NULL, domain_process_id VARCHAR(500) DEFAULT NULL, application_process_id VARCHAR(255) DEFAULT NULL, registration_process_id VARCHAR(255) DEFAULT NULL, remove_process_id VARCHAR(255) DEFAULT NULL, iv VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, secret VARCHAR(500) NOT NULL, applications MEDIUMTEXT DEFAULT NULL, description VARCHAR(5000) DEFAULT NULL, target_id VARCHAR(255) DEFAULT NULL, process_state TINYINT(1) DEFAULT NULL, public_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE auth_bridge');
    }
}
