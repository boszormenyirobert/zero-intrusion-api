<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502161000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expand identity.nfc_encryption_key to varchar(50) to fit encrypted NFC keys';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on mysql.'
        );

        $this->addSql('ALTER TABLE identity MODIFY nfc_encryption_key VARCHAR(50) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on mysql.'
        );

        $this->addSql('ALTER TABLE identity MODIFY nfc_encryption_key VARCHAR(30) NOT NULL');
    }
}
