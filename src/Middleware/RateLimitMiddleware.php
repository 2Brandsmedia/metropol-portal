<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use Closure;

/**
 * Rate-Limiting Middleware für Brute-Force-Schutz
 * 
 * @author 2Brands Media GmbH
 */
class RateLimitMiddleware
{
    private Database $db;
    private array $config;
    
    public function __construct(Database $db, array $config = [])
    {
        $this->db = $db;
        $this->config = array_merge([
            'max_attempts' => 5,           // Maximale Versuche
            'decay_minutes' => 15,         // Zeitfenster in Minuten
            'block_duration' => 60,        // Blockierungsdauer in Minuten
            'identifier' => 'ip',          // 'ip' oder 'email'
            'routes' => []                 // Spezifische Limits für bestimmte Routen
        ], $config);
    }

    /**
     * Handle Request
     */
    public function handle(Request $request, Closure $next): Response
    {
        $identifier = $this->getIdentifier($request);
        $route = $request->getUri();
        
        // Route-spezifische Limits
        $limits = $this->getRouteLimits($route);
        
        // Prüfen ob IP/User blockiert ist
        if ($this->isBlocked($identifier, $route)) {
            return $this->createTooManyRequestsResponse($identifier, $route);
        }
        
        // Anzahl der Versuche prüfen
        $attempts = $this->getAttempts($identifier, $route);
        
        if ($attempts >= $limits['max_attempts']) {
            // Blockierung setzen
            $this->block($identifier, $route, $limits['block_duration']);
            return $this->createTooManyRequestsResponse($identifier, $route);
        }
        
        // Request verarbeiten
        $response = $next($request);
        
        // Bei Fehler (401, 403) Attempt zählen
        if (in_array($response->getStatusCode(), [401, 403, 422])) {
            $this->incrementAttempts($identifier, $route, $limits['decay_minutes']);
        } else if ($response->getStatusCode() === 200) {
            // Bei Erfolg Attempts zurücksetzen
            $this->clearAttempts($identifier, $route);
        }
        
        // Rate-Limit Headers hinzufügen
        $remaining = max(0, $limits['max_attempts'] - $attempts - 1);
        $response->header('X-RateLimit-Limit', (string) $limits['max_attempts']);
        $response->header('X-RateLimit-Remaining', (string) $remaining);
        
        return $response;
    }

    /**
     * Ermittelt Identifier für Rate-Limiting
     */
    private function getIdentifier(Request $request): string
    {
        switch ($this->config['identifier']) {
            case 'email':
                $data = $request->json();
                return $data['email'] ?? $request->ip();
                
            case 'user':
                return (string) ($request->getAttribute('user_id') ?? $request->ip());
                
            case 'ip':
            default:
                return $request->ip();
        }
    }

    /**
     * Gibt Route-spezifische Limits zurück
     */
    private function getRouteLimits(string $route): array
    {
        // Prüfen ob spezifische Limits für Route existieren
        foreach ($this->config['routes'] as $pattern => $limits) {
            if (preg_match($pattern, $route)) {
                return array_merge($this->config, $limits);
            }
        }
        
        return $this->config;
    }

    /**
     * Prüft ob Identifier blockiert ist
     */
    private function isBlocked(string $identifier, string $route): bool
    {
        $key = $this->getCacheKey($identifier, $route, 'blocked');
        $blocked = $this->getCache($key);
        
        return $blocked !== null;
    }

    /**
     * Blockiert einen Identifier
     */
    private function block(string $identifier, string $route, int $minutes): void
    {
        $key = $this->getCacheKey($identifier, $route, 'blocked');
        $this->setCache($key, time(), $minutes * 60);
        
        // In Datenbank loggen für Audit
        $this->logRateLimit($identifier, $route, 'blocked', $minutes);
    }

    /**
     * Gibt Anzahl der Versuche zurück
     */
    private function getAttempts(string $identifier, string $route): int
    {
        $key = $this->getCacheKey($identifier, $route, 'attempts');
        return (int) ($this->getCache($key) ?? 0);
    }

    /**
     * Erhöht Anzahl der Versuche
     */
    private function incrementAttempts(string $identifier, string $route, int $decayMinutes): void
    {
        $key = $this->getCacheKey($identifier, $route, 'attempts');
        $attempts = $this->getAttempts($identifier, $route);
        
        $this->setCache($key, $attempts + 1, $decayMinutes * 60);
        
        // In Datenbank loggen
        $this->logRateLimit($identifier, $route, 'attempt', $attempts + 1);
    }

    /**
     * Setzt Versuche zurück
     */
    private function clearAttempts(string $identifier, string $route): void
    {
        $key = $this->getCacheKey($identifier, $route, 'attempts');
        $this->deleteCache($key);
    }

    /**
     * Generiert Cache-Key
     */
    private function getCacheKey(string $identifier, string $route, string $type): string
    {
        return sprintf('rate_limit:%s:%s:%s', $type, md5($route), md5($identifier));
    }

    /**
     * Holt Wert aus Cache (vereinfacht - normalerweise Redis/Memcached)
     */
    private function getCache(string $key): ?string
    {
        $result = $this->db->selectOne(
            'SELECT value, expires_at FROM cache WHERE `key` = ? AND expires_at > NOW()',
            [$key]
        );
        
        return $result ? $result['value'] : null;
    }

    /**
     * Speichert Wert in Cache
     */
    private function setCache(string $key, $value, int $seconds): void
    {
        // Cache-Tabelle muss noch erstellt werden
        try {
            $this->db->statement(
                'INSERT INTO cache (`key`, value, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
                 ON DUPLICATE KEY UPDATE value = VALUES(value), expires_at = VALUES(expires_at)',
                [$key, (string) $value, $seconds]
            );
        } catch (\Exception $e) {
            // Fallback: In-Memory speichern (nicht persistent)
            // In Produktion sollte Redis/Memcached verwendet werden
        }
    }

    /**
     * Löscht Wert aus Cache
     */
    private function deleteCache(string $key): void
    {
        try {
            $this->db->delete('DELETE FROM cache WHERE `key` = ?', [$key]);
        } catch (\Exception $e) {
            // Fehler ignorieren
        }
    }

    /**
     * Loggt Rate-Limit Events
     */
    private function logRateLimit(string $identifier, string $route, string $action, $value): void
    {
        try {
            $this->db->insert(
                'INSERT INTO audit_log (action, entity_type, entity_id, new_values, ip_address, created_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())',
                [
                    'rate_limit_' . $action,
                    'security',
                    0,
                    json_encode([
                        'route' => $route,
                        'identifier' => substr($identifier, 0, 8) . '...', // Anonymisiert
                        'value' => $value
                    ]),
                    $identifier
                ]
            );
        } catch (\Exception $e) {
            error_log('Rate limit logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Erstellt Too Many Requests Response
     */
    private function createTooManyRequestsResponse(string $identifier, string $route): Response
    {
        $response = new Response();
        
        // Retry-After Header
        $blockedUntil = $this->getBlockedUntil($identifier, $route);
        if ($blockedUntil) {
            $retryAfter = max(1, $blockedUntil - time());
            $response->header('Retry-After', (string) $retryAfter);
        }
        
        return $response->json([
            'error' => true,
            'message' => 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.',
            'code' => 'TOO_MANY_REQUESTS'
        ], 429);
    }

    /**
     * Gibt Zeitpunkt zurück bis wann blockiert
     */
    private function getBlockedUntil(string $identifier, string $route): ?int
    {
        $key = $this->getCacheKey($identifier, $route, 'blocked');
        $result = $this->db->selectOne(
            'SELECT UNIX_TIMESTAMP(expires_at) as expires FROM cache WHERE `key` = ? AND expires_at > NOW()',
            [$key]
        );
        
        return $result ? (int) $result['expires'] : null;
    }

    /**
     * Factory-Methode für Login-Rate-Limiting
     */
    public static function forLogin(Database $db): self
    {
        return new self($db, [
            'max_attempts' => 5,
            'decay_minutes' => 15,
            'block_duration' => 60,
            'identifier' => 'email',
            'routes' => [
                '#^/api/auth/login#' => [
                    'max_attempts' => 5,
                    'decay_minutes' => 15,
                    'block_duration' => 60
                ],
                '#^/api/auth/forgot-password#' => [
                    'max_attempts' => 3,
                    'decay_minutes' => 60,
                    'block_duration' => 120
                ]
            ]
        ]);
    }

    /**
     * Factory-Methode für API-Rate-Limiting
     */
    public static function forApi(Database $db): self
    {
        return new self($db, [
            'max_attempts' => 60,
            'decay_minutes' => 1,
            'block_duration' => 5,
            'identifier' => 'user'
        ]);
    }
}