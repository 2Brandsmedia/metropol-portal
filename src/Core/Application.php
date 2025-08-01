<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Container;
use App\Core\Router;
use App\Core\Config;
use App\Core\Database;
use Exception;

/**
 * Hauptapplikations-Klasse
 * 
 * @author 2Brands Media GmbH
 */
class Application
{
    private static ?self $instance = null;
    private Container $container;
    private Router $router;
    private Config $config;
    private array $middleware = [];

    private function __construct()
    {
        $this->container = new Container();
        $this->config = new Config();
        $this->router = new Router($this->container);
        
        $this->bootstrap();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    private function bootstrap(): void
    {
        // Fehlerbehandlung einrichten
        $this->setupErrorHandling();
        
        // Services registrieren
        $this->registerServices();
        
        // Agents registrieren
        $this->registerAgents();
        
        // Globale Middleware registrieren
        $this->registerGlobalMiddleware();
    }

    private function setupErrorHandling(): void
    {
        error_reporting(E_ALL);
        
        set_error_handler(function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        
        set_exception_handler(function (\Throwable $e) {
            $this->handleException($e);
        });
    }

    private function registerServices(): void
    {
        // Config Service
        $this->container->singleton('config', function () {
            return $this->config;
        });
        
        // Database Service
        $this->container->singleton('db', function () {
            return new Database($this->config);
        });
        
        // Session Service
        $this->container->singleton('session', function () {
            return new Session();
        });
    }

    private function registerAgents(): void
    {
        // AuthAgent
        $this->container->singleton('auth', function () {
            return new \App\Agents\AuthAgent(
                $this->container->get('db'),
                $this->container->get('session'),
                $this->config
            );
        });
        
        // I18nAgent
        $this->container->singleton('i18n', function () {
            return new \App\Agents\I18nAgent(
                $this->config,
                $this->container->get('session')
            );
        });
        
        // DeployAgent
        $this->container->singleton('deploy', function () {
            return new \App\Agents\DeployAgent(
                $this->config,
                $this->container->get('db')
            );
        });
        
        // UIAgent
        $this->container->singleton('ui', function () {
            return new \App\Agents\UIAgent(
                $this->config,
                $this->container->get('i18n')
            );
        });
        
        // PlaylistAgent
        $this->container->singleton('playlist', function () {
            $playlistAgent = new \App\Agents\PlaylistAgent(
                $this->container->get('db'),
                $this->config
            );
            
            // GeoAgent injizieren wenn vorhanden
            if ($this->container->has('geo')) {
                $playlistAgent->setGeoAgent($this->container->get('geo'));
            }
            
            // RouteAgent injizieren wenn vorhanden
            if ($this->container->has('route')) {
                $playlistAgent->setRouteAgent($this->container->get('route'));
            }
            
            return $playlistAgent;
        });
        
        // GeoAgent
        $this->container->singleton('geo', function () {
            return new \App\Agents\GeoAgent(
                $this->container->get('db'),
                $this->config
            );
        });
        
        // RouteAgent
        $this->container->singleton('route', function () {
            $routeAgent = new \App\Agents\RouteAgent(
                $this->container->get('db'),
                $this->config
            );
            
            // GeoAgent injizieren wenn vorhanden
            if ($this->container->has('geo')) {
                $routeAgent->setGeoAgent($this->container->get('geo'));
            }
            
            // GoogleMapsAgent injizieren wenn vorhanden
            if ($this->container->has('google_maps')) {
                $routeAgent->setGoogleMapsAgent($this->container->get('google_maps'));
            }
            
            return $routeAgent;
        });
        
        // GoogleMapsAgent (nur wenn API-Key vorhanden)
        if ($this->config->get('google.maps_api_key')) {
            $this->container->singleton('google_maps', function () {
                return new \App\Agents\GoogleMapsAgent(
                    $this->container->get('db'),
                    $this->config
                );
            });
        }
    }

    private function registerGlobalMiddleware(): void
    {
        // Security Headers
        $this->addMiddleware(new \App\Middleware\SecurityMiddleware());
        
        // CORS
        $this->addMiddleware(new \App\Middleware\CorsMiddleware($this->config));
        
        // I18n
        $this->addMiddleware(new \App\Middleware\I18nMiddleware(
            $this->container->get('i18n')
        ));
    }

    public function addMiddleware($middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function run(): void
    {
        try {
            // Request erstellen
            $request = Request::capture();
            
            // Response erstellen
            $response = new Response();
            
            // Middleware-Pipeline durchlaufen
            $next = function ($request) use (&$response) {
                $response = $this->router->dispatch($request);
                return $response;
            };
            
            foreach (array_reverse($this->middleware) as $middleware) {
                $next = function ($request) use ($middleware, $next) {
                    return $middleware->handle($request, $next);
                };
            }
            
            $response = $next($request);
            
            // Response senden
            $response->send();
            
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    private function handleException(\Throwable $e): void
    {
        // Fehler loggen
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
        
        // Maintenance Mode prüfen
        if (file_exists($this->config->get('app.maintenance_file'))) {
            $this->sendMaintenanceResponse();
            return;
        }
        
        // Error Response senden
        $response = new Response();
        
        if ($this->config->get('app.debug')) {
            $response->json([
                'error' => true,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace()
            ], 500);
        } else {
            $response->json([
                'error' => true,
                'message' => 'Ein Fehler ist aufgetreten'
            ], 500);
        }
        
        $response->send();
        exit(1);
    }

    private function sendMaintenanceResponse(): void
    {
        $response = new Response();
        $response->setStatusCode(503);
        $response->setContent('Wartungsmodus aktiv. Bitte versuchen Sie es später erneut.');
        $response->send();
        exit;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }
}