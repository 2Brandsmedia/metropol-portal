<?php

declare(strict_types=1);

/**
 * Playlist-Templates-Tabelle Migration
 * 
 * @author 2Brands Media GmbH
 */

class CreatePlaylistTemplatesTable
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $sql = "CREATE TABLE playlist_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            stops JSON NOT NULL COMMENT 'Array von Template-Stopps mit client_id, address, work_duration etc.',
            estimated_duration INT UNSIGNED NULL COMMENT 'Geschätzte Gesamtdauer in Minuten',
            estimated_distance INT UNSIGNED NULL COMMENT 'Geschätzte Gesamtdistanz in Metern',
            day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NULL,
            recurring_interval ENUM('weekly', 'biweekly', 'monthly', 'custom') NULL DEFAULT NULL,
            valid_from DATE NULL,
            valid_until DATE NULL,
            max_stops INT UNSIGNED DEFAULT 20,
            tags JSON NULL COMMENT 'Array von Tags für Kategorisierung',
            usage_count INT UNSIGNED DEFAULT 0,
            last_used_at TIMESTAMP NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_name (name),
            INDEX idx_day_of_week (day_of_week),
            INDEX idx_active (is_active),
            INDEX idx_created_by (created_by),
            INDEX idx_usage (usage_count),
            FULLTEXT idx_search (name, description)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS playlist_templates");
    }
}