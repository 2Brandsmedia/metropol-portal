<?php

declare(strict_types=1);

/**
 * Route-Optimizations-Tabelle Migration
 * 
 * @author 2Brands Media GmbH
 */

class CreateRouteOptimizationsTable
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $sql = "CREATE TABLE route_optimizations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            playlist_id INT UNSIGNED NOT NULL,
            original_order JSON NOT NULL COMMENT 'Array der Stop-IDs in Original-Reihenfolge',
            optimized_order JSON NOT NULL COMMENT 'Array der Stop-IDs in optimierter Reihenfolge',
            original_duration INT UNSIGNED NOT NULL COMMENT 'Geschätzte Dauer in Minuten',
            optimized_duration INT UNSIGNED NOT NULL COMMENT 'Optimierte Dauer in Minuten',
            original_distance INT UNSIGNED NOT NULL COMMENT 'Original-Distanz in Metern',
            optimized_distance INT UNSIGNED NOT NULL COMMENT 'Optimierte Distanz in Metern',
            savings_duration INT UNSIGNED GENERATED ALWAYS AS (original_duration - optimized_duration) STORED,
            savings_distance INT UNSIGNED GENERATED ALWAYS AS (original_distance - optimized_distance) STORED,
            savings_percentage DECIMAL(5,2) GENERATED ALWAYS AS ((original_duration - optimized_duration) / original_duration * 100) STORED,
            optimization_type ENUM('distance', 'duration', 'balanced', 'time_windows') NOT NULL DEFAULT 'balanced',
            algorithm_used VARCHAR(50) DEFAULT 'openrouteservice',
            traffic_considered BOOLEAN DEFAULT TRUE,
            constraints_applied JSON NULL COMMENT 'Array der angewendeten Constraints',
            execution_time_ms INT UNSIGNED NULL COMMENT 'Optimierungsdauer in Millisekunden',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
            INDEX idx_playlist (playlist_id),
            INDEX idx_created (created_at),
            INDEX idx_type (optimization_type),
            INDEX idx_savings (savings_percentage)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS route_optimizations");
    }
}