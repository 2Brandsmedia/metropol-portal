<?php

declare(strict_types=1);

/**
 * Clients-Tabelle Migration
 * 
 * @author 2Brands Media GmbH
 */

class CreateClientsTable
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $sql = "CREATE TABLE clients (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            company VARCHAR(255) NULL,
            phone VARCHAR(50) NULL,
            email VARCHAR(255) NULL,
            address TEXT NOT NULL,
            latitude DECIMAL(10, 8) NULL,
            longitude DECIMAL(11, 8) NULL,
            default_work_duration INT UNSIGNED DEFAULT 30 COMMENT 'Standard-Arbeitszeit in Minuten',
            notes TEXT NULL,
            contact_person VARCHAR(255) NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_active (is_active),
            INDEX idx_coordinates (latitude, longitude),
            INDEX idx_name (name),
            INDEX idx_company (company)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS clients");
    }
}