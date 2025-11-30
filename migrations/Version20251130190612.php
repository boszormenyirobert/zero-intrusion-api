public function up(Schema $schema): void
{
    // létrehozás csak ha nem létezik
    $this->addSql('CREATE TABLE IF NOT EXISTS access_registry (
        id INT AUTO_INCREMENT NOT NULL,
        registration_process_id VARCHAR(500) DEFAULT NULL,
        registration_state TINYINT(1) NOT NULL,
        public_id VARCHAR(800) NOT NULL,
        corporate_id VARCHAR(500) DEFAULT NULL,
        domain VARCHAR(255) DEFAULT NULL,
        user_credential VARCHAR(500) DEFAULT NULL,
        created_at DATETIME NOT NULL,
        iv VARCHAR(32) NOT NULL,
        application VARCHAR(255) DEFAULT NULL,
        description VARCHAR(5000) DEFAULT NULL,
        target_id VARCHAR(255) NOT NULL,
        PRIMARY KEY(id)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

    $this->addSql('CREATE TABLE IF NOT EXISTS auth_bridge (
        id INT AUTO_INCREMENT NOT NULL,
        user_credential VARCHAR(500) DEFAULT NULL,
        domain_process_id VARCHAR(500) DEFAULT NULL,
        application_process_id VARCHAR(255) DEFAULT NULL,
        registration_process_id VARCHAR(255) DEFAULT NULL,
        remove_process_id VARCHAR(255) DEFAULT NULL,
        iv VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL,
        secret VARCHAR(500) NOT NULL,
        applications MEDIUMTEXT DEFAULT NULL,
        description VARCHAR(5000) DEFAULT NULL,
        target_id VARCHAR(255) DEFAULT NULL,
        process_state TINYINT(1) DEFAULT NULL,
        public_id VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY(id)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

    // ... ismételd meg minden táblára CREATE TABLE IF NOT EXISTS-sel ...

    // Foreign key-ek hozzáadása csak ha létezik a tábla
    $this->addSql('ALTER TABLE corporate_identity ADD CONSTRAINT IF NOT EXISTS FK_E9D4764AEF317FA9 FOREIGN KEY (business_services_id) REFERENCES business_services (id)');
    $this->addSql('ALTER TABLE identity ADD CONSTRAINT IF NOT EXISTS FK_6A95E9C42DFFA14D FOREIGN KEY (business_service_id) REFERENCES business_services (id)');
}
