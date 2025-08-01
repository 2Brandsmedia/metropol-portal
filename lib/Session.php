<?php

declare(strict_types=1);

/**
 * Simple Session Manager
 * 
 * Session-Verwaltung ohne externe Dependencies
 * 
 * @author 2Brands Media GmbH
 */
class Session
{
    private static bool $started = false;
    private static array $config = [
        'lifetime' => 7200,        // 2 Stunden
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    
    /**
     * Konfiguriert die Session
     */
    public static function configure(array $config): void
    {
        self::$config = array_merge(self::$config, $config);
    }
    
    /**
     * Startet die Session
     */
    public static function start(): bool
    {
        if (self::$started) {
            return true;
        }
        
        if (headers_sent()) {
            return false;
        }
        
        // Session-Cookie-Parameter setzen
        $cookieParams = [
            'lifetime' => self::$config['lifetime'],
            'path' => self::$config['path'],
            'domain' => self::$config['domain'],
            'secure' => self::$config['secure'],
            'httponly' => self::$config['httponly'],
            'samesite' => self::$config['samesite']
        ];
        
        session_set_cookie_params($cookieParams);
        
        // Session-Name setzen
        session_name('metropol_session');
        
        // Session starten
        if (session_start()) {
            self::$started = true;
            
            // Regeneriere Session-ID bei Bedarf
            if (!self::has('_session_regenerated')) {
                self::regenerate();
                self::set('_session_regenerated', time());
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Setzt einen Session-Wert
     */
    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    /**
     * Holt einen Session-Wert
     */
    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Prüft ob ein Session-Wert existiert
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    /**
     * Löscht einen Session-Wert
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }
    
    /**
     * Löscht alle Session-Werte
     */
    public static function clear(): void
    {
        self::start();
        $_SESSION = [];
    }
    
    /**
     * Zerstört die Session
     */
    public static function destroy(): void
    {
        self::start();
        
        // Session-Daten löschen
        $_SESSION = [];
        
        // Session-Cookie löschen
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
        
        // Session zerstören
        session_destroy();
        self::$started = false;
    }
    
    /**
     * Regeneriert die Session-ID
     */
    public static function regenerate(bool $deleteOld = true): bool
    {
        self::start();
        return session_regenerate_id($deleteOld);
    }
    
    /**
     * Flash-Messages setzen
     */
    public static function flash(string $key, $value): void
    {
        self::set('_flash_' . $key, $value);
    }
    
    /**
     * Flash-Message abrufen und löschen
     */
    public static function getFlash(string $key, $default = null)
    {
        $flashKey = '_flash_' . $key;
        $value = self::get($flashKey, $default);
        self::remove($flashKey);
        return $value;
    }
    
    /**
     * Prüft ob Flash-Message existiert
     */
    public static function hasFlash(string $key): bool
    {
        return self::has('_flash_' . $key);
    }
    
    /**
     * CSRF-Token generieren
     */
    public static function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        self::set('_csrf_token', $token);
        return $token;
    }
    
    /**
     * CSRF-Token validieren
     */
    public static function validateCsrfToken(string $token): bool
    {
        $sessionToken = self::get('_csrf_token');
        return $sessionToken !== null && hash_equals($sessionToken, $token);
    }
    
    /**
     * Benutzer einloggen
     */
    public static function login(array $userData): void
    {
        self::regenerate();
        self::set('user', $userData);
        self::set('logged_in', true);
        self::set('login_time', time());
    }
    
    /**
     * Benutzer ausloggen
     */
    public static function logout(): void
    {
        self::remove('user');
        self::remove('logged_in');
        self::remove('login_time');
        self::regenerate();
    }
    
    /**
     * Prüft ob Benutzer eingeloggt ist
     */
    public static function isLoggedIn(): bool
    {
        return self::get('logged_in', false) === true;
    }
    
    /**
     * Holt die Benutzer-Daten
     */
    public static function getUser(): ?array
    {
        return self::get('user');
    }
    
    /**
     * Holt die Benutzer-ID
     */
    public static function getUserId(): ?int
    {
        $user = self::getUser();
        return $user ? (int)($user['id'] ?? 0) : null;
    }
    
    /**
     * Prüft ob Session abgelaufen ist
     */
    public static function isExpired(): bool
    {
        $loginTime = self::get('login_time', 0);
        $maxLifetime = self::$config['lifetime'];
        
        return (time() - $loginTime) > $maxLifetime;
    }
    
    /**
     * Erneuert die Session-Zeit
     */
    public static function touch(): void
    {
        self::set('last_activity', time());
    }
}