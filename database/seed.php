<?php

declare(strict_types=1);

/**
 * Datenbank-Seeder für Demo-Daten
 * 
 * @author 2Brands Media GmbH
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Umgebungsvariablen laden
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

class DatabaseSeeder
{
    private PDO $db;

    public function __construct()
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $_ENV['DB_HOST'],
                $_ENV['DB_PORT'],
                $_ENV['DB_DATABASE'],
                $_ENV['DB_CHARSET']
            );

            $this->db = new PDO(
                $dsn,
                $_ENV['DB_USERNAME'],
                $_ENV['DB_PASSWORD'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );

            echo "✓ Datenbankverbindung hergestellt\n";
        } catch (PDOException $e) {
            die("✗ Datenbankverbindung fehlgeschlagen: " . $e->getMessage() . "\n");
        }
    }

    public function run(): void
    {
        echo "🌱 Seeding gestartet...\n\n";

        $this->seedUsers();
        $this->seedClients();
        $this->seedTaskTemplates();
        $this->seedUserSkills();
        $this->seedPlaylistTemplates();

        echo "\n✅ Seeding erfolgreich abgeschlossen!\n";
    }

    private function seedUsers(): void
    {
        echo "👥 Erstelle Benutzer...\n";

        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@metropol.de',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'role' => 'admin',
                'phone' => '+49 30 12345678',
                'language' => 'de',
                'working_hours_start' => '07:00:00',
                'working_hours_end' => '16:00:00',
                'max_daily_stops' => 25,
                'vehicle_type' => 'car'
            ],
            [
                'name' => 'Max Mustermann',
                'email' => 'max@metropol.de',
                'password' => password_hash('max123', PASSWORD_DEFAULT),
                'role' => 'employee',
                'phone' => '+49 30 23456789',
                'language' => 'de',
                'working_hours_start' => '08:00:00',
                'working_hours_end' => '17:00:00',
                'max_daily_stops' => 20,
                'vehicle_type' => 'van'
            ],
            [
                'name' => 'Erika Schmidt',
                'email' => 'erika@metropol.de',
                'password' => password_hash('erika123', PASSWORD_DEFAULT),
                'role' => 'employee',
                'phone' => '+49 30 34567890',
                'language' => 'de',
                'working_hours_start' => '07:30:00',
                'working_hours_end' => '16:30:00',
                'max_daily_stops' => 18,
                'vehicle_type' => 'car'
            ],
            [
                'name' => 'Mehmet Yilmaz',
                'email' => 'mehmet@metropol.de',
                'password' => password_hash('mehmet123', PASSWORD_DEFAULT),
                'role' => 'employee',
                'phone' => '+49 30 45678901',
                'language' => 'tr',
                'working_hours_start' => '09:00:00',
                'working_hours_end' => '18:00:00',
                'max_daily_stops' => 15,
                'vehicle_type' => 'bike'
            ]
        ];

        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, password, role, phone, language, 
             working_hours_start, working_hours_end, max_daily_stops, vehicle_type) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($users as $user) {
            $stmt->execute([
                $user['name'], $user['email'], $user['password'], $user['role'],
                $user['phone'], $user['language'], $user['working_hours_start'],
                $user['working_hours_end'], $user['max_daily_stops'], $user['vehicle_type']
            ]);
            echo "  ✓ {$user['name']} ({$user['email']})\n";
        }
    }

    private function seedClients(): void
    {
        echo "\n🏢 Erstelle Kunden...\n";

        // Echte Berliner Adressen für Demo
        $clients = [
            ['name' => 'Café Einstein', 'company' => 'Einstein Gastronomie GmbH', 'address' => 'Kurfürstenstraße 58, 10785 Berlin', 'lat' => 52.5040, 'lng' => 13.3522],
            ['name' => 'Buchhandlung Dussmann', 'company' => 'Dussmann KulturKaufhaus', 'address' => 'Friedrichstraße 90, 10117 Berlin', 'lat' => 52.5195, 'lng' => 13.3885],
            ['name' => 'Apotheke am Alex', 'company' => null, 'address' => 'Alexanderplatz 2, 10178 Berlin', 'lat' => 52.5219, 'lng' => 13.4132],
            ['name' => 'Hotel Adlon Kempinski', 'company' => 'Kempinski Hotels', 'address' => 'Unter den Linden 77, 10117 Berlin', 'lat' => 52.5158, 'lng' => 13.3801],
            ['name' => 'Bäckerei Siebert', 'company' => null, 'address' => 'Schönhauser Allee 58, 10437 Berlin', 'lat' => 52.5295, 'lng' => 13.4134],
            ['name' => 'Praxis Dr. Weber', 'company' => 'MVZ Berlin-Mitte', 'address' => 'Potsdamer Straße 58, 10785 Berlin', 'lat' => 52.5030, 'lng' => 13.3631],
            ['name' => 'Edeka Reichelt', 'company' => 'EDEKA Handelsgesellschaft', 'address' => 'Greifswalder Straße 207, 10405 Berlin', 'lat' => 52.5321, 'lng' => 13.4204],
            ['name' => 'DHL Paketshop', 'company' => 'Deutsche Post DHL', 'address' => 'Kottbusser Damm 25, 10967 Berlin', 'lat' => 52.4898, 'lng' => 13.4195],
            ['name' => 'Sparkasse Berlin', 'company' => 'Berliner Sparkasse', 'address' => 'Alexanderplatz 8, 10178 Berlin', 'lat' => 52.5213, 'lng' => 13.4151],
            ['name' => 'Fitness First', 'company' => 'Fitness First Germany GmbH', 'address' => 'Hauptstraße 5, 10827 Berlin', 'lat' => 52.4836, 'lng' => 13.3535],
            ['name' => 'Rossmann', 'company' => 'Dirk Rossmann GmbH', 'address' => 'Karl-Marx-Allee 91, 10243 Berlin', 'lat' => 52.5168, 'lng' => 13.4350],
            ['name' => 'Zahnarzt Praxis Mitte', 'company' => null, 'address' => 'Rosenthaler Straße 46, 10178 Berlin', 'lat' => 52.5253, 'lng' => 13.4014],
            ['name' => 'Restaurant Zur letzten Instanz', 'company' => null, 'address' => 'Waisenstraße 14-16, 10179 Berlin', 'lat' => 52.5156, 'lng' => 13.4087],
            ['name' => 'Saturn Alexanderplatz', 'company' => 'Saturn Elektro-Handelsgesellschaft', 'address' => 'Alexanderplatz 3, 10178 Berlin', 'lat' => 52.5217, 'lng' => 13.4140],
            ['name' => 'dm-drogerie markt', 'company' => 'dm-drogerie markt GmbH', 'address' => 'Friedrichstraße 191, 10117 Berlin', 'lat' => 52.5061, 'lng' => 13.3876],
            ['name' => 'Bürgerbüro Mitte', 'company' => 'Bezirksamt Mitte', 'address' => 'Karl-Marx-Allee 31, 10178 Berlin', 'lat' => 52.5183, 'lng' => 13.4206],
            ['name' => 'Rewe City', 'company' => 'REWE Markt GmbH', 'address' => 'Torstraße 125, 10119 Berlin', 'lat' => 52.5294, 'lng' => 13.3938],
            ['name' => 'BioCompany', 'company' => 'Bio Company SE', 'address' => 'Anklamer Straße 38, 10115 Berlin', 'lat' => 52.5361, 'lng' => 13.3975],
            ['name' => 'Optiker Fielmann', 'company' => 'Fielmann AG', 'address' => 'Wilmersdorfer Straße 46, 10627 Berlin', 'lat' => 52.5066, 'lng' => 13.3049],
            ['name' => 'Thalia Buchhandlung', 'company' => 'Thalia Bücher GmbH', 'address' => 'Tauentzienstraße 13, 10789 Berlin', 'lat' => 52.5026, 'lng' => 13.3368]
        ];

        $stmt = $this->db->prepare(
            "INSERT INTO clients (name, company, address, latitude, longitude, 
             default_work_duration, phone, email, is_active) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $workDurations = [15, 20, 30, 45, 60];
        
        foreach ($clients as $i => $client) {
            $phone = sprintf('+49 30 %d%d%d%d%d%d%d', 
                rand(2,9), rand(0,9), rand(0,9), rand(0,9), rand(0,9), rand(0,9), rand(0,9));
            $email = strtolower(str_replace(' ', '.', $client['name'])) . '@example.com';
            $duration = $workDurations[array_rand($workDurations)];
            
            $stmt->execute([
                $client['name'], $client['company'], $client['address'], 
                $client['lat'], $client['lng'], $duration,
                $phone, $email, true
            ]);
            echo "  ✓ {$client['name']}\n";
        }
    }

    private function seedTaskTemplates(): void
    {
        echo "\n📋 Erstelle Aufgabenvorlagen...\n";

        $templates = [
            [
                'name' => 'Standardlieferung',
                'description' => 'Normale Paketlieferung an Kunden',
                'duration' => 15,
                'category' => 'delivery',
                'skills' => ['driving', 'customer_service'],
                'checklist' => ['Paket prüfen', 'Kunde kontaktieren', 'Unterschrift einholen', 'Foto machen']
            ],
            [
                'name' => 'Installation Haushaltsgerät',
                'description' => 'Installation und Inbetriebnahme von Haushaltsgeräten',
                'duration' => 60,
                'category' => 'installation',
                'skills' => ['technical', 'electrical', 'customer_service'],
                'checklist' => ['Altes Gerät ausbauen', 'Neues Gerät installieren', 'Funktionstest', 'Kunde einweisen']
            ],
            [
                'name' => 'Wartung Klimaanlage',
                'description' => 'Regelmäßige Wartung von Klimaanlagen',
                'duration' => 45,
                'category' => 'maintenance',
                'skills' => ['hvac', 'technical'],
                'checklist' => ['Filter prüfen', 'Kühlmittel kontrollieren', 'Reinigung', 'Protokoll erstellen']
            ],
            [
                'name' => 'Medikamentenlieferung',
                'description' => 'Lieferung von Medikamenten aus der Apotheke',
                'duration' => 20,
                'category' => 'medical',
                'skills' => ['driving', 'medical_knowledge'],
                'checklist' => ['Rezept prüfen', 'Medikamente kontrollieren', 'Übergabe dokumentieren']
            ],
            [
                'name' => 'IT-Support vor Ort',
                'description' => 'Technischer Support für Computer und Netzwerke',
                'duration' => 90,
                'category' => 'it',
                'skills' => ['it_support', 'networking', 'customer_service'],
                'checklist' => ['Problem analysieren', 'Lösung implementieren', 'System testen', 'Dokumentation']
            ],
            [
                'name' => 'Reinigungsservice',
                'description' => 'Professionelle Reinigung von Büros und Geschäften',
                'duration' => 120,
                'category' => 'cleaning',
                'skills' => ['cleaning', 'time_management'],
                'checklist' => ['Materialien prüfen', 'Reinigung durchführen', 'Qualitätskontrolle', 'Abnahme']
            ],
            [
                'name' => 'Gartenarbeit',
                'description' => 'Gartenpflege und Landschaftsarbeiten',
                'duration' => 180,
                'category' => 'gardening',
                'skills' => ['gardening', 'physical_strength'],
                'checklist' => ['Rasen mähen', 'Hecke schneiden', 'Unkraut entfernen', 'Abfall entsorgen']
            ],
            [
                'name' => 'Express-Kurier',
                'description' => 'Eilige Dokumentenlieferung',
                'duration' => 10,
                'category' => 'express',
                'skills' => ['driving', 'navigation'],
                'checklist' => ['Dokumente sichern', 'Schnellste Route wählen', 'Übergabe bestätigen']
            ],
            [
                'name' => 'Möbelmontage',
                'description' => 'Aufbau von Möbeln beim Kunden',
                'duration' => 90,
                'category' => 'assembly',
                'skills' => ['assembly', 'tools', 'customer_service'],
                'checklist' => ['Teile prüfen', 'Montage durchführen', 'Stabilität testen', 'Verpackung entsorgen']
            ],
            [
                'name' => 'Lebensmittellieferung',
                'description' => 'Lieferung von Lebensmitteleinkäufen',
                'duration' => 25,
                'category' => 'food',
                'skills' => ['driving', 'food_handling'],
                'checklist' => ['Kühlkette prüfen', 'Bestellung kontrollieren', 'Rechnung übergeben']
            ]
        ];

        $stmt = $this->db->prepare(
            "INSERT INTO task_templates (name, description, estimated_duration, 
             category, required_skills, checklist, is_active) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($templates as $template) {
            $stmt->execute([
                $template['name'], $template['description'], $template['duration'],
                $template['category'], json_encode($template['skills']), 
                json_encode($template['checklist']), true
            ]);
            echo "  ✓ {$template['name']}\n";
        }
    }

    private function seedUserSkills(): void
    {
        echo "\n🛠 Erstelle Mitarbeiter-Fähigkeiten...\n";

        $skills = [
            2 => [ // Max Mustermann
                ['driving', 'transport', 'expert'],
                ['customer_service', 'soft', 'expert'],
                ['delivery', 'transport', 'expert'],
                ['navigation', 'transport', 'intermediate']
            ],
            3 => [ // Erika Schmidt
                ['driving', 'transport', 'expert'],
                ['medical_knowledge', 'medical', 'intermediate'],
                ['customer_service', 'soft', 'expert'],
                ['time_management', 'soft', 'expert']
            ],
            4 => [ // Mehmet Yilmaz
                ['driving', 'transport', 'intermediate'],
                ['technical', 'technical', 'expert'],
                ['it_support', 'technical', 'expert'],
                ['customer_service', 'soft', 'intermediate']
            ]
        ];

        $stmt = $this->db->prepare(
            "INSERT INTO user_skills (user_id, skill_name, skill_category, level, certified) 
             VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($skills as $userId => $userSkills) {
            foreach ($userSkills as $skill) {
                $stmt->execute([$userId, $skill[0], $skill[1], $skill[2], rand(0,1)]);
                echo "  ✓ User {$userId}: {$skill[0]} ({$skill[2]})\n";
            }
        }
    }

    private function seedPlaylistTemplates(): void
    {
        echo "\n📅 Erstelle Routenvorlagen...\n";

        $templates = [
            [
                'name' => 'Montags-Route Mitte',
                'description' => 'Standard-Route für Berlin-Mitte jeden Montag',
                'day' => 'monday',
                'stops' => [
                    ['client_id' => 2, 'duration' => 30, 'time_window' => ['09:00', '10:00']],
                    ['client_id' => 4, 'duration' => 45, 'time_window' => ['10:30', '11:30']],
                    ['client_id' => 9, 'duration' => 20, 'time_window' => ['12:00', '13:00']],
                    ['client_id' => 13, 'duration' => 60, 'time_window' => ['14:00', '16:00']]
                ]
            ],
            [
                'name' => 'Express-Lieferungen Vormittag',
                'description' => 'Schnelle Lieferroute für eilige Sendungen',
                'day' => null,
                'stops' => [
                    ['client_id' => 1, 'duration' => 10],
                    ['client_id' => 3, 'duration' => 10],
                    ['client_id' => 8, 'duration' => 15],
                    ['client_id' => 14, 'duration' => 10],
                    ['client_id' => 16, 'duration' => 10]
                ]
            ],
            [
                'name' => 'Apotheken-Tour',
                'description' => 'Medikamentenlieferung an Stammkunden',
                'day' => null,
                'stops' => [
                    ['client_id' => 3, 'duration' => 20],
                    ['client_id' => 6, 'duration' => 25],
                    ['client_id' => 12, 'duration' => 20]
                ]
            ],
            [
                'name' => 'Freitags-Wartung',
                'description' => 'Wöchentliche Wartungsrunde',
                'day' => 'friday',
                'stops' => [
                    ['client_id' => 5, 'duration' => 60],
                    ['client_id' => 10, 'duration' => 45],
                    ['client_id' => 15, 'duration' => 90],
                    ['client_id' => 18, 'duration' => 30]
                ]
            ],
            [
                'name' => 'Großkunden-Route',
                'description' => 'Monatliche Besuche bei Großkunden',
                'day' => null,
                'stops' => [
                    ['client_id' => 7, 'duration' => 120],
                    ['client_id' => 11, 'duration' => 90],
                    ['client_id' => 17, 'duration' => 60]
                ]
            ]
        ];

        $stmt = $this->db->prepare(
            "INSERT INTO playlist_templates (name, description, stops, 
             estimated_duration, day_of_week, is_active, created_by) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($templates as $template) {
            $totalDuration = array_sum(array_column($template['stops'], 'duration'));
            
            $stmt->execute([
                $template['name'], 
                $template['description'], 
                json_encode($template['stops']),
                $totalDuration + (count($template['stops']) * 15), // +15 Min Fahrtzeit pro Stopp
                $template['day'],
                true,
                1 // Admin User
            ]);
            echo "  ✓ {$template['name']}\n";
        }
    }
}

// Seeder ausführen
if (php_sapi_name() === 'cli') {
    $seeder = new DatabaseSeeder();
    $seeder->run();
}