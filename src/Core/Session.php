<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session-Management-Klasse
 * 
 * @author 2Brands Media GmbH
 */
class Session
{
    private bool $started = false;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'name' => 'metropol_session',
            'lifetime' => 120, // Minuten
            'path' => '/',
            'domain' => null,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ], $config);
    }

    /**
     * Startet die Session
     */
    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Session-Konfiguration
        ini_set('session.name', $this->config['name']);
        ini_set('session.cookie_lifetime', (string) ($this->config['lifetime'] * 60));
        ini_set('session.cookie_path', $this->config['path']);
        ini_set('session.cookie_secure', $this->config['secure'] ? '1' : '0');
        ini_set('session.cookie_httponly', $this->config['httponly'] ? '1' : '0');
        ini_set('session.cookie_samesite', $this->config['samesite']);
        
        if ($this->config['domain']) {
            ini_set('session.cookie_domain', $this->config['domain']);
        }

        session_start();
        $this->started = true;

        // Session-Timeout prüfen
        $this->checkTimeout();
    }

    /**
     * Prüft ob die Session abgelaufen ist
     */
    private function checkTimeout(): void
    {
        $lastActivity = $this->get('_last_activity', 0);
        $timeout = $this->config['lifetime'] * 60;

        if ($lastActivity && (time() - $lastActivity > $timeout)) {
            $this->destroy();
            $this->start();
        }

        $this->set('_last_activity', time());
    }

    /**
     * Setzt einen Session-Wert
     */
    public function set(string $key, $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    /**
     * Gibt einen Session-Wert zurück
     */
    public function get(string $key, $default = null)
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Prüft ob ein Session-Wert existiert
     */
    public function has(string $key): bool
    {
        $this->start();
        return isset($_SESSION[$key]);
    }

    /**
     * Entfernt einen Session-Wert
     */
    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    /**
     * Setzt mehrere Session-Werte
     */
    public function put(array $values): void
    {
        $this->start();
        foreach ($values as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * Gibt alle Session-Werte zurück
     */
    public function all(): array
    {
        $this->start();
        return $_SESSION;
    }

    /**
     * Löscht alle Session-Werte
     */
    public function clear(): void
    {
        $this->start();
        $_SESSION = [];
    }

    /**
     * Flash-Messages setzen
     */
    public function flash(string $key, $value): void
    {
        $this->start();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Flash-Message abrufen und löschen
     */
    public function getFlash(string $key, $default = null)
    {
        $this->start();
        
        if (isset($_SESSION['_flash'][$key])) {
            $value = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $value;
        }
        
        return $default;
    }

    /**
     * Prüft ob eine Flash-Message existiert
     */
    public function hasFlash(string $key): bool
    {
        $this->start();
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Regeneriert die Session-ID
     */
    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    /**
     * Gibt die Session-ID zurück
     */
    public function getId(): string
    {
        $this->start();
        return session_id();
    }

    /**
     * Setzt die Session-ID
     */
    public function setId(string $id): void
    {
        if ($this->started) {
            throw new \Exception('Session bereits gestartet');
        }
        
        session_id($id);
    }

    /**
     * Beendet die Session
     */
    public function destroy(): void
    {
        $this->start();
        
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        session_destroy();
        $this->started = false;
    }

    /**
     * CSRF-Token generieren
     */
    public function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->set('_csrf_token', $token);
        return $token;
    }

    /**
     * CSRF-Token validieren
     */
    public function validateCsrfToken(string $token): bool
    {
        $sessionToken = $this->get('_csrf_token');
        return $sessionToken && hash_equals($sessionToken, $token);
    }

    /**
     * CSRF-Token abrufen
     */
    public function getCsrfToken(): string
    {
        if (!$this->has('_csrf_token')) {
            $this->generateCsrfToken();
        }
        
        return $this->get('_csrf_token');
    }
}