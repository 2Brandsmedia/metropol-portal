<?php

declare(strict_types=1);

/**
 * Geocache-Tabelle Migration
 * 
 * @author 2Brands Media GmbH
 */

class CreateGeocacheTable
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $sql = "CREATE TABLE geocache (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            address_hash VARCHAR(64) NOT NULL UNIQUE,
            address TEXT NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            provider VARCHAR(50) DEFAULT 'openstreetmap',
            confidence DECIMAL(3, 2) DEFAULT 1.00,
            raw_response JSON NULL,
            hits INT UNSIGNED DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_hash (address_hash),
            INDEX idx_coordinates (latitude, longitude),
            INDEX idx_hits (hits)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS geocache");
    }
}