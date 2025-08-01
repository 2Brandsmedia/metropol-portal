<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Application Core (Standalone Version)
 * 
 * Hauptklasse der Anwendung ohne externe Dependencies
 * 
 * @author 2Brands Media GmbH
 */
class Application
{
    private static ?self $instance = null;
    private \Router $router;
    private array $config = [];
    
    /**
     * Konstruktor
     */
    private function __construct()
    {
        $this->loadConfig();
        $this->initializeRouter();
        $this->initializeDatabase();
    }
    
    /**
     * Singleton-Instanz
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Lädt die Konfiguration
     */
    private function loadConfig(): void
    {
        $this->config = [
            'app' => [
                'name' => \Env::get('APP_NAME', 'Metropol Portal'),
                'env' => \Env::get('APP_ENV', 'production'),
                'debug' => \Env::get('APP_DEBUG', 'false') === 'true',
                'url' => \Env::get('APP_URL', ''),
                'timezone' => \Env::get('APP_TIMEZONE', 'Europe/Berlin'),
                'locale' => \Env::get('DEFAULT_LOCALE', 'de')
            ],
            'database' => [
                'host' => \Env::get('DB_HOST', 'localhost'),
                'port' => \Env::get('DB_PORT', '3306'),
                'database' => \Env::get('DB_DATABASE', ''),
                'username' => \Env::get('DB_USERNAME', ''),
                'password' => \Env::get('DB_PASSWORD', ''),
                'charset' => 'utf8mb4'
            ],
            'api' => [
                'google_maps_key' => \Env::get('GOOGLE_MAPS_API_KEY', ''),
                'ors_api_key' => \Env::get('ORS_API_KEY', '')
            ]
        ];
    }
    
    /**
     * Initialisiert den Router
     */
    private function initializeRouter(): void
    {
        $this->router = new \Router();
        
        // Basis-Pfad setzen falls in Unterverzeichnis
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath !== '/') {
            $this->router->setBasePath($basePath);
        }
        
        // Globale Middlewares registrieren
        $this->registerMiddlewares();
    }
    
    /**
     * Initialisiert die Datenbank
     */
    private function initializeDatabase(): void
    {
        try {
            \Database::configure($this->config['database']);
            
            // Test-Verbindung
            \Database::getInstance();
        } catch (\Exception $e) {
            // Bei Installation ist das normal
            if (!$this->isInstalling()) {
                throw $e;
            }
        }
    }
    
    /**
     * Registriert globale Middlewares
     */
    private function registerMiddlewares(): void
    {
        // Session-Check für geschützte Routen
        $this->router->addMiddleware(function($request, $response) {
            // Öffentliche Routen
            $publicRoutes = [
                '/login',
                '/api/auth/login',
                '/api/i18n/switch',
                '/install.php'
            ];
            
            // Prüfe ob Route öffentlich ist
            foreach ($publicRoutes as $route) {
                if (strpos($request->uri, $route) !== false) {
                    return true;
                }
            }
            
            // Prüfe ob eingeloggt
            if (!\Session::isLoggedIn()) {
                if (strpos($request->uri, '/api/') === 0) {
                    $response->json(['error' => 'Nicht authentifiziert'], 401);
                } else {
                    $response->redirect('/login');
                }
                return false;
            }
            
            // Session erneuern
            \Session::touch();
            
            return true;
        });
        
        // CSRF-Schutz für POST/PUT/DELETE
        $this->router->addMiddleware(function($request, $response) {
            if (in_array($request->method, ['POST', 'PUT', 'DELETE'])) {
                // API-Requests ausschließen (verwenden andere Auth)
                if (strpos($request->uri, '/api/') === 0) {
                    return true;
                }
                
                $token = $request->body['csrf_token'] ?? '';
                if (!\Session::validateCsrfToken($token)) {
                    $response->json(['error' => 'CSRF-Token ungültig'], 403);
                    return false;
                }
            }
            
            return true;
        });
    }
    
    /**
     * Gibt den Router zurück
     */
    public function getRouter(): \Router
    {
        return $this->router;
    }
    
    /**
     * Gibt die Konfiguration zurück
     */
    public function getConfig(string $key = null)
    {
        if ($key === null) {
            return $this->config;
        }
        
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Führt die Anwendung aus
     */
    public function run(): void
    {
        $this->router->dispatch();
    }
    
    /**
     * Prüft ob gerade installiert wird
     */
    private function isInstalling(): bool
    {
        return strpos($_SERVER['REQUEST_URI'] ?? '', 'install.php') !== false;
    }
    
    /**
     * Hilfsmethode: View rendern
     */
    public static function view(string $template, array $data = []): string
    {
        $templatePath = BASE_PATH . '/templates/' . $template . '.php';
        
        if (!file_exists($templatePath)) {
            throw new \Exception("Template nicht gefunden: $template");
        }
        
        // Daten extrahieren
        extract($data);
        
        // CSRF-Token immer verfügbar machen
        $csrf_token = \Session::get('_csrf_token') ?? \Session::generateCsrfToken();
        
        // Aktueller Benutzer
        $currentUser = \Session::getUser();
        
        // Übersetzungen laden
        $locale = \Session::get('locale', 'de');
        $translations = self::loadTranslations($locale);
        
        // Output buffering
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }
    
    /**
     * Lädt Übersetzungen
     */
    private static function loadTranslations(string $locale): array
    {
        $file = BASE_PATH . '/lang/' . $locale . '.json';
        
        if (!file_exists($file)) {
            $file = BASE_PATH . '/lang/de.json';
        }
        
        $json = file_get_contents($file);
        return json_decode($json, true) ?? [];
    }
    
    /**
     * Hilfsmethode: JSON Response
     */
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Hilfsmethode: Redirect
     */
    public static function redirect(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header('Location: ' . $url);
        exit;
    }
}