<?php

declare(strict_types=1);

/**
 * User-Skills-Tabelle Migration
 * 
 * @author 2Brands Media GmbH
 */

class CreateUserSkillsTable
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $sql = "CREATE TABLE user_skills (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            skill_name VARCHAR(100) NOT NULL,
            skill_category VARCHAR(50) NULL,
            level ENUM('beginner', 'intermediate', 'expert') NOT NULL DEFAULT 'intermediate',
            certified BOOLEAN DEFAULT FALSE,
            certification_date DATE NULL,
            certification_expiry DATE NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_skill (user_id, skill_name),
            INDEX idx_skill_name (skill_name),
            INDEX idx_level (level),
            INDEX idx_category (skill_category),
            INDEX idx_certified (certified)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS user_skills");
    }
}