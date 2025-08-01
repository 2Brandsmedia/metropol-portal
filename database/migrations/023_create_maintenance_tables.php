<?php

declare(strict_types=1);

/**
 * Maintenance-Tabellen Migration
 * 
 * Erstellt zusätzliche Tabellen für das Wartungssystem
 * 
 * @author 2Brands Media GmbH
 */
class CreateMaintenanceTables
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        // Wartungshistorie-Tabelle
        $this->createMaintenanceHistoryTable();
        
        // Wartungszeitpläne-Tabelle
        $this->createMaintenanceSchedulesTable();
        
        // System-Ressourcen-Snapshots
        $this->createResourceSnapshotsTable();
        
        // Backup-Protokoll
        $this->createBackupLogsTable();
    }

    /**
     * Wartungshistorie mit detaillierteren Informationen
     */
    private function createMaintenanceHistoryTable(): void
    {
        $sql = "CREATE TABLE maintenance_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            maintenance_id VARCHAR(64) NOT NULL,
            schedule_type ENUM('hourly', 'daily', 'weekly', 'monthly', 'emergency', 'manual') NOT NULL,
            status ENUM('running', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'running',
            started_at TIMESTAMP NOT NULL,
            completed_at TIMESTAMP NULL,
            duration_seconds DECIMAL(8,2) NULL,
            tasks_executed JSON NULL,
            tasks_failed JSON NULL,
            pre_health_score INT NULL,
            post_health_score INT NULL,
            resources_freed_mb DECIMAL(10,2) DEFAULT 0,
            errors_encountered JSON NULL,
            performance_impact JSON NULL,
            triggered_by ENUM('cron', 'api', 'health_monitor', 'emergency') NOT NULL,
            triggered_by_user INT UNSIGNED NULL,
            notes TEXT NULL,
            
            FOREIGN KEY (triggered_by_user) REFERENCES users(id) ON DELETE SET NULL,
            
            UNIQUE KEY uk_maintenance_id (maintenance_id),
            INDEX idx_schedule_type (schedule_type),
            INDEX idx_status (status),
            INDEX idx_started_at (started_at),
            INDEX idx_health_score (post_health_score),
            INDEX idx_duration (duration_seconds)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    /**
     * Wartungszeitpläne für dynamische Konfiguration
     */
    private function createMaintenanceSchedulesTable(): void
    {
        $sql = "CREATE TABLE maintenance_schedules (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            schedule_name VARCHAR(100) NOT NULL UNIQUE,
            schedule_type ENUM('hourly', 'daily', 'weekly', 'monthly') NOT NULL,
            cron_expression VARCHAR(100) NOT NULL,
            tasks JSON NOT NULL,
            enabled BOOLEAN DEFAULT TRUE,
            priority INT DEFAULT 100,
            max_duration_minutes INT DEFAULT 30,
            health_threshold INT DEFAULT 70,
            conditions JSON NULL,
            notification_settings JSON NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_run TIMESTAMP NULL,
            next_run TIMESTAMP NULL,
            
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            
            INDEX idx_schedule_type (schedule_type),
            INDEX idx_enabled (enabled),
            INDEX idx_next_run (next_run),
            INDEX idx_priority (priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    /**
     * System-Ressourcen-Snapshots für Trend-Analyse
     */
    private function createResourceSnapshotsTable(): void
    {
        $sql = "CREATE TABLE resource_snapshots (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            snapshot_type ENUM('pre_maintenance', 'post_maintenance', 'scheduled', 'alert_triggered') NOT NULL,
            maintenance_id VARCHAR(64) NULL,
            
            -- System-Ressourcen
            memory_usage_mb DECIMAL(10,2) NOT NULL,
            memory_limit_mb DECIMAL(10,2) NULL,
            memory_percentage DECIMAL(5,2) AS (
                CASE WHEN memory_limit_mb > 0 THEN (memory_usage_mb / memory_limit_mb) * 100 ELSE NULL END
            ) STORED,
            
            disk_usage_gb DECIMAL(12,2) NULL,
            disk_total_gb DECIMAL(12,2) NULL,
            disk_percentage DECIMAL(5,2) AS (
                CASE WHEN disk_total_gb > 0 THEN (disk_usage_gb / disk_total_gb) * 100 ELSE NULL END
            ) STORED,
            
            cpu_load_1min DECIMAL(5,2) NULL,
            cpu_load_5min DECIMAL(5,2) NULL,
            cpu_load_15min DECIMAL(5,2) NULL,
            
            -- Datenbank-Metriken
            db_size_mb DECIMAL(12,2) NULL,
            db_connections_active INT NULL,
            db_slow_queries_count INT NULL,
            db_lock_wait_time_ms DECIMAL(8,2) NULL,
            
            -- Anwendungsmetriken
            active_sessions INT NULL,
            cache_hit_ratio DECIMAL(5,2) NULL,
            cache_size_mb DECIMAL(10,2) NULL,
            avg_response_time_ms DECIMAL(8,2) NULL,
            error_rate_percent DECIMAL(5,2) NULL,
            
            -- Performance-Indikatoren
            health_score INT NULL,
            performance_grade ENUM('excellent', 'good', 'warning', 'critical') NULL,
            
            -- Zusätzliche Daten
            metadata JSON NULL,
            captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_snapshot_type (snapshot_type),
            INDEX idx_maintenance_id (maintenance_id),
            INDEX idx_captured_at (captured_at),
            INDEX idx_health_score (health_score),
            INDEX idx_memory_percentage (memory_percentage),
            INDEX idx_disk_percentage (disk_percentage)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    /**
     * Backup-Protokoll für Verfügbarkeit und Integrität
     */
    private function createBackupLogsTable(): void
    {
        $sql = "CREATE TABLE backup_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            backup_type ENUM('database', 'files', 'full_system', 'logs', 'cache') NOT NULL,
            backup_method ENUM('mysqldump', 'tar', 'rsync', 'automated', 'manual') NOT NULL,
            backup_path VARCHAR(500) NULL,
            backup_size_bytes BIGINT NULL,
            backup_size_compressed BIGINT NULL,
            compression_ratio DECIMAL(5,2) AS (
                CASE WHEN backup_size_bytes > 0 AND backup_size_compressed > 0 
                THEN (1 - (backup_size_compressed / backup_size_bytes)) * 100 
                ELSE NULL END
            ) STORED,
            
            status ENUM('started', 'completed', 'failed', 'verifying', 'verified', 'corrupted') NOT NULL,
            
            -- Timing
            started_at TIMESTAMP NOT NULL,
            completed_at TIMESTAMP NULL,
            duration_seconds DECIMAL(8,2) NULL,
            
            -- Verifikation
            checksum_method ENUM('md5', 'sha256', 'crc32') NULL,
            checksum_value VARCHAR(128) NULL,
            verification_status ENUM('pending', 'passed', 'failed', 'skipped') DEFAULT 'pending',
            verification_date TIMESTAMP NULL,
            
            -- Retention
            retention_days INT DEFAULT 30,
            expires_at TIMESTAMP AS (DATE_ADD(completed_at, INTERVAL retention_days DAY)) STORED,
            auto_delete BOOLEAN DEFAULT TRUE,
            
            -- Metadaten
            tables_backed_up JSON NULL,
            files_backed_up JSON NULL,
            error_log TEXT NULL,
            metadata JSON NULL,
            
            -- Auslöser
            triggered_by ENUM('cron', 'maintenance', 'manual', 'pre_update') NOT NULL,
            triggered_by_user INT UNSIGNED NULL,
            maintenance_id VARCHAR(64) NULL,
            
            FOREIGN KEY (triggered_by_user) REFERENCES users(id) ON DELETE SET NULL,
            
            INDEX idx_backup_type (backup_type),
            INDEX idx_status (status),
            INDEX idx_started_at (started_at),
            INDEX idx_verification_status (verification_status),
            INDEX idx_expires_at (expires_at),
            INDEX idx_maintenance_id (maintenance_id),
            INDEX idx_size (backup_size_bytes)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    public function down(): void
    {
        $tables = [
            'backup_logs',
            'resource_snapshots',
            'maintenance_schedules',
            'maintenance_history'
        ];

        foreach ($tables as $table) {
            $this->db->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}