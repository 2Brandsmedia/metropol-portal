<?php

declare(strict_types=1);

/**
 * Cache-Tabelle Migration für Rate-Limiting
 * 
 * @author 2Brands Media GmbH
 */

class CreateCacheTable
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $sql = "CREATE TABLE cache (
            `key` VARCHAR(255) PRIMARY KEY,
            value TEXT NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);

        // Event für automatisches Löschen abgelaufener Einträge
        $this->db->exec("
            CREATE EVENT IF NOT EXISTS clean_expired_cache
            ON SCHEDULE EVERY 1 HOUR
            DO DELETE FROM cache WHERE expires_at < NOW()
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP EVENT IF EXISTS clean_expired_cache");
        $this->db->exec("DROP TABLE IF EXISTS cache");
    }
}