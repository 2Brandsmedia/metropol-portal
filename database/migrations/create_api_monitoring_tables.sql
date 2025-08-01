-- API Usage Tracking Tabellen für Metropol Portal
-- Entwickelt von 2Brands Media GmbH

-- API Usage Statistics
CREATE TABLE IF NOT EXISTS `api_usage` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `api_provider` varchar(50) NOT NULL COMMENT 'google_maps, nominatim, openrouteservice',
    `endpoint` varchar(100) NOT NULL COMMENT 'z.B. directions, geocoding, search',
    `period_type` enum('hourly', 'daily') NOT NULL,
    `period_key` varchar(20) NOT NULL COMMENT 'YYYY-MM-DD oder YYYY-MM-DD HH:00:00',
    `request_count` int(11) NOT NULL DEFAULT 0,
    `error_count` int(11) NOT NULL DEFAULT 0,
    `total_response_time` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Gesamte Response-Zeit in ms',
    `avg_response_time` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Durchschnittliche Response-Zeit in ms',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_usage_entry` (`api_provider`, `endpoint`, `period_type`, `period_key`),
    INDEX `idx_api_provider` (`api_provider`),
    INDEX `idx_period` (`period_type`, `period_key`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Rate Limiting
CREATE TABLE IF NOT EXISTS `api_rate_limits` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `api_provider` varchar(50) NOT NULL,
    `last_request_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `requests_this_second` int(11) NOT NULL DEFAULT 0,
    `requests_this_minute` int(11) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_provider` (`api_provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Warnings und Alerts
CREATE TABLE IF NOT EXISTS `api_warnings` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `api_provider` varchar(50) NOT NULL,
    `warning_level` enum('yellow', 'red', 'blocked') NOT NULL,
    `daily_requests` int(11) NOT NULL,
    `hourly_requests` int(11) NOT NULL,
    `message` text,
    `email_sent` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_api_level` (`api_provider`, `warning_level`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Error Logs
CREATE TABLE IF NOT EXISTS `api_errors` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `api_provider` varchar(50) NOT NULL,
    `endpoint` varchar(100) NOT NULL,
    `error_message` text NOT NULL,
    `metadata` json DEFAULT NULL,
    `user_id` int(11) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_api_endpoint` (`api_provider`, `endpoint`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Request Metadata (für detaillierte Analyse)
CREATE TABLE IF NOT EXISTS `api_request_metadata` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `api_provider` varchar(50) NOT NULL,
    `endpoint` varchar(100) NOT NULL,
    `metadata` json DEFAULT NULL COMMENT 'Response-Size, Params, etc.',
    `response_time` decimal(6,2) DEFAULT NULL,
    `user_id` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_api_endpoint` (`api_provider`, `endpoint`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_response_time` (`response_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Route History (für Prefetching)
CREATE TABLE IF NOT EXISTS `route_history` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `origin_address` varchar(255) NOT NULL,
    `destination_address` varchar(255) NOT NULL,
    `usage_count` int(11) NOT NULL DEFAULT 1,
    `last_used_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_route` (`user_id`, `origin_address`, `destination_address`),
    INDEX `idx_user_usage` (`user_id`, `usage_count` DESC),
    INDEX `idx_last_used` (`last_used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Address History (für Prefetching)
CREATE TABLE IF NOT EXISTS `address_history` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `address` varchar(255) NOT NULL,
    `usage_count` int(11) NOT NULL DEFAULT 1,
    `last_used_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_address` (`user_id`, `address`),
    INDEX `idx_user_usage` (`user_id`, `usage_count` DESC),
    INDEX `idx_last_used` (`last_used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache Tabelle erweitern (falls noch nicht vorhanden)
CREATE TABLE IF NOT EXISTS `cache` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `cache_key` varchar(255) NOT NULL,
    `data` longtext NOT NULL,
    `expires_at` timestamp NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_cache_key` (`cache_key`),
    INDEX `idx_expires_at` (`expires_at`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Log erweitern (falls noch nicht vorhanden)
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `action` varchar(100) NOT NULL,
    `entity_type` varchar(50) NOT NULL,
    `entity_id` int(11) NOT NULL,
    `old_values` json DEFAULT NULL,
    `new_values` json DEFAULT NULL,
    `user_id` int(11) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` varchar(500) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trigger für automatische Cleanup alter Daten
DELIMITER $$

CREATE EVENT IF NOT EXISTS `cleanup_api_usage`
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- Alte API Usage Daten löschen (älter als 30 Tage)
    DELETE FROM api_usage 
    WHERE period_type = 'daily' 
    AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Alte hourly Daten löschen (älter als 7 Tage)
    DELETE FROM api_usage 
    WHERE period_type = 'hourly' 
    AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    -- Alte API Errors löschen (älter als 14 Tage)
    DELETE FROM api_errors 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 14 DAY);
    
    -- Alte API Metadata löschen (älter als 7 Tage)
    DELETE FROM api_request_metadata 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    -- Alte Warnings löschen (älter als 7 Tage)
    DELETE FROM api_warnings 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    -- Abgelaufene Cache-Einträge löschen
    DELETE FROM cache 
    WHERE expires_at < NOW();
    
    -- Alte Audit-Logs löschen (älter als 90 Tage)
    DELETE FROM audit_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
END$$

DELIMITER ;

-- Initial-Daten für API-Limits (Beispielkonfiguration)
INSERT IGNORE INTO `cache` (`cache_key`, `data`, `expires_at`) VALUES
('api_limits_config', '{
    "google_maps": {
        "daily": 25000,
        "hourly": 2500,
        "per_second": 50,
        "cost_per_request": 0.005
    },
    "nominatim": {
        "daily": 86400,
        "hourly": 3600,
        "per_second": 1,
        "cost_per_request": 0.0
    },
    "openrouteservice": {
        "daily": 2000,
        "hourly": 500,
        "per_second": 5,
        "cost_per_request": 0.0
    }
}', DATE_ADD(NOW(), INTERVAL 1 YEAR));

-- Views für bessere Performance
CREATE OR REPLACE VIEW `api_usage_today` AS
SELECT 
    api_provider,
    endpoint,
    SUM(request_count) as total_requests,
    SUM(error_count) as total_errors,
    AVG(avg_response_time) as avg_response_time,
    MIN(created_at) as first_request,
    MAX(updated_at) as last_request
FROM api_usage 
WHERE period_type = 'daily' 
AND period_key = CURDATE()
GROUP BY api_provider, endpoint;

CREATE OR REPLACE VIEW `api_usage_current_hour` AS
SELECT 
    api_provider,
    endpoint,
    SUM(request_count) as total_requests,
    SUM(error_count) as total_errors,
    AVG(avg_response_time) as avg_response_time
FROM api_usage 
WHERE period_type = 'hourly' 
AND period_key = DATE_FORMAT(NOW(), '%Y-%m-%d %H:00:00')
GROUP BY api_provider, endpoint;

-- Indizes für bessere Performance
CREATE INDEX IF NOT EXISTS `idx_geocache_hits` ON `geocache` (`hits` DESC);
CREATE INDEX IF NOT EXISTS `idx_geocache_created` ON `geocache` (`created_at`);

-- Kommentare für Dokumentation
ALTER TABLE `api_usage` COMMENT = 'Speichert API-Nutzungsstatistiken für Monitoring und Limit-Überwachung';
ALTER TABLE `api_warnings` COMMENT = 'Protokolliert API-Limit-Warnungen und gesendete Benachrichtigungen';
ALTER TABLE `api_errors` COMMENT = 'Detaillierte API-Fehlerprotokolle für Debugging und Monitoring';
ALTER TABLE `route_history` COMMENT = 'Benutzer-Routenhistorie für intelligentes Prefetching';
ALTER TABLE `address_history` COMMENT = 'Benutzer-Adresshistorie für intelligentes Prefetching';

-- Erfolgsmeldung
SELECT 'API Monitoring Tabellen erfolgreich erstellt!' as message;