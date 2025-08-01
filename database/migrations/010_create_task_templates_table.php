<?php

declare(strict_types=1);

/**
 * Task-Templates-Tabelle Migration
 * 
 * @author 2Brands Media GmbH
 */

class CreateTaskTemplatesTable
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $sql = "CREATE TABLE task_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            estimated_duration INT UNSIGNED DEFAULT 30 COMMENT 'in Minuten',
            required_skills JSON NULL COMMENT 'Array von erforderlichen Fähigkeiten',
            category VARCHAR(50) NULL,
            checklist JSON NULL COMMENT 'Array von Checklist-Items',
            materials_needed TEXT NULL,
            safety_requirements TEXT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_category (category),
            INDEX idx_active (is_active),
            INDEX idx_name (name),
            FULLTEXT idx_search (name, description)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);

        // Foreign Key für stops-Tabelle hinzufügen
        $this->db->exec("ALTER TABLE stops ADD FOREIGN KEY fk_stops_task_template (task_template_id) REFERENCES task_templates(id) ON DELETE SET NULL");
    }

    public function down(): void
    {
        // Zuerst Foreign Key von stops entfernen
        $this->db->exec("ALTER TABLE stops DROP FOREIGN KEY fk_stops_task_template");
        
        $this->db->exec("DROP TABLE IF EXISTS task_templates");
    }
}