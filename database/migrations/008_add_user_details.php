<?php

declare(strict_types=1);

/**
 * User-Details Migration
 * 
 * @author 2Brands Media GmbH
 */

class AddUserDetails
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $sql = "ALTER TABLE users 
            ADD COLUMN phone VARCHAR(50) NULL AFTER email,
            ADD COLUMN avatar_url VARCHAR(255) NULL AFTER language,
            ADD COLUMN working_hours_start TIME DEFAULT '08:00:00' AFTER avatar_url,
            ADD COLUMN working_hours_end TIME DEFAULT '17:00:00' AFTER working_hours_start,
            ADD COLUMN max_daily_stops INT UNSIGNED DEFAULT 20 AFTER working_hours_end,
            ADD COLUMN vehicle_type VARCHAR(50) DEFAULT 'car' AFTER max_daily_stops,
            ADD COLUMN driver_license_number VARCHAR(50) NULL AFTER vehicle_type,
            ADD COLUMN emergency_contact VARCHAR(255) NULL AFTER driver_license_number,
            ADD COLUMN emergency_phone VARCHAR(50) NULL AFTER emergency_contact,
            
            ADD INDEX idx_working_hours (working_hours_start, working_hours_end)";

        $this->db->exec($sql);
    }

    public function down(): void
    {
        $sql = "ALTER TABLE users 
            DROP COLUMN phone,
            DROP COLUMN avatar_url,
            DROP COLUMN working_hours_start,
            DROP COLUMN working_hours_end,
            DROP COLUMN max_daily_stops,
            DROP COLUMN vehicle_type,
            DROP COLUMN driver_license_number,
            DROP COLUMN emergency_contact,
            DROP COLUMN emergency_phone,
            
            DROP INDEX idx_working_hours";

        $this->db->exec($sql);
    }
}