-- Metropol Portal - Vollständige Datenbank-Migration
-- @author 2Brands Media GmbH
-- Für All-Inkl MySQL 8.0

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- Tabelle: users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'employee') DEFAULT 'employee',
  `is_active` BOOLEAN DEFAULT TRUE,
  `phone` VARCHAR(20) NULL,
  `address` TEXT NULL,
  `avatar` VARCHAR(255) NULL,
  `language` VARCHAR(5) DEFAULT 'de',
  `theme` VARCHAR(20) DEFAULT 'light',
  `last_login_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`),
  INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: playlists
CREATE TABLE IF NOT EXISTS `playlists` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `date` DATE NOT NULL,
  `status` ENUM('draft', 'active', 'completed') DEFAULT 'draft',
  `route_data` JSON NULL,
  `total_distance` INT DEFAULT 0,
  `total_duration` INT DEFAULT 0,
  `total_duration_in_traffic` INT NULL,
  `traffic_data` JSON NULL,
  `using_google_maps` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_date` (`date`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: stops
CREATE TABLE IF NOT EXISTS `stops` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `playlist_id` INT UNSIGNED NOT NULL,
  `position` INT NOT NULL,
  `address` TEXT NOT NULL,
  `latitude` DECIMAL(10, 8) NULL,
  `longitude` DECIMAL(11, 8) NULL,
  `work_duration` INT DEFAULT 30,
  `status` ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
  `notes` TEXT NULL,
  `client_name` VARCHAR(255) NULL,
  `client_phone` VARCHAR(50) NULL,
  `arrival_time` TIME NULL,
  `departure_time` TIME NULL,
  `completed_at` TIMESTAMP NULL,
  `distance_to_next` INT NULL,
  `duration_to_next` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`playlist_id`) REFERENCES `playlists`(`id`) ON DELETE CASCADE,
  INDEX `idx_playlist_id` (`playlist_id`),
  INDEX `idx_position` (`position`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: geocache
CREATE TABLE IF NOT EXISTS `geocache` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `address_hash` VARCHAR(64) NOT NULL UNIQUE,
  `address` TEXT NOT NULL,
  `latitude` DECIMAL(10, 8) NOT NULL,
  `longitude` DECIMAL(11, 8) NOT NULL,
  `provider` VARCHAR(50) NOT NULL,
  `confidence` DECIMAL(3, 2) DEFAULT 1.0,
  `raw_response` JSON NULL,
  `hits` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_address_hash` (`address_hash`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: sessions
CREATE TABLE IF NOT EXISTS `sessions` (
  `session_id` VARCHAR(128) PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `payload` TEXT NOT NULL,
  `last_activity` INT NOT NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: audit_log
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(50) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `old_values` JSON NULL,
  `new_values` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_entity` (`entity_type`, `entity_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: cache
CREATE TABLE IF NOT EXISTS `cache` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cache_key` VARCHAR(255) NOT NULL UNIQUE,
  `data` LONGTEXT NOT NULL,
  `expires_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cache_key` (`cache_key`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: password_resets
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_token` (`token`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: remember_tokens
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_token` (`token`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monitoring & Performance Tabellen
-- Tabelle: system_metrics
CREATE TABLE IF NOT EXISTS `system_metrics` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `metric_name` VARCHAR(100) NOT NULL,
  `metric_value` DECIMAL(10, 2) NOT NULL,
  `metric_unit` VARCHAR(20) NULL,
  `threshold_warning` DECIMAL(10, 2) NULL,
  `threshold_critical` DECIMAL(10, 2) NULL,
  `context` JSON NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_metric_name` (`metric_name`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: error_logs
CREATE TABLE IF NOT EXISTS `error_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `level` ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL,
  `message` TEXT NOT NULL,
  `context` JSON NULL,
  `exception_class` VARCHAR(255) NULL,
  `file` VARCHAR(500) NULL,
  `line` INT NULL,
  `stack_trace` TEXT NULL,
  `user_id` INT UNSIGNED NULL,
  `request_id` VARCHAR(50) NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_level` (`level`),
  INDEX `idx_request_id` (`request_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: performance_metrics
CREATE TABLE IF NOT EXISTS `performance_metrics` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `endpoint` VARCHAR(255) NOT NULL,
  `method` VARCHAR(10) NOT NULL,
  `response_time` INT NOT NULL,
  `memory_usage` BIGINT NULL,
  `status_code` INT NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `request_id` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_endpoint` (`endpoint`),
  INDEX `idx_response_time` (`response_time`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Monitoring Tabellen
-- Tabelle: api_usage
CREATE TABLE IF NOT EXISTS `api_usage` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `api_name` VARCHAR(50) NOT NULL,
  `endpoint` VARCHAR(255) NOT NULL,
  `method` VARCHAR(10) NOT NULL,
  `response_time` INT NULL,
  `status_code` INT NULL,
  `error_message` TEXT NULL,
  `request_data` JSON NULL,
  `response_data` JSON NULL,
  `user_id` INT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_api_name` (`api_name`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: api_limits
CREATE TABLE IF NOT EXISTS `api_limits` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `api_name` VARCHAR(50) NOT NULL UNIQUE,
  `daily_limit` INT NOT NULL DEFAULT 0,
  `hourly_limit` INT NOT NULL DEFAULT 0,
  `rate_limit` INT NOT NULL DEFAULT 0,
  `rate_window` INT NOT NULL DEFAULT 1,
  `current_daily_usage` INT NOT NULL DEFAULT 0,
  `current_hourly_usage` INT NOT NULL DEFAULT 0,
  `last_reset_daily` DATE NULL,
  `last_reset_hourly` DATETIME NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_api_name` (`api_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial-Daten für API-Limits
INSERT INTO `api_limits` (`api_name`, `daily_limit`, `hourly_limit`, `rate_limit`, `rate_window`) VALUES
('google_maps', 25000, 2500, 50, 1),
('nominatim', 86400, 3600, 1, 1),
('openrouteservice', 2000, 500, 5, 1)
ON DUPLICATE KEY UPDATE `api_name` = VALUES(`api_name`);

-- Admin-Benutzer
INSERT INTO `users` (`name`, `email`, `password`, `role`, `is_active`) VALUES
('Administrator', 'admin@firmenpro.de', '$2y$12$YourHashedPasswordHere', 'admin', 1)
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

-- Cleanup-Events
DELIMITER $$

CREATE EVENT IF NOT EXISTS cleanup_old_logs
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
  DELETE FROM error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
  DELETE FROM performance_metrics WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
  DELETE FROM api_usage WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
  DELETE FROM cache WHERE expires_at < NOW();
  DELETE FROM password_resets WHERE expires_at < NOW();
  DELETE FROM remember_tokens WHERE expires_at < NOW();
  DELETE FROM sessions WHERE last_activity < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY));
END$$

DELIMITER ;

SET foreign_key_checks = 1;