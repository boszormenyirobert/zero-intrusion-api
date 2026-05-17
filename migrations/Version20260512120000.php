<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_registered_corporate table for tracking corporate registrations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_registered_corporate (id INT AUTO_INCREMENT NOT NULL, public_id VARCHAR(255) NOT NULL, corporate_id VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_registered_corporate');
    }
}
