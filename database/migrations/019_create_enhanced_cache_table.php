<?php

declare(strict_types=1);

/**
 * Erweiterte Cache-Tabelle für Multi-Layer Caching
 * 
 * Ersetzt die einfache cache-Tabelle durch ein intelligentes System mit:
 * - Layer-Support (Memory, Database, Browser)
 * - Hit/Miss Tracking
 * - Cache-Warming Support  
 * - Predictive Caching
 * 
 * @author 2Brands Media GmbH
 */

class CreateEnhancedCacheTable
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        // Erweiterte Cache-Tabelle
        $sql = "CREATE TABLE enhanced_cache (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cache_key VARCHAR(255) NOT NULL,
            cache_layer ENUM('memory', 'database', 'browser', 'shared') NOT NULL DEFAULT 'database',
            cache_type ENUM('route', 'geocoding', 'traffic', 'matrix', 'autocomplete') NOT NULL,
            data LONGTEXT NOT NULL,
            metadata JSON NULL COMMENT 'Zusätzliche Metadaten wie Konfidenz, Provider, etc.',
            
            -- TTL und Invalidierung
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            ttl_seconds INT UNSIGNED NOT NULL,
            last_accessed_at TIMESTAMP NULL,
            
            -- Performance Tracking
            hit_count INT UNSIGNED DEFAULT 0,
            miss_count INT UNSIGNED DEFAULT 0,
            hit_rate DECIMAL(5,2) GENERATED ALWAYS AS (
                CASE 
                    WHEN (hit_count + miss_count) > 0 
                    THEN (hit_count * 100.0) / (hit_count + miss_count)
                    ELSE 0 
                END
            ) STORED,
            
            -- Warming und Prediction
            warming_priority TINYINT UNSIGNED DEFAULT 0 COMMENT '0=normal, 1-10=warming priority',
            prediction_score DECIMAL(3,2) DEFAULT 0.00 COMMENT 'Wahrscheinlichkeit für zukünftige Nutzung',
            usage_pattern JSON NULL COMMENT 'Nutzungsmuster für Predictive Caching',
            
            -- Invalidierung
            invalidation_tags JSON NULL COMMENT 'Tags für gruppenweise Invalidierung',
            parent_keys JSON NULL COMMENT 'Abhängige Cache-Keys',
            
            -- Größe und Kosten
            data_size_bytes INT UNSIGNED GENERATED ALWAYS AS (LENGTH(data)) STORED,
            api_cost DECIMAL(10,4) DEFAULT 0.0000 COMMENT 'Kosten des ursprünglichen API-Aufrufs',
            
            -- Indizes
            UNIQUE KEY uk_cache_key_layer (cache_key, cache_layer),
            INDEX idx_expires_at (expires_at),
            INDEX idx_cache_type_layer (cache_type, cache_layer),
            INDEX idx_hit_rate (hit_rate DESC),
            INDEX idx_warming_priority (warming_priority DESC),
            INDEX idx_prediction_score (prediction_score DESC),
            INDEX idx_last_accessed (last_accessed_at),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);

        // Cache-Statistiken Tabelle
        $sql2 = "CREATE TABLE cache_stats (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            date_key DATE NOT NULL,
            cache_type ENUM('route', 'geocoding', 'traffic', 'matrix', 'autocomplete') NOT NULL,
            cache_layer ENUM('memory', 'database', 'browser', 'shared') NOT NULL,
            
            -- Performance Metriken
            total_requests INT UNSIGNED DEFAULT 0,
            cache_hits INT UNSIGNED DEFAULT 0,
            cache_misses INT UNSIGNED DEFAULT 0,
            hit_rate DECIMAL(5,2) GENERATED ALWAYS AS (
                CASE 
                    WHEN total_requests > 0 
                    THEN (cache_hits * 100.0) / total_requests
                    ELSE 0 
                END
            ) STORED,
            
            -- Antwortzeiten
            avg_hit_response_time_ms DECIMAL(8,2) DEFAULT 0.00,
            avg_miss_response_time_ms DECIMAL(8,2) DEFAULT 0.00,
            
            -- Speicher und Kosten
            total_data_size_mb DECIMAL(10,2) DEFAULT 0.00,
            api_calls_saved INT UNSIGNED DEFAULT 0,
            estimated_cost_saved DECIMAL(10,4) DEFAULT 0.0000,
            
            -- Warming Statistiken
            warming_requests INT UNSIGNED DEFAULT 0,
            prediction_accuracy DECIMAL(5,2) DEFAULT 0.00,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY uk_date_type_layer (date_key, cache_type, cache_layer),
            INDEX idx_date_key (date_key),
            INDEX idx_hit_rate (hit_rate DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql2);

        // Cache-Warming Queue
        $sql3 = "CREATE TABLE cache_warming_queue (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cache_key VARCHAR(255) NOT NULL,
            cache_type ENUM('route', 'geocoding', 'traffic', 'matrix', 'autocomplete') NOT NULL,
            priority TINYINT UNSIGNED DEFAULT 5 COMMENT '1=highest, 10=lowest',
            
            -- Request Details
            request_data JSON NOT NULL COMMENT 'Parameter für den API-Aufruf',
            estimated_cost DECIMAL(10,4) DEFAULT 0.0000,
            expected_usage_count INT UNSIGNED DEFAULT 1,
            
            -- Scheduling
            scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            execute_after TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            attempts INT UNSIGNED DEFAULT 0,
            max_attempts INT UNSIGNED DEFAULT 3,
            
            -- Status
            status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            error_message TEXT NULL,
            processed_at TIMESTAMP NULL,
            
            -- Abhängigkeiten
            depends_on_ids JSON NULL COMMENT 'IDs anderer Warming-Jobs die zuerst fertig sein müssen',
            
            INDEX idx_status_priority (status, priority),
            INDEX idx_execute_after (execute_after),
            INDEX idx_cache_type (cache_type),
            INDEX idx_scheduled_at (scheduled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql3);

        // Events für automatische Wartung
        $this->db->exec("
            CREATE EVENT IF NOT EXISTS clean_expired_enhanced_cache
            ON SCHEDULE EVERY 30 MINUTE
            DO BEGIN
                -- Abgelaufene Einträge löschen
                DELETE FROM enhanced_cache WHERE expires_at < NOW();
                
                -- Tägliche Statistiken aktualisieren
                REPLACE INTO cache_stats (
                    date_key, cache_type, cache_layer, total_requests, 
                    cache_hits, cache_misses, avg_hit_response_time_ms
                )
                SELECT 
                    CURDATE(),
                    cache_type,
                    cache_layer,
                    SUM(hit_count + miss_count),
                    SUM(hit_count),
                    SUM(miss_count),
                    AVG(CASE WHEN hit_count > 0 THEN 50.0 ELSE NULL END)
                FROM enhanced_cache 
                WHERE DATE(created_at) = CURDATE()
                GROUP BY cache_type, cache_layer;
            END
        ");

        $this->db->exec("
            CREATE EVENT IF NOT EXISTS process_cache_warming_queue
            ON SCHEDULE EVERY 5 MINUTE
            DO BEGIN
                -- Nur während Wartungsfenster (nachts) oder bei hoher Priorität
                IF HOUR(NOW()) BETWEEN 2 AND 5 OR EXISTS(
                    SELECT 1 FROM cache_warming_queue 
                    WHERE status = 'pending' AND priority <= 3
                ) THEN
                    -- Queue-Status für Verarbeitung markieren
                    UPDATE cache_warming_queue 
                    SET status = 'processing' 
                    WHERE status = 'pending' 
                      AND execute_after <= NOW() 
                      AND attempts < max_attempts
                    LIMIT 10;
                END IF;
            END
        ");

        // Trigger für Hit-Tracking
        $this->db->exec("
            CREATE TRIGGER update_cache_hit_stats
            AFTER UPDATE ON enhanced_cache
            FOR EACH ROW
            BEGIN
                IF NEW.hit_count > OLD.hit_count THEN
                    UPDATE cache_stats 
                    SET cache_hits = cache_hits + (NEW.hit_count - OLD.hit_count),
                        total_requests = total_requests + (NEW.hit_count - OLD.hit_count)
                    WHERE date_key = CURDATE() 
                      AND cache_type = NEW.cache_type 
                      AND cache_layer = NEW.cache_layer;
                END IF;
                
                IF NEW.miss_count > OLD.miss_count THEN
                    UPDATE cache_stats 
                    SET cache_misses = cache_misses + (NEW.miss_count - OLD.miss_count),
                        total_requests = total_requests + (NEW.miss_count - OLD.miss_count)
                    WHERE date_key = CURDATE() 
                      AND cache_type = NEW.cache_type 
                      AND cache_layer = NEW.cache_layer;
                END IF;
            END
        ");

        echo "Enhanced Cache System erfolgreich erstellt\n";
    }

    public function down(): void
    {
        $this->db->exec("DROP TRIGGER IF EXISTS update_cache_hit_stats");
        $this->db->exec("DROP EVENT IF EXISTS process_cache_warming_queue");
        $this->db->exec("DROP EVENT IF EXISTS clean_expired_enhanced_cache");
        $this->db->exec("DROP TABLE IF EXISTS cache_warming_queue");
        $this->db->exec("DROP TABLE IF EXISTS cache_stats");
        $this->db->exec("DROP TABLE IF EXISTS enhanced_cache");
    }
}