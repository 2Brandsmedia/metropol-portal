<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Agents\MonitorAgent;
use Closure;
use Throwable;

/**
 * Monitoring-Middleware für automatisches Performance-Tracking
 * 
 * Überwacht alle API-Requests und sammelt Performance-Metriken:
 * - Response-Zeiten mit Targets (Login <100ms, Route <300ms, API <200ms)
 * - Speicherverbrauch und System-Ressourcen
 * - Error-Tracking mit Context-Informationen
 * - Automatische Alert-Generierung bei Grenzwertüberschreitungen
 * 
 * @author 2Brands Media GmbH
 */
class MonitoringMiddleware
{
    private MonitorAgent $monitor;
    private array $config;

    public function __construct(MonitorAgent $monitor, array $config = [])
    {
        $this->monitor = $monitor;
        $this->config = array_merge([
            'enabled' => true,
            'track_all_requests' => true,
            'track_only_api' => false,
            'exclude_patterns' => [
                '/assets/',
                '/favicon.ico',
                '/robots.txt'
            ],
            'sample_rate' => 1.0, // 100% der Requests tracken
            'max_payload_log_size' => 1024 // Max Bytes für Request-Payload-Logging
        ], $config);
    }

    /**
     * Handle eingehende Requests
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->shouldTrackRequest($request)) {
            return $next($request);
        }

        $endpoint = $this->normalizeEndpoint($request->getUri());
        $method = $request->getMethod();
        $userId = $this->extractUserId($request);

        // Request-Monitoring starten
        $this->monitor->startRequest($endpoint, $method, $userId);

        // Request-Context für Error-Logging vorbereiten
        $requestContext = $this->buildRequestContext($request);

        try {
            // Request verarbeiten
            $response = $next($request);
            
            // Success-Metriken sammeln
            $this->collectSuccessMetrics($request, $response);
            
            // Monitoring beenden
            $this->monitor->endRequest($response->getStatusCode(), [
                'request_size' => $this->getRequestSize($request),
                'response_size' => $this->getResponseSize($response)
            ]);

            return $response;

        } catch (Throwable $error) {
            // Error-Logging mit umfangreichem Context
            $this->monitor->logError($error, $this->determineSeverity($error), $requestContext, $userId);
            
            // Error-Response erstellen
            $errorResponse = $this->createErrorResponse($error, $request);
            
            // Monitoring mit Error-Status beenden
            $this->monitor->endRequest($errorResponse->getStatusCode());
            
            // Error nicht schlucken - weiterwerfen für normale Error-Handler
            throw $error;
        }
    }

    /**
     * Prüft ob Request getrackt werden soll
     */
    private function shouldTrackRequest(Request $request): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }

        // Sampling-Rate prüfen
        if ($this->config['sample_rate'] < 1.0 && mt_rand() / mt_getrandmax() > $this->config['sample_rate']) {
            return false;
        }

        $uri = $request->getUri();

        // Ausgeschlossene Pfade prüfen
        foreach ($this->config['exclude_patterns'] as $pattern) {
            if (strpos($uri, $pattern) !== false) {
                return false;
            }
        }

        // Nur API-Requests tracken (optional)
        if ($this->config['track_only_api'] && !$this->isApiRequest($request)) {
            return false;
        }

        return true;
    }

    /**
     * Normalisiert Endpoint für konsistente Gruppierung
     */
    private function normalizeEndpoint(string $uri): string
    {
        // URL-Parameter entfernen
        $uri = parse_url($uri, PHP_URL_PATH) ?: $uri;
        
        // IDs durch Platzhalter ersetzen für bessere Aggregation
        $patterns = [
            '/\/\d+/' => '/{id}',                    // /playlists/123 -> /playlists/{id}
            '/\/[a-f0-9-]{36}/' => '/{uuid}',        // UUIDs
            '/\/[a-f0-9]{32}/' => '/{hash}',         // MD5 Hashes
        ];

        foreach ($patterns as $pattern => $replacement) {
            $uri = preg_replace($pattern, $replacement, $uri);
        }

        return $uri;
    }

    /**
     * Extrahiert User-ID aus Request
     */
    private function extractUserId(Request $request): ?int
    {
        // Aus Session
        if (isset($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }

        // Aus Request-Attributen (falls durch AuthMiddleware gesetzt)
        $userId = $request->getAttribute('user_id');
        return $userId ? (int) $userId : null;
    }

    /**
     * Erstellt Request-Context für Error-Logging
     */
    private function buildRequestContext(Request $request): array
    {
        $context = [
            'request_id' => uniqid('req_', true),
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'headers' => $this->sanitizeHeaders($request->getHeaders()),
            'query_params' => $_GET ?? [],
            'server_info' => [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                'request_time' => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true),
            ]
        ];

        // Request-Body (nur für bestimmte Content-Types und begrenzte Größe)
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'])) {
            $contentType = $request->header('Content-Type') ?? '';
            if (strpos($contentType, 'application/json') !== false) {
                $rawBody = file_get_contents('php://input');
                if (strlen($rawBody) <= $this->config['max_payload_log_size']) {
                    $context['request_body'] = $this->sanitizeRequestBody($rawBody);
                }
            }
        }

        return $context;
    }

    /**
     * Sammelt Success-Metriken
     */
    private function collectSuccessMetrics(Request $request, Response $response): void
    {
        // Cache-Treffer aus Response-Headers
        if ($response->hasHeader('X-Cache-Status')) {
            $cacheStatus = $response->getHeader('X-Cache-Status');
            if ($cacheStatus === 'HIT') {
                $this->monitor->incrementCacheHit();
            } else {
                $this->monitor->incrementCacheMiss();
            }
        }

        // Custom-Metriken aus Response-Headers
        if ($response->hasHeader('X-DB-Queries')) {
            $queryCount = (int) $response->getHeader('X-DB-Queries');
            $queryTime = (float) ($response->getHeader('X-DB-Time') ?? 0);
            
            for ($i = 0; $i < $queryCount; $i++) {
                $this->monitor->incrementDbQuery($queryTime / $queryCount);
            }
        }
    }

    /**
     * Bestimmt Error-Severity basierend auf Exception-Typ
     */
    private function determineSeverity(Throwable $error): string
    {
        $className = get_class($error);
        
        // Mapping von Exception-Types zu Severity-Levels
        $severityMap = [
            'Error' => 'critical',
            'ParseError' => 'critical', 
            'TypeError' => 'error',
            'ArgumentCountError' => 'error',
            'InvalidArgumentException' => 'warning',
            'RuntimeException' => 'error',
            'LogicException' => 'error',
            'BadMethodCallException' => 'error',
            'OutOfBoundsException' => 'warning',
            'PDOException' => 'critical',
        ];

        foreach ($severityMap as $type => $severity) {
            if (strpos($className, $type) !== false) {
                return $severity;
            }
        }

        // Default-Severity basierend auf HTTP-Status (falls verfügbar)
        if (method_exists($error, 'getStatusCode')) {
            $statusCode = $error->getStatusCode();
            if ($statusCode >= 500) return 'error';
            if ($statusCode >= 400) return 'warning';
        }

        return 'error';
    }

    /**
     * Erstellt Error-Response
     */
    private function createErrorResponse(Throwable $error, Request $request): Response
    {
        $response = new Response();
        
        // Status-Code bestimmen
        $statusCode = method_exists($error, 'getStatusCode') ? 
            $error->getStatusCode() : 500;

        // Development vs Production Response
        $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
        
        $errorData = [
            'error' => true,
            'message' => $isProduction ? 
                'Ein unerwarteter Fehler ist aufgetreten.' : 
                $error->getMessage(),
            'code' => $error->getCode(),
            'timestamp' => date('c')
        ];

        // In Development: Mehr Details
        if (!$isProduction) {
            $errorData['debug'] = [
                'type' => get_class($error),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => array_slice($error->getTrace(), 0, 5)
            ];
        }

        // Request-ID für Nachverfolgung
        $errorData['request_id'] = uniqid('err_', true);

        return $response->json($errorData, $statusCode);
    }

    /**
     * Hilfsmethoden
     */
    private function isApiRequest(Request $request): bool
    {
        return strpos($request->getUri(), '/api/') === 0;
    }

    private function getRequestSize(Request $request): int
    {
        // Schätzung der Request-Größe
        $size = strlen($request->getUri());
        $size += array_sum(array_map('strlen', $request->getHeaders()));
        
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'])) {
            $size += (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        }
        
        return $size;
    }

    private function getResponseSize(Response $response): int
    {
        // Response-Größe schätzen
        $content = $response->getContent();
        return $content ? strlen($content) : 0;
    }

    private function sanitizeHeaders(array $headers): array
    {
        // Sensitive Headers entfernen/maskieren
        $sensitive = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];
        
        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), $sensitive)) {
                $headers[$name] = '***masked***';
            }
        }
        
        return $headers;
    }

    private function sanitizeRequestBody(string $body): array
    {
        $data = json_decode($body, true);
        
        if (!is_array($data)) {
            return ['raw' => substr($body, 0, 200) . '...'];
        }

        // Sensitive Felder maskieren
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'api_key'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***masked***';
            }
        }

        return $data;
    }

    /**
     * Sammelt System-Metriken bei jedem Request (Sampling)
     */
    public function collectSystemMetrics(): void
    {
        // Nur bei jedem 10. Request System-Metriken sammeln
        if (mt_rand(1, 10) === 1) {
            $this->monitor->collectSystemMetrics();
        }
    }

    /**
     * Factory-Methode für API-Monitoring
     */
    public static function forApi(MonitorAgent $monitor): self
    {
        return new self($monitor, [
            'enabled' => true,
            'track_only_api' => true,
            'sample_rate' => 1.0,
            'exclude_patterns' => [
                '/api/health',
                '/api/metrics'
            ]
        ]);
    }

    /**
     * Factory-Methode für vollständiges Web-Monitoring
     */
    public static function forWeb(MonitorAgent $monitor): self
    {
        return new self($monitor, [
            'enabled' => true,
            'track_all_requests' => true,
            'sample_rate' => 0.1, // 10% Sampling für Web-Requests
            'exclude_patterns' => [
                '/assets/',
                '/favicon.ico',
                '/robots.txt',
                '.css',
                '.js',
                '.png',
                '.jpg',
                '.gif'
            ]
        ]);
    }
}