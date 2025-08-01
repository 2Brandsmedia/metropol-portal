<?php

declare(strict_types=1);

/**
 * Stops-Tabelle Migration
 * 
 * @author 2Brands Media GmbH
 */

class CreateStopsTable
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $sql = "CREATE TABLE stops (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            playlist_id INT UNSIGNED NOT NULL,
            position INT UNSIGNED NOT NULL,
            address TEXT NOT NULL,
            latitude DECIMAL(10, 8) NULL,
            longitude DECIMAL(11, 8) NULL,
            work_duration INT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'in Minuten',
            travel_duration INT UNSIGNED DEFAULT 0 COMMENT 'in Minuten zum nächsten Stopp',
            distance INT UNSIGNED DEFAULT 0 COMMENT 'in Metern zum nächsten Stopp',
            status ENUM('pending', 'in_progress', 'completed', 'skipped') NOT NULL DEFAULT 'pending',
            notes TEXT NULL,
            started_at TIMESTAMP NULL DEFAULT NULL,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
            INDEX idx_playlist_position (playlist_id, position),
            INDEX idx_status (status),
            INDEX idx_coordinates (latitude, longitude)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS stops");
    }
}