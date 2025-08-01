<?php

declare(strict_types=1);

/**
 * Traffic-Daten Migration
 * Fügt Traffic-bezogene Felder zu Playlists und Stops hinzu
 * 
 * @author 2Brands Media GmbH
 */

class AddTrafficDataToRoutes
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        // Traffic-Daten zu route_optimizations hinzufügen
        $this->db->exec("
            ALTER TABLE route_optimizations 
            ADD COLUMN traffic_delay INT DEFAULT 0 COMMENT 'Verzögerung durch Verkehr in Sekunden' AFTER traffic_considered,
            ADD COLUMN traffic_data JSON NULL COMMENT 'Detaillierte Traffic-Informationen' AFTER traffic_delay,
            ADD COLUMN traffic_severity ENUM('low', 'medium', 'high', 'severe', 'unknown') NULL DEFAULT 'unknown' AFTER traffic_data,
            ADD INDEX idx_traffic_severity (traffic_severity)
        ");

        // Traffic-Daten zu stops hinzufügen
        $this->db->exec("
            ALTER TABLE stops 
            ADD COLUMN travel_duration_in_traffic INT NULL COMMENT 'Fahrzeit mit Verkehr in Minuten' AFTER travel_duration,
            ADD COLUMN traffic_delay INT DEFAULT 0 COMMENT 'Verzögerung durch Verkehr in Sekunden' AFTER travel_duration_in_traffic,
            ADD COLUMN traffic_status ENUM('low', 'medium', 'high', 'severe', 'unknown') NULL DEFAULT NULL AFTER traffic_delay,
            ADD INDEX idx_traffic_status (traffic_status)
        ");

        // Traffic-Daten zu playlists hinzufügen
        $this->db->exec("
            ALTER TABLE playlists 
            ADD COLUMN total_travel_time_in_traffic INT NULL COMMENT 'Gesamtfahrzeit mit Verkehr in Minuten' AFTER total_travel_time,
            ADD COLUMN total_traffic_delay INT DEFAULT 0 COMMENT 'Gesamtverzögerung durch Verkehr in Sekunden' AFTER total_travel_time_in_traffic,
            ADD COLUMN overall_traffic_severity ENUM('low', 'medium', 'high', 'severe', 'unknown') NULL DEFAULT NULL AFTER total_traffic_delay,
            ADD COLUMN last_traffic_update TIMESTAMP NULL DEFAULT NULL AFTER overall_traffic_severity,
            ADD INDEX idx_overall_traffic (overall_traffic_severity),
            ADD INDEX idx_traffic_update (last_traffic_update)
        ");

        // Neue Tabelle für Traffic-History
        $this->db->exec("
            CREATE TABLE traffic_history (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                playlist_id INT UNSIGNED NOT NULL,
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                total_duration_normal INT NOT NULL COMMENT 'Normale Fahrzeit in Minuten',
                total_duration_in_traffic INT NOT NULL COMMENT 'Fahrzeit mit Verkehr in Minuten',
                traffic_delay INT NOT NULL COMMENT 'Verzögerung in Sekunden',
                traffic_severity ENUM('low', 'medium', 'high', 'severe', 'unknown') NOT NULL,
                segment_data JSON NOT NULL COMMENT 'Detaillierte Traffic-Daten pro Segment',
                weather_conditions JSON NULL COMMENT 'Wetterbedingungen zum Zeitpunkt',
                special_events JSON NULL COMMENT 'Besondere Ereignisse (Unfälle, Baustellen etc.)',
                
                FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
                INDEX idx_playlist_time (playlist_id, recorded_at),
                INDEX idx_severity (traffic_severity),
                INDEX idx_delay (traffic_delay)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Neue Tabelle für Traffic-Alerts
        $this->db->exec("
            CREATE TABLE traffic_alerts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                playlist_id INT UNSIGNED NOT NULL,
                stop_id INT UNSIGNED NULL,
                alert_type ENUM('accident', 'congestion', 'road_closed', 'construction', 'weather', 'event') NOT NULL,
                severity ENUM('info', 'warning', 'critical') NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                location_lat DECIMAL(10, 8) NULL,
                location_lng DECIMAL(11, 8) NULL,
                affected_distance INT NULL COMMENT 'Betroffene Strecke in Metern',
                estimated_delay INT NULL COMMENT 'Geschätzte Verzögerung in Minuten',
                starts_at TIMESTAMP NULL DEFAULT NULL,
                ends_at TIMESTAMP NULL DEFAULT NULL,
                active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
                FOREIGN KEY (stop_id) REFERENCES stops(id) ON DELETE CASCADE,
                INDEX idx_playlist_active (playlist_id, active),
                INDEX idx_type_severity (alert_type, severity),
                INDEX idx_time_range (starts_at, ends_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        // Tabellen löschen
        $this->db->exec("DROP TABLE IF EXISTS traffic_alerts");
        $this->db->exec("DROP TABLE IF EXISTS traffic_history");

        // Spalten von playlists entfernen
        $this->db->exec("
            ALTER TABLE playlists 
            DROP INDEX idx_overall_traffic,
            DROP INDEX idx_traffic_update,
            DROP COLUMN total_travel_time_in_traffic,
            DROP COLUMN total_traffic_delay,
            DROP COLUMN overall_traffic_severity,
            DROP COLUMN last_traffic_update
        ");

        // Spalten von stops entfernen
        $this->db->exec("
            ALTER TABLE stops 
            DROP INDEX idx_traffic_status,
            DROP COLUMN travel_duration_in_traffic,
            DROP COLUMN traffic_delay,
            DROP COLUMN traffic_status
        ");

        // Spalten von route_optimizations entfernen
        $this->db->exec("
            ALTER TABLE route_optimizations 
            DROP INDEX idx_traffic_severity,
            DROP COLUMN traffic_delay,
            DROP COLUMN traffic_data,
            DROP COLUMN traffic_severity
        ");
    }
}