<?php

declare(strict_types=1);

/**
 * Monitoring-Tabellen Migration für umfassendes System-Monitoring
 * 
 * @author 2Brands Media GmbH
 */
class CreateMonitoringTables
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        // System Metriken Tabelle
        $this->createSystemMetricsTable();
        
        // Error Logs Tabelle
        $this->createErrorLogsTable();
        
        // Performance Metriken Tabelle
        $this->createPerformanceMetricsTable();
        
        // Alert Konfiguration Tabelle
        $this->createAlertsTable();
        
        // Alert Logs Tabelle
        $this->createAlertLogsTable();
        
        // API Monitoring Tabelle
        $this->createApiMonitoringTable();
        
        // Database Query Performance Tabelle
        $this->createQueryPerformanceTable();
    }

    /**
     * System-Ressourcen Monitoring (CPU, Memory, Disk)
     */
    private function createSystemMetricsTable(): void
    {
        $sql = "CREATE TABLE system_metrics (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            metric_type ENUM('cpu', 'memory', 'disk', 'network', 'load') NOT NULL,
            value DECIMAL(10,4) NOT NULL,
            percentage DECIMAL(5,2) NULL,
            threshold_warning DECIMAL(5,2) DEFAULT 80.00,
            threshold_critical DECIMAL(5,2) DEFAULT 90.00,
            status ENUM('normal', 'warning', 'critical') DEFAULT 'normal',
            server_info JSON NULL,
            measured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_metric_type (metric_type),
            INDEX idx_status (status),
            INDEX idx_measured_at (measured_at),
            INDEX idx_type_time (metric_type, measured_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    /**
     * Umfassendes Error-Logging
     */
    private function createErrorLogsTable(): void
    {
        $sql = "CREATE TABLE error_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            severity ENUM('emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug') NOT NULL,
            error_type VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            file_path VARCHAR(500) NULL,
            line_number INT NULL,
            trace JSON NULL,
            context JSON NULL,
            user_id INT UNSIGNED NULL,
            session_id VARCHAR(128) NULL,
            request_id VARCHAR(64) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            url VARCHAR(1000) NULL,
            http_method VARCHAR(10) NULL,
            response_time_ms INT NULL,
            memory_usage_mb DECIMAL(8,2) NULL,
            error_count INT DEFAULT 1,
            first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL,
            resolved_by INT UNSIGNED NULL,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
            
            INDEX idx_severity (severity),
            INDEX idx_error_type (error_type),
            INDEX idx_user_id (user_id),
            INDEX idx_first_seen (first_seen),
            INDEX idx_last_seen (last_seen),
            INDEX idx_resolved (resolved_at),
            INDEX idx_severity_time (severity, first_seen),
            FULLTEXT idx_message (message)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    /**
     * Performance-Metriken für Response-Zeiten
     */
    private function createPerformanceMetricsTable(): void
    {
        $sql = "CREATE TABLE performance_metrics (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            endpoint VARCHAR(200) NOT NULL,
            http_method VARCHAR(10) NOT NULL,
            response_time_ms INT NOT NULL,
            memory_usage_mb DECIMAL(8,2) NULL,
            cpu_usage_percent DECIMAL(5,2) NULL,
            db_queries_count INT DEFAULT 0,
            db_query_time_ms INT DEFAULT 0,
            cache_hits INT DEFAULT 0,
            cache_misses INT DEFAULT 0,
            status_code INT NOT NULL,
            user_id INT UNSIGNED NULL,
            session_id VARCHAR(128) NULL,
            request_id VARCHAR(64) NULL,
            ip_address VARCHAR(45) NULL,
            payload_size_bytes INT NULL,
            response_size_bytes INT NULL,
            target_response_time INT NOT NULL DEFAULT 200,
            performance_grade ENUM('excellent', 'good', 'warning', 'critical') AS (
                CASE 
                    WHEN response_time_ms <= target_response_time * 0.5 THEN 'excellent'
                    WHEN response_time_ms <= target_response_time THEN 'good'
                    WHEN response_time_ms <= target_response_time * 1.5 THEN 'warning'
                    ELSE 'critical'
                END
            ) STORED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            
            INDEX idx_endpoint (endpoint),
            INDEX idx_response_time (response_time_ms),
            INDEX idx_status_code (status_code),
            INDEX idx_created_at (created_at),
            INDEX idx_performance_grade (performance_grade),
            INDEX idx_endpoint_time (endpoint, created_at),
            INDEX idx_slow_queries (response_time_ms, created_at) 
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    /**
     * Alert-Konfiguration
     */
    private function createAlertsTable(): void
    {
        $sql = "CREATE TABLE alerts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT NULL,
            alert_type ENUM('performance', 'error', 'security', 'system', 'business') NOT NULL,
            metric_type VARCHAR(50) NOT NULL,
            condition_operator ENUM('gt', 'gte', 'lt', 'lte', 'eq', 'neq') NOT NULL,
            threshold_value DECIMAL(15,4) NOT NULL,
            time_window_minutes INT DEFAULT 5,
            evaluation_frequency_minutes INT DEFAULT 1,
            severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
            enabled BOOLEAN DEFAULT TRUE,
            notification_channels JSON NOT NULL DEFAULT '[]',
            escalation_rules JSON NULL,
            suppression_rules JSON NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            
            INDEX idx_alert_type (alert_type),
            INDEX idx_enabled (enabled),
            INDEX idx_severity (severity),
            INDEX idx_metric_type (metric_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    /**
     * Alert-Logs für ausgelöste Benachrichtigungen
     */
    private function createAlertLogsTable(): void
    {
        $sql = "CREATE TABLE alert_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            alert_id INT UNSIGNED NOT NULL,
            alert_name VARCHAR(100) NOT NULL,
            severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
            trigger_value DECIMAL(15,4) NOT NULL,
            threshold_value DECIMAL(15,4) NOT NULL,
            message TEXT NOT NULL,
            context JSON NULL,
            notification_sent BOOLEAN DEFAULT FALSE,
            notification_channels JSON NULL,
            notification_errors JSON NULL,
            resolved_at TIMESTAMP NULL,
            resolved_by INT UNSIGNED NULL,
            resolution_notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE,
            FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
            
            INDEX idx_alert_id (alert_id),
            INDEX idx_severity (severity),
            INDEX idx_created_at (created_at),
            INDEX idx_resolved (resolved_at),
            INDEX idx_unresolved (resolved_at) WHERE resolved_at IS NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    /**
     * API-spezifisches Monitoring
     */
    private function createApiMonitoringTable(): void
    {
        $sql = "CREATE TABLE api_monitoring (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            endpoint VARCHAR(200) NOT NULL,
            http_method VARCHAR(10) NOT NULL,
            api_version VARCHAR(20) NULL,
            request_count INT DEFAULT 1,
            success_count INT DEFAULT 0,
            error_count INT DEFAULT 0,
            avg_response_time_ms DECIMAL(8,2) NOT NULL,
            min_response_time_ms INT NOT NULL,
            max_response_time_ms INT NOT NULL,
            p95_response_time_ms INT NULL,
            p99_response_time_ms INT NULL,
            total_bytes_transferred BIGINT DEFAULT 0,
            rate_limit_hits INT DEFAULT 0,
            unique_users INT DEFAULT 0,
            peak_concurrent_requests INT DEFAULT 0,
            hour_bucket DATETIME NOT NULL,
            
            UNIQUE KEY uk_endpoint_hour (endpoint, http_method, hour_bucket),
            INDEX idx_endpoint (endpoint),
            INDEX idx_hour_bucket (hour_bucket),
            INDEX idx_avg_response_time (avg_response_time_ms),
            INDEX idx_error_rate (error_count, request_count)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    /**
     * Datenbank-Query Performance Monitoring
     */
    private function createQueryPerformanceTable(): void
    {
        $sql = "CREATE TABLE query_performance (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            query_hash VARCHAR(64) NOT NULL,
            query_type ENUM('SELECT', 'INSERT', 'UPDATE', 'DELETE', 'OTHER') NOT NULL,
            table_name VARCHAR(100) NULL,
            execution_time_ms DECIMAL(8,3) NOT NULL,
            rows_examined BIGINT NULL,
            rows_affected BIGINT NULL,
            query_text MEDIUMTEXT NULL,
            execution_plan JSON NULL,
            called_from VARCHAR(200) NULL,
            user_id INT UNSIGNED NULL,
            session_id VARCHAR(128) NULL,
            is_slow BOOLEAN AS (execution_time_ms > 100) STORED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            
            INDEX idx_query_hash (query_hash),
            INDEX idx_query_type (query_type),
            INDEX idx_table_name (table_name),
            INDEX idx_execution_time (execution_time_ms),
            INDEX idx_is_slow (is_slow),
            INDEX idx_created_at (created_at),
            INDEX idx_slow_queries (is_slow, created_at) WHERE is_slow = TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    public function down(): void
    {
        $tables = [
            'query_performance',
            'api_monitoring', 
            'alert_logs',
            'alerts',
            'performance_metrics',
            'error_logs',
            'system_metrics'
        ];

        foreach ($tables as $table) {
            $this->db->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}