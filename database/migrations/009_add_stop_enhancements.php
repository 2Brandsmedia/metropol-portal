<?php

declare(strict_types=1);

/**
 * Stop-Enhancements Migration
 * 
 * @author 2Brands Media GmbH
 */

class AddStopEnhancements
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $sql = "ALTER TABLE stops 
            ADD COLUMN client_id INT UNSIGNED NULL AFTER playlist_id,
            ADD COLUMN task_template_id INT UNSIGNED NULL AFTER client_id,
            ADD COLUMN priority ENUM('high', 'medium', 'low') NOT NULL DEFAULT 'medium' AFTER position,
            ADD COLUMN time_window_start TIME NULL AFTER work_duration,
            ADD COLUMN time_window_end TIME NULL AFTER time_window_start,
            ADD COLUMN actual_arrival_time TIMESTAMP NULL AFTER completed_at,
            ADD COLUMN actual_departure_time TIMESTAMP NULL AFTER actual_arrival_time,
            ADD COLUMN signature_url VARCHAR(255) NULL AFTER notes,
            ADD COLUMN photo_urls JSON NULL AFTER signature_url,
            ADD COLUMN completion_notes TEXT NULL AFTER photo_urls,
            ADD COLUMN weather_conditions VARCHAR(50) NULL AFTER completion_notes,
            ADD COLUMN traffic_conditions ENUM('light', 'moderate', 'heavy') NULL AFTER weather_conditions,
            
            ADD FOREIGN KEY fk_stops_client (client_id) REFERENCES clients(id) ON DELETE SET NULL,
            ADD INDEX idx_priority (priority),
            ADD INDEX idx_time_window (time_window_start, time_window_end),
            ADD INDEX idx_client_id (client_id),
            ADD INDEX idx_composite (playlist_id, priority, position)";

        $this->db->exec($sql);
    }

    public function down(): void
    {
        // Zuerst Foreign Key entfernen
        $this->db->exec("ALTER TABLE stops DROP FOREIGN KEY fk_stops_client");
        
        $sql = "ALTER TABLE stops 
            DROP COLUMN client_id,
            DROP COLUMN task_template_id,
            DROP COLUMN priority,
            DROP COLUMN time_window_start,
            DROP COLUMN time_window_end,
            DROP COLUMN actual_arrival_time,
            DROP COLUMN actual_departure_time,
            DROP COLUMN signature_url,
            DROP COLUMN photo_urls,
            DROP COLUMN completion_notes,
            DROP COLUMN weather_conditions,
            DROP COLUMN traffic_conditions,
            
            DROP INDEX idx_priority,
            DROP INDEX idx_time_window,
            DROP INDEX idx_client_id,
            DROP INDEX idx_composite";

        $this->db->exec($sql);
    }
}