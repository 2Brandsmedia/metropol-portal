<?php

declare(strict_types=1);

/**
 * Playlists-Tabelle Migration
 * 
 * @author 2Brands Media GmbH
 */

class CreatePlaylistsTable
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $sql = "CREATE TABLE playlists (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            date DATE NOT NULL,
            name VARCHAR(255) NOT NULL,
            status ENUM('draft', 'active', 'completed') NOT NULL DEFAULT 'draft',
            total_stops INT UNSIGNED DEFAULT 0,
            completed_stops INT UNSIGNED DEFAULT 0,
            estimated_duration INT UNSIGNED DEFAULT 0 COMMENT 'in Minuten',
            actual_duration INT UNSIGNED DEFAULT 0 COMMENT 'in Minuten',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_date (user_id, date),
            INDEX idx_date (date),
            INDEX idx_status (status),
            UNIQUE KEY unique_user_date (user_id, date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS playlists");
    }
}