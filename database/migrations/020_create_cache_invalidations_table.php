<?php

declare(strict_types=1);

/**
 * Cache-Invalidierungsprotokoll für Monitoring und Debugging
 * 
 * @author 2Brands Media GmbH
 */

class CreateCacheInvalidationsTable
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $sql = "CREATE TABLE cache_invalidations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cache_key VARCHAR(255) NOT NULL,
            strategy ENUM('traffic_based', 'time_based', 'event_based', 'dependency_based', 'confidence_based', 'manual_traffic', 'manual_admin') NOT NULL,
            reason TEXT NOT NULL,
            invalidated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Kontext-Informationen
            cache_type ENUM('route', 'geocoding', 'traffic', 'matrix', 'autocomplete') NULL,
            cache_age_seconds INT UNSIGNED NULL,
            hit_count_at_invalidation INT UNSIGNED NULL,
            
            -- Performance-Tracking
            replacement_cache_created BOOLEAN DEFAULT FALSE,
            replacement_created_at TIMESTAMP NULL,
            
            INDEX idx_strategy (strategy),
            INDEX idx_invalidated_at (invalidated_at),
            INDEX idx_cache_key (cache_key),
            INDEX idx_cache_type (cache_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
        
        echo "Cache Invalidations Tabelle erfolgreich erstellt\n";
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS cache_invalidations");
    }
}