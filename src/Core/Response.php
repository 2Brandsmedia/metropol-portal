<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Response-Klasse
 * 
 * @author 2Brands Media GmbH
 */
class Response
{
    private string $content = '';
    private int $statusCode = 200;
    private array $headers = [];
    private array $cookies = [];

    private array $statusTexts = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable'
    ];

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Setzt den Response-Content
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Gibt den Response-Content zurück
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Setzt den Status-Code
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Gibt den Status-Code zurück
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Setzt einen Header
     */
    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Setzt mehrere Header
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Gibt alle Header zurück
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Setzt ein Cookie
     */
    public function cookie(
        string $name,
        string $value,
        int $minutes = 0,
        string $path = '/',
        ?string $domain = null,
        bool $secure = true,
        bool $httpOnly = true,
        ?string $sameSite = 'Lax'
    ): self {
        $this->cookies[] = [
            'name' => $name,
            'value' => $value,
            'expire' => $minutes > 0 ? time() + ($minutes * 60) : 0,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite
        ];
        
        return $this;
    }

    /**
     * Erstellt eine JSON-Response
     */
    public function json($data, int $statusCode = 200): self
    {
        $this->setStatusCode($statusCode);
        $this->header('Content-Type', 'application/json');
        $this->setContent(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        
        return $this;
    }

    /**
     * Erstellt eine Redirect-Response
     */
    public function redirect(string $url, int $statusCode = 302): self
    {
        $this->setStatusCode($statusCode);
        $this->header('Location', $url);
        
        return $this;
    }

    /**
     * Erstellt eine Download-Response
     */
    public function download(string $file, ?string $name = null): self
    {
        if (!file_exists($file)) {
            throw new \Exception("Datei nicht gefunden: {$file}");
        }

        $name = $name ?? basename($file);
        
        $this->header('Content-Description', 'File Transfer');
        $this->header('Content-Type', 'application/octet-stream');
        $this->header('Content-Disposition', 'attachment; filename="' . $name . '"');
        $this->header('Content-Transfer-Encoding', 'binary');
        $this->header('Content-Length', (string) filesize($file));
        $this->header('Cache-Control', 'must-revalidate');
        $this->header('Pragma', 'public');
        $this->header('Expires', '0');
        
        $this->setContent(file_get_contents($file));
        
        return $this;
    }

    /**
     * Erstellt eine Datei-Response
     */
    public function file(string $file, array $headers = []): self
    {
        if (!file_exists($file)) {
            throw new \Exception("Datei nicht gefunden: {$file}");
        }

        $mimeType = mime_content_type($file);
        
        $this->header('Content-Type', $mimeType);
        $this->header('Content-Length', (string) filesize($file));
        $this->withHeaders($headers);
        
        $this->setContent(file_get_contents($file));
        
        return $this;
    }

    /**
     * Sendet die Response
     */
    public function send(): void
    {
        // Status-Code senden
        $statusText = $this->statusTexts[$this->statusCode] ?? 'Unknown';
        header("HTTP/1.1 {$this->statusCode} {$statusText}");

        // Header senden
        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }

        // Cookies senden
        foreach ($this->cookies as $cookie) {
            setcookie(
                $cookie['name'],
                $cookie['value'],
                $cookie['expire'],
                $cookie['path'],
                $cookie['domain'],
                $cookie['secure'],
                $cookie['httponly']
            );
        }

        // Content senden
        echo $this->content;
    }

    /**
     * Setzt einen Cache-Header
     */
    public function cache(int $minutes): self
    {
        $seconds = $minutes * 60;
        
        $this->header('Cache-Control', "public, max-age={$seconds}");
        $this->header('Expires', gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');
        
        return $this;
    }

    /**
     * Verhindert Caching
     */
    public function noCache(): self
    {
        $this->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->header('Pragma', 'no-cache');
        $this->header('Expires', '0');
        
        return $this;
    }
}