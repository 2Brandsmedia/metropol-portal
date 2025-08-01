<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Request-Klasse
 * 
 * @author 2Brands Media GmbH
 */
class Request
{
    private array $query;
    private array $request;
    private array $attributes;
    private array $cookies;
    private array $files;
    private array $server;
    private array $headers;
    private ?string $content = null;

    public function __construct(
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        $content = null
    ) {
        $this->query = $query;
        $this->request = $request;
        $this->attributes = $attributes;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->server = $server;
        $this->content = $content;
        $this->headers = $this->parseHeaders($server);
    }

    /**
     * Erstellt eine Request-Instanz aus globalen Variablen
     */
    public static function capture(): self
    {
        return new self(
            $_GET,
            $_POST,
            [],
            $_COOKIE,
            $_FILES,
            $_SERVER,
            file_get_contents('php://input')
        );
    }

    /**
     * Gibt die HTTP-Methode zurück
     */
    public function getMethod(): string
    {
        $method = $this->server['REQUEST_METHOD'] ?? 'GET';
        
        // Method Override für HTML-Formulare
        if ($method === 'POST') {
            if ($override = $this->request['_method'] ?? $this->headers['x-http-method-override'] ?? null) {
                $method = strtoupper($override);
            }
        }
        
        return $method;
    }

    /**
     * Gibt die Request-URI zurück
     */
    public function getUri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        
        // Query-String entfernen
        if ($pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        
        return $uri;
    }

    /**
     * Alias für getUri()
     */
    public function path(): string
    {
        return $this->getUri();
    }

    /**
     * Gibt den vollständigen URL zurück
     */
    public function getUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->getHost();
        $uri = $this->server['REQUEST_URI'] ?? '/';
        
        return $scheme . '://' . $host . $uri;
    }

    /**
     * Gibt den Host zurück
     */
    public function getHost(): string
    {
        return $this->headers['host'] ?? $this->server['SERVER_NAME'] ?? 'localhost';
    }

    /**
     * Prüft ob die Verbindung sicher ist
     */
    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || ($this->server['SERVER_PORT'] ?? 80) == 443
            || ($this->headers['x-forwarded-proto'] ?? '') === 'https';
    }

    /**
     * Gibt einen Query-Parameter zurück oder alle Query-Parameter
     */
    public function query(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->query;
        }
        
        return $this->query[$key] ?? $default;
    }

    /**
     * Gibt einen POST-Parameter zurück
     */
    public function post(string $key, $default = null)
    {
        return $this->request[$key] ?? $default;
    }

    /**
     * Gibt einen Parameter zurück (GET oder POST)
     */
    public function input(string $key, $default = null)
    {
        return $this->request[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Gibt alle Input-Daten zurück
     */
    public function all(): array
    {
        return array_merge($this->query, $this->request);
    }

    /**
     * Gibt nur bestimmte Input-Daten zurück
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * Gibt alle Input-Daten außer bestimmten zurück
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    /**
     * Prüft ob ein Input-Key existiert
     */
    public function has(string $key): bool
    {
        return isset($this->query[$key]) || isset($this->request[$key]);
    }

    /**
     * Gibt einen Cookie-Wert zurück
     */
    public function cookie(string $key, $default = null)
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Gibt einen Header zurück
     */
    public function header(string $key, $default = null)
    {
        $key = strtolower(str_replace('_', '-', $key));
        return $this->headers[$key] ?? $default;
    }

    /**
     * Gibt den Content-Type zurück
     */
    public function getContentType(): ?string
    {
        return $this->header('content-type');
    }

    /**
     * Prüft ob der Request JSON ist
     */
    public function isJson(): bool
    {
        $contentType = $this->getContentType();
        return $contentType !== null && str_contains($contentType, 'application/json');
    }

    /**
     * Prüft ob der Request AJAX ist
     */
    public function isAjax(): bool
    {
        return $this->header('x-requested-with') === 'XMLHttpRequest';
    }

    /**
     * Gibt den Request-Body zurück
     */
    public function getContent(): string
    {
        return $this->content ?? '';
    }

    /**
     * Gibt den Request-Body als JSON zurück
     */
    public function json(): array
    {
        if ($this->isJson()) {
            return json_decode($this->getContent(), true) ?? [];
        }
        
        return [];
    }

    /**
     * Gibt die Client-IP zurück
     */
    public function ip(): string
    {
        $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($keys as $key) {
            if (isset($this->server[$key])) {
                $ips = explode(',', $this->server[$key]);
                return trim($ips[0]);
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Gibt den User-Agent zurück
     */
    public function userAgent(): ?string
    {
        return $this->server['HTTP_USER_AGENT'] ?? null;
    }

    /**
     * Parst die Header aus den Server-Variablen
     */
    private function parseHeaders(array $server): array
    {
        $headers = [];
        
        foreach ($server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        
        return $headers;
    }

    /**
     * Setzt ein Attribut
     */
    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Gibt ein Attribut zurück
     */
    public function getAttribute(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }
}