<?php

declare(strict_types=1);

/**
 * Migration: API Limit Monitoring Tables
 * 
 * Erstellt alle notwendigen Tabellen für das erweiterte API-Limit-Monitoring-System
 * 
 * @author 2Brands Media GmbH
 */

return [
    'up' => function($db) {
        // API Fallback Log - Tracking erfolgreicher Fallbacks
        $db->query("
            CREATE TABLE IF NOT EXISTS api_fallback_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                original_provider VARCHAR(50) NOT NULL,
                fallback_provider VARCHAR(50) NOT NULL,
                request_type VARCHAR(100) NOT NULL,
                success BOOLEAN NOT NULL DEFAULT 0,
                response_time FLOAT NULL,
                metadata JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_original_provider (original_provider),
                INDEX idx_fallback_provider (fallback_provider),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // API Monitor Stats - Tägliche Monitoring-Statistiken
        $db->query("
            CREATE TABLE IF NOT EXISTS api_monitor_stats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                stats_date DATE NOT NULL UNIQUE,
                stats_data JSON NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_stats_date (stats_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // API Reports - Gespeicherte Berichte
        $db->query("
            CREATE TABLE IF NOT EXISTS api_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                report_type ENUM('daily', 'weekly', 'monthly', 'custom') NOT NULL,
                report_period VARCHAR(20) NOT NULL,
                report_data JSON NOT NULL,
                generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_report_type_period (report_type, report_period),
                INDEX idx_generated_at (generated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // API Limit Changes - Historie der Limit-Änderungen
        $db->query("
            CREATE TABLE IF NOT EXISTS api_limit_changes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_provider VARCHAR(50) NOT NULL,
                change_type ENUM('automatic', 'manual', 'emergency') NOT NULL,
                old_limits JSON NOT NULL,
                new_limits JSON NOT NULL,
                confidence_score FLOAT NULL,
                detection_method VARCHAR(100) NULL,
                approved_by INT NULL,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_api_provider (api_provider),
                INDEX idx_change_type (change_type),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // API Budget Alerts - Budget-Überschreitungs-Tracking
        $db->query("
            CREATE TABLE IF NOT EXISTS api_budget_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_provider VARCHAR(50) NOT NULL,
                alert_level ENUM('warning', 'critical', 'emergency') NOT NULL,
                current_cost DECIMAL(10,4) NOT NULL,
                budget_limit DECIMAL(10,4) NOT NULL,
                percentage_used DECIMAL(5,2) NOT NULL,
                alert_data JSON NULL,
                resolved_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_api_provider (api_provider),
                INDEX idx_alert_level (alert_level),
                INDEX idx_created_at (created_at),
                INDEX idx_resolved (resolved_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // API Health Checks - Regelmäßige Gesundheitsprüfungen
        $db->query("
            CREATE TABLE IF NOT EXISTS api_health_checks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_provider VARCHAR(50) NOT NULL,
                check_type ENUM('availability', 'performance', 'quota', 'pricing') NOT NULL,
                status ENUM('healthy', 'degraded', 'unhealthy', 'unknown') NOT NULL,
                response_time FLOAT NULL,
                error_rate FLOAT NULL,
                availability_percentage FLOAT NULL,
                check_data JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_api_provider (api_provider),
                INDEX idx_check_type (check_type),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // API Capacity Predictions - Kapazitätsvorhersagen
        $db->query("
            CREATE TABLE IF NOT EXISTS api_capacity_predictions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_provider VARCHAR(50) NOT NULL,
                prediction_date DATE NOT NULL,
                current_trend ENUM('increasing', 'decreasing', 'stable') NOT NULL,
                predicted_daily_usage INT NOT NULL,
                days_until_limit INT NOT NULL,
                confidence_level FLOAT NOT NULL,
                recommendation TEXT NULL,
                prediction_data JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_api_provider (api_provider),
                INDEX idx_prediction_date (prediction_date),
                INDEX idx_days_until_limit (days_until_limit)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // API Cost Optimizations - Kosteneinsparungsempfehlungen
        $db->query("
            CREATE TABLE IF NOT EXISTS api_cost_optimizations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_provider VARCHAR(50) NOT NULL,
                optimization_type ENUM('caching', 'rate_limiting', 'fallback', 'usage_pattern') NOT NULL,
                current_cost DECIMAL(10,4) NOT NULL,
                potential_savings DECIMAL(10,4) NOT NULL,
                implementation_effort ENUM('low', 'medium', 'high') NOT NULL,
                priority ENUM('low', 'medium', 'high', 'critical') NOT NULL,
                description TEXT NOT NULL,
                implementation_guide TEXT NULL,
                implemented BOOLEAN DEFAULT 0,
                implemented_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_api_provider (api_provider),
                INDEX idx_optimization_type (optimization_type),
                INDEX idx_priority (priority),
                INDEX idx_implemented (implemented)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // API Quota Notifications - Benachrichtigungen über Quota-Änderungen
        $db->query("
            CREATE TABLE IF NOT EXISTS api_quota_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_provider VARCHAR(50) NOT NULL,
                notification_type ENUM('quota_increase', 'quota_decrease', 'pricing_change', 'service_change') NOT NULL,
                old_value JSON NULL,
                new_value JSON NULL,
                source VARCHAR(100) NOT NULL,
                confidence_score FLOAT NOT NULL,
                verified BOOLEAN DEFAULT 0,
                verified_by INT NULL,
                verified_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_api_provider (api_provider),
                INDEX idx_notification_type (notification_type),
                INDEX idx_verified (verified),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // API Emergency Actions - Notfallmaßnahmen-Log
        $db->query("
            CREATE TABLE IF NOT EXISTS api_emergency_actions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_provider VARCHAR(50) NOT NULL,
                trigger_event ENUM('budget_exceeded', 'quota_exceeded', 'service_unavailable', 'manual') NOT NULL,
                action_type ENUM('limit_reduction', 'service_block', 'fallback_activation', 'cache_only') NOT NULL,
                action_data JSON NOT NULL,
                triggered_by ENUM('system', 'user', 'external') NOT NULL,
                user_id INT NULL,
                resolved BOOLEAN DEFAULT 0,
                resolved_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_api_provider (api_provider),
                INDEX idx_trigger_event (trigger_event),
                INDEX idx_action_type (action_type),
                INDEX idx_resolved (resolved),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // API Performance Baselines - Performance-Referenzwerte
        $db->query("
            CREATE TABLE IF NOT EXISTS api_performance_baselines (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_provider VARCHAR(50) NOT NULL,
                endpoint VARCHAR(200) NOT NULL,
                baseline_date DATE NOT NULL,
                avg_response_time FLOAT NOT NULL,
                p95_response_time FLOAT NOT NULL,
                p99_response_time FLOAT NOT NULL,
                error_rate FLOAT NOT NULL,
                throughput FLOAT NOT NULL,
                sample_size INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_baseline (api_provider, endpoint, baseline_date),
                INDEX idx_api_provider (api_provider),
                INDEX idx_endpoint (endpoint),
                INDEX idx_baseline_date (baseline_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "API Limit Monitoring tables created successfully.\n";
        
        // Insert default monitoring configuration
        $db->query("
            INSERT IGNORE INTO api_monitor_stats (stats_date, stats_data) VALUES (
                CURDATE(), 
                JSON_OBJECT(
                    'initialization', true,
                    'timestamp', NOW(),
                    'tables_created', 10,
                    'monitoring_active', true
                )
            )
        ");

        echo "Default monitoring configuration inserted.\n";
    },

    'down' => function($db) {
        // Drop tables in reverse order to handle foreign key constraints
        $tables = [
            'api_performance_baselines',
            'api_emergency_actions',
            'api_quota_notifications',
            'api_cost_optimizations',
            'api_capacity_predictions',
            'api_health_checks',
            'api_budget_alerts',
            'api_limit_changes',
            'api_reports',
            'api_monitor_stats',
            'api_fallback_log'
        ];

        foreach ($tables as $table) {
            $db->query("DROP TABLE IF EXISTS {$table}");
            echo "Dropped table: {$table}\n";
        }

        echo "API Limit Monitoring tables dropped successfully.\n";
    }
];