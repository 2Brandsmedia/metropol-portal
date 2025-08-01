<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Agents\AuthAgent;
use Closure;

/**
 * Authentifizierungs-Middleware
 * 
 * @author 2Brands Media GmbH
 */
class AuthMiddleware
{
    private AuthAgent $auth;
    private array $options;

    public function __construct(AuthAgent $auth, array $options = [])
    {
        $this->auth = $auth;
        $this->options = array_merge([
            'optional' => false,  // Route erfordert keine Authentifizierung
            'roles' => [],       // Erforderliche Rollen
            'permissions' => []  // Erforderliche Berechtigungen (für später)
        ], $options);
    }

    /**
     * Handle Request
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Token aus Request extrahieren
        $token = $this->extractToken($request);
        
        if ($token) {
            // Token validieren
            $user = $this->auth->validateToken($token);
            
            if ($user) {
                // Benutzer in Request speichern
                $request->setAttribute('user', $user);
                $request->setAttribute('user_id', $user['id']);
                
                // Rollen prüfen
                if (!empty($this->options['roles'])) {
                    if (!$this->hasRequiredRole($user)) {
                        return $this->createForbiddenResponse();
                    }
                }
                
                // Request fortsetzen
                return $next($request);
            }
        }

        // Session-basierte Authentifizierung prüfen
        if ($this->auth->check()) {
            $user = $this->auth->user();
            $request->setAttribute('user', $user);
            $request->setAttribute('user_id', $user['id']);
            
            // Rollen prüfen
            if (!empty($this->options['roles'])) {
                if (!$this->hasRequiredRole($user)) {
                    return $this->createForbiddenResponse();
                }
            }
            
            return $next($request);
        }

        // Optionale Authentifizierung
        if ($this->options['optional']) {
            return $next($request);
        }

        // Nicht authentifiziert
        return $this->createUnauthorizedResponse();
    }

    /**
     * Extrahiert Token aus Request
     */
    private function extractToken(Request $request): ?string
    {
        // Authorization Header (Bearer Token)
        $authHeader = $request->header('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        // Query Parameter (für Downloads etc.)
        $token = $request->query('token');
        if ($token) {
            return $token;
        }

        // Cookie (für Web-Anwendungen)
        $token = $request->cookie('auth_token');
        if ($token) {
            return $token;
        }

        return null;
    }

    /**
     * Prüft ob Benutzer erforderliche Rolle hat
     */
    private function hasRequiredRole(array $user): bool
    {
        $userRole = $user['role'] ?? null;
        
        if (!$userRole) {
            return false;
        }

        // Admin hat immer Zugriff
        if ($userRole === 'admin') {
            return true;
        }

        return in_array($userRole, $this->options['roles']);
    }

    /**
     * Erstellt Unauthorized Response
     */
    private function createUnauthorizedResponse(): Response
    {
        $response = new Response();
        
        // WWW-Authenticate Header für API
        $response->header('WWW-Authenticate', 'Bearer realm="Metropol Portal"');
        
        return $response->json([
            'error' => true,
            'message' => 'Authentifizierung erforderlich',
            'code' => 'UNAUTHORIZED'
        ], 401);
    }

    /**
     * Erstellt Forbidden Response
     */
    private function createForbiddenResponse(): Response
    {
        $response = new Response();
        
        return $response->json([
            'error' => true,
            'message' => 'Zugriff verweigert',
            'code' => 'FORBIDDEN'
        ], 403);
    }

    /**
     * Factory-Methode für optionale Authentifizierung
     */
    public static function optional(AuthAgent $auth): self
    {
        return new self($auth, ['optional' => true]);
    }

    /**
     * Factory-Methode für Rollen-basierte Authentifizierung
     */
    public static function withRoles(AuthAgent $auth, array $roles): self
    {
        return new self($auth, ['roles' => $roles]);
    }

    /**
     * Factory-Methode für Admin-only Routes
     */
    public static function adminOnly(AuthAgent $auth): self
    {
        return new self($auth, ['roles' => ['admin']]);
    }
}