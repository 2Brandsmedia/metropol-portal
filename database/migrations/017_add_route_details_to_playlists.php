<?php

declare(strict_types=1);

/**
 * Route-Details zu Playlists hinzufügen Migration
 * 
 * @author 2Brands Media GmbH
 */

class AddRouteDetailsToPlaylists
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $sql = "ALTER TABLE playlists 
            ADD COLUMN total_distance INT UNSIGNED DEFAULT 0 COMMENT 'Gesamtstrecke in Metern',
            ADD COLUMN total_travel_time INT UNSIGNED DEFAULT 0 COMMENT 'Gesamtfahrzeit in Minuten',
            ADD COLUMN route_polyline TEXT NULL COMMENT 'Encoded Polyline der Route',
            ADD COLUMN last_optimized_at TIMESTAMP NULL DEFAULT NULL,
            ADD INDEX idx_optimized (last_optimized_at)";

        $this->db->exec($sql);
    }

    public function down(): void
    {
        $sql = "ALTER TABLE playlists 
            DROP COLUMN total_distance,
            DROP COLUMN total_travel_time,
            DROP COLUMN route_polyline,
            DROP COLUMN last_optimized_at";

        $this->db->exec($sql);
    }
}