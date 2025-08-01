<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Agents\AuthAgent;
use App\Validators\AuthValidator;
use Exception;

/**
 * Authentifizierungs-Controller
 * 
 * @author 2Brands Media GmbH
 */
class AuthController
{
    private AuthAgent $auth;
    private AuthValidator $validator;

    public function __construct(AuthAgent $auth, AuthValidator $validator)
    {
        $this->auth = $auth;
        $this->validator = $validator;
    }

    /**
     * Login-Endpunkt
     * POST /api/auth/login
     */
    public function login(Request $request): Response
    {
        $response = new Response();

        try {
            // Validierung
            $data = $request->json();
            $errors = $this->validator->validateLogin($data);
            
            if (!empty($errors)) {
                return $response->json([
                    'success' => false,
                    'errors' => $errors
                ], 422);
            }

            // Login durchführen
            $result = $this->auth->login(
                $data['email'],
                $data['password']
            );

            // Remember Me
            $rememberMe = $data['remember_me'] ?? false;
            if ($rememberMe) {
                // Cookie für 30 Tage setzen
                $response->cookie(
                    'remember_token',
                    $this->generateRememberToken($result['user']['id']),
                    43200 // 30 Tage
                );
            }

            // Audit-Log
            $this->logAuthEvent('login', $result['user']['id'], true);

            return $response->json([
                'success' => true,
                'user' => $result['user'],
                'token' => $result['token']
            ]);

        } catch (Exception $e) {
            // Fehlgeschlagener Login loggen
            $this->logAuthEvent('login_failed', null, false, [
                'email' => $data['email'] ?? '',
                'ip' => $request->ip()
            ]);

            return $response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Logout-Endpunkt
     * POST /api/auth/logout
     */
    public function logout(Request $request): Response
    {
        $response = new Response();

        try {
            // Benutzer-ID vor Logout speichern
            $userId = $this->auth->id();

            // Logout durchführen
            $this->auth->logout();

            // Remember Token löschen
            $response->cookie('remember_token', '', -1);

            // Audit-Log
            if ($userId) {
                $this->logAuthEvent('logout', $userId, true);
            }

            return $response->json([
                'success' => true,
                'message' => 'Erfolgreich abgemeldet'
            ]);

        } catch (Exception $e) {
            return $response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Status-Endpunkt
     * GET /api/auth/status
     */
    public function status(Request $request): Response
    {
        $response = new Response();

        if (!$this->auth->check()) {
            return $response->json([
                'authenticated' => false
            ]);
        }

        return $response->json([
            'authenticated' => true,
            'user' => $this->auth->user()
        ]);
    }

    /**
     * Token-Refresh-Endpunkt
     * POST /api/auth/refresh
     */
    public function refresh(Request $request): Response
    {
        $response = new Response();

        try {
            $token = $this->extractToken($request);
            
            if (!$token) {
                throw new Exception('Token fehlt');
            }

            // Token validieren und neuen generieren
            $user = $this->auth->validateToken($token);
            
            if (!$user) {
                throw new Exception('Ungültiger Token');
            }

            // Neuen Token generieren
            $newToken = $this->auth->generateJWT(['id' => $user['id']]);

            return $response->json([
                'success' => true,
                'token' => $newToken,
                'user' => $user
            ]);

        } catch (Exception $e) {
            return $response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Passwort vergessen Endpunkt
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(Request $request): Response
    {
        $response = new Response();

        try {
            $data = $request->json();
            
            // Validierung
            $errors = $this->validator->validateForgotPassword($data);
            
            if (!empty($errors)) {
                return $response->json([
                    'success' => false,
                    'errors' => $errors
                ], 422);
            }

            // Reset-Token generieren und per E-Mail senden
            $this->auth->initiatePasswordReset($data['email']);

            // Immer erfolgreiche Antwort (Security)
            return $response->json([
                'success' => true,
                'message' => 'Falls die E-Mail-Adresse existiert, wurde ein Reset-Link gesendet.'
            ]);

        } catch (Exception $e) {
            // Fehler loggen aber nicht an User weitergeben
            error_log('Password reset error: ' . $e->getMessage());
            
            return $response->json([
                'success' => true,
                'message' => 'Falls die E-Mail-Adresse existiert, wurde ein Reset-Link gesendet.'
            ]);
        }
    }

    /**
     * Passwort zurücksetzen Endpunkt
     * POST /api/auth/reset-password
     */
    public function resetPassword(Request $request): Response
    {
        $response = new Response();

        try {
            $data = $request->json();
            
            // Validierung
            $errors = $this->validator->validateResetPassword($data);
            
            if (!empty($errors)) {
                return $response->json([
                    'success' => false,
                    'errors' => $errors
                ], 422);
            }

            // Passwort zurücksetzen
            $this->auth->resetPassword($data['token'], $data['password']);

            // Audit-Log
            $this->logAuthEvent('password_reset', null, true);

            return $response->json([
                'success' => true,
                'message' => 'Passwort erfolgreich zurückgesetzt'
            ]);

        } catch (Exception $e) {
            return $response->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Extrahiert Token aus Request
     */
    private function extractToken(Request $request): ?string
    {
        // Aus Authorization Header
        $authHeader = $request->header('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Aus Query-Parameter (für Downloads etc.)
        return $request->query('token');
    }

    /**
     * Generiert Remember-Me Token
     */
    private function generateRememberToken(int $userId): string
    {
        return $this->auth->createRememberToken($userId);
    }

    /**
     * Loggt Auth-Events
     */
    private function logAuthEvent(string $action, ?int $userId, bool $success, array $extra = []): void
    {
        // TODO: In audit_log Tabelle schreiben
        error_log(sprintf(
            'Auth Event: %s | User: %s | Success: %s | Extra: %s',
            $action,
            $userId ?? 'anonymous',
            $success ? 'yes' : 'no',
            json_encode($extra)
        ));
    }
}