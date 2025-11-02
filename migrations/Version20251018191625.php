<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251018191625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE access_registry (id INT AUTO_INCREMENT NOT NULL, registration_process_id VARCHAR(500) DEFAULT NULL, registration_state TINYINT(1) NOT NULL, public_id VARCHAR(800) NOT NULL, corporate_id VARCHAR(500) DEFAULT NULL, domain VARCHAR(255) DEFAULT NULL, user_credential VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, iv VARCHAR(32) NOT NULL, application VARCHAR(255) DEFAULT NULL, description VARCHAR(5000) DEFAULT NULL, target_id VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE auth_bridge (id INT AUTO_INCREMENT NOT NULL, user_credential VARCHAR(500) DEFAULT NULL, domain_process_id VARCHAR(500) DEFAULT NULL, application_process_id VARCHAR(255) DEFAULT NULL, registration_process_id VARCHAR(255) DEFAULT NULL, remove_process_id VARCHAR(255) DEFAULT NULL, iv VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, secret VARCHAR(500) NOT NULL, applications MEDIUMTEXT DEFAULT NULL, description VARCHAR(5000) DEFAULT NULL, target_id VARCHAR(255) DEFAULT NULL, process_state TINYINT(1) DEFAULT NULL, public_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE business_services (id INT AUTO_INCREMENT NOT NULL, password_manager TINYINT(1) NOT NULL, biometric TINYINT(1) NOT NULL, basic TINYINT(1) NOT NULL, plus TINYINT(1) NOT NULL, pro TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE corporate_identity (id INT AUTO_INCREMENT NOT NULL, business_services_id INT DEFAULT NULL, corporate_id_key VARCHAR(300) NOT NULL, corporate_id_secret VARCHAR(300) NOT NULL, state VARCHAR(10) NOT NULL, iv VARCHAR(32) NOT NULL, corporate_id VARCHAR(255) NOT NULL, callback_user_login VARCHAR(255) DEFAULT NULL, callback_user_registration VARCHAR(255) DEFAULT NULL, ssl_private_key VARCHAR(5000) NOT NULL, domain VARCHAR(255) DEFAULT NULL, ssl_public_key VARCHAR(5000) DEFAULT NULL, INDEX IDX_E9D4764AEF317FA9 (business_services_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE identity (id INT AUTO_INCREMENT NOT NULL, business_service_id INT DEFAULT NULL, public_id VARCHAR(800) NOT NULL, iv VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, secret VARCHAR(800) NOT NULL, private_id VARCHAR(800) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(255) NOT NULL, privacy_policy TINYINT(1) DEFAULT NULL, fcm_token VARCHAR(500) DEFAULT NULL, UNIQUE INDEX UNIQ_6A95E9C42DFFA14D (business_service_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE restore (id INT AUTO_INCREMENT NOT NULL, pin INT DEFAULT NULL, hash VARCHAR(255) DEFAULT NULL, public_id VARCHAR(500) DEFAULT NULL, private_id VARCHAR(500) DEFAULT NULL, secret VARCHAR(500) DEFAULT NULL, allow TINYINT(1) DEFAULT NULL, created_at DATETIME NOT NULL, iv VARCHAR(32) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_registrated_corporate (id INT AUTO_INCREMENT NOT NULL, public_id VARCHAR(255) NOT NULL, corporate_id VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE corporate_identity ADD CONSTRAINT FK_E9D4764AEF317FA9 FOREIGN KEY (business_services_id) REFERENCES business_services (id)');
        $this->addSql('ALTER TABLE identity ADD CONSTRAINT FK_6A95E9C42DFFA14D FOREIGN KEY (business_service_id) REFERENCES business_services (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE corporate_identity DROP FOREIGN KEY FK_E9D4764AEF317FA9');
        $this->addSql('ALTER TABLE identity DROP FOREIGN KEY FK_6A95E9C42DFFA14D');
        $this->addSql('DROP TABLE access_registry');
        $this->addSql('DROP TABLE auth_bridge');
        $this->addSql('DROP TABLE business_services');
        $this->addSql('DROP TABLE corporate_identity');
        $this->addSql('DROP TABLE identity');
        $this->addSql('DROP TABLE restore');
        $this->addSql('DROP TABLE user_registrated_corporate');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
