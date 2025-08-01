<?php

declare(strict_types=1);

namespace App\Agents;

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

/**
 * AuthAgent - Authentifizierung und Autorisierung
 * 
 * Erfolgskriterium: Login-Response < 100ms
 * 
 * @author 2Brands Media GmbH
 */
class AuthAgent
{
    private Database $db;
    private Session $session;
    private Config $config;
    private ?array $user = null;

    public function __construct(Database $db, Session $session, Config $config)
    {
        $this->db = $db;
        $this->session = $session;
        $this->config = $config;
        
        // Aktuellen Benutzer aus Session laden
        $this->loadUserFromSession();
    }

    /**
     * Benutzer-Login
     * 
     * @throws Exception bei ungültigen Anmeldedaten
     */
    public function login(string $email, string $password): array
    {
        $startTime = microtime(true);

        // Benutzer aus Datenbank laden
        $user = $this->db->selectOne(
            'SELECT * FROM users WHERE email = ? AND is_active = 1',
            [$email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            throw new Exception('Ungültige Anmeldedaten');
        }

        // Session erstellen
        $this->createSession($user);

        // JWT-Token generieren
        $token = $this->generateJWT($user);

        // Last Login aktualisieren
        $this->db->update(
            'UPDATE users SET last_login_at = NOW() WHERE id = ?',
            [$user['id']]
        );

        // Performance-Check
        $duration = (microtime(true) - $startTime) * 1000;
        if ($duration > 100) {
            error_log("AuthAgent: Login dauerte {$duration}ms (Ziel: < 100ms)");
        }

        return [
            'user' => $this->sanitizeUser($user),
            'token' => $token
        ];
    }

    /**
     * Benutzer-Logout
     */
    public function logout(): void
    {
        $this->session->destroy();
        $this->user = null;
    }

    /**
     * Prüft ob ein Benutzer eingeloggt ist
     */
    public function check(): bool
    {
        return $this->user !== null;
    }

    /**
     * Gibt den aktuellen Benutzer zurück
     */
    public function user(): ?array
    {
        return $this->user ? $this->sanitizeUser($this->user) : null;
    }

    /**
     * Gibt die Benutzer-ID zurück
     */
    public function id(): ?int
    {
        return $this->user ? (int) $this->user['id'] : null;
    }

    /**
     * Prüft ob der Benutzer eine bestimmte Rolle hat
     */
    public function hasRole(string $role): bool
    {
        return $this->user && $this->user['role'] === $role;
    }

    /**
     * Prüft ob der Benutzer Admin ist
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * JWT-Token validieren
     */
    public function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode(
                $token,
                new Key($this->config->get('jwt.secret'), $this->config->get('jwt.algorithm'))
            );

            $userId = $decoded->sub ?? null;
            
            if (!$userId) {
                return null;
            }

            // Benutzer aus Datenbank laden
            $user = $this->db->selectOne(
                'SELECT * FROM users WHERE id = ? AND is_active = 1',
                [$userId]
            );

            if ($user) {
                $this->user = $user;
                return $this->sanitizeUser($user);
            }

        } catch (\Exception $e) {
            // Token ungültig oder abgelaufen
        }

        return null;
    }

    /**
     * Passwort ändern
     */
    public function changePassword(string $currentPassword, string $newPassword): void
    {
        if (!$this->user) {
            throw new Exception('Nicht authentifiziert');
        }

        if (!password_verify($currentPassword, $this->user['password'])) {
            throw new Exception('Aktuelles Passwort ist falsch');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $this->db->update(
            'UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?',
            [$hashedPassword, $this->user['id']]
        );
    }

    /**
     * Benutzer registrieren
     */
    public function register(array $data): array
    {
        // E-Mail bereits vorhanden?
        $existing = $this->db->selectOne(
            'SELECT id FROM users WHERE email = ?',
            [$data['email']]
        );

        if ($existing) {
            throw new Exception('E-Mail-Adresse bereits registriert');
        }

        // Passwort hashen
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

        // Benutzer erstellen
        $userId = $this->db->insert(
            'INSERT INTO users (name, email, password, role, language) VALUES (?, ?, ?, ?, ?)',
            [
                $data['name'],
                $data['email'],
                $data['password'],
                $data['role'] ?? 'employee',
                $data['language'] ?? 'de'
            ]
        );

        // Benutzer laden
        $user = $this->db->selectOne(
            'SELECT * FROM users WHERE id = ?',
            [$userId]
        );

        return $this->sanitizeUser($user);
    }

    /**
     * Session erstellen
     */
    private function createSession(array $user): void
    {
        $this->session->regenerate();
        $this->session->put([
            'user_id' => $user['id'],
            'user_role' => $user['role'],
            'user_name' => $user['name']
        ]);
        $this->user = $user;
    }

    /**
     * Benutzer aus Session laden
     */
    private function loadUserFromSession(): void
    {
        $userId = $this->session->get('user_id');
        
        if ($userId) {
            $this->user = $this->db->selectOne(
                'SELECT * FROM users WHERE id = ? AND is_active = 1',
                [$userId]
            );
            
            if (!$this->user) {
                $this->session->destroy();
            }
        }
    }

    /**
     * JWT-Token generieren
     */
    public function generateJWT(array $user): string
    {
        $payload = [
            'iss' => $this->config->get('app.url'),
            'sub' => $user['id'],
            'iat' => time(),
            'exp' => time() + $this->config->get('jwt.expire'),
            'role' => $user['role'],
            'name' => $user['name']
        ];

        return JWT::encode(
            $payload,
            $this->config->get('jwt.secret'),
            $this->config->get('jwt.algorithm')
        );
    }

    /**
     * Initiiert Password-Reset
     */
    public function initiatePasswordReset(string $email): void
    {
        // Benutzer suchen
        $user = $this->db->selectOne(
            'SELECT id, email, name FROM users WHERE email = ? AND is_active = 1',
            [$email]
        );

        if (!$user) {
            // Aus Sicherheitsgründen keinen Fehler werfen
            return;
        }

        // Reset-Token generieren
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Token in Datenbank speichern
        $this->db->insert(
            'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)',
            [$email, $token, $expiresAt]
        );

        // E-Mail senden (TODO: Mail-Service implementieren)
        error_log("Password reset token for {$email}: {$token}");
    }

    /**
     * Passwort zurücksetzen
     */
    public function resetPassword(string $token, string $newPassword): void
    {
        // Token validieren
        $reset = $this->db->selectOne(
            'SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used_at IS NULL',
            [$token]
        );

        if (!$reset) {
            throw new Exception('Ungültiger oder abgelaufener Reset-Token');
        }

        // Neues Passwort hashen
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Passwort aktualisieren
        $this->db->update(
            'UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?',
            [$hashedPassword, $reset['email']]
        );

        // Token als verwendet markieren
        $this->db->update(
            'UPDATE password_resets SET used_at = NOW() WHERE id = ?',
            [$reset['id']]
        );
    }

    /**
     * Remember-Me Token erstellen
     */
    public function createRememberToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $this->db->insert(
            'INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)',
            [$userId, $token, $expiresAt]
        );

        return $token;
    }

    /**
     * Mit Remember-Me Token anmelden
     */
    public function loginWithRememberToken(string $token): ?array
    {
        // Token validieren
        $remember = $this->db->selectOne(
            'SELECT user_id FROM remember_tokens WHERE token = ? AND expires_at > NOW()',
            [$token]
        );

        if (!$remember) {
            return null;
        }

        // Benutzer laden
        $user = $this->db->selectOne(
            'SELECT * FROM users WHERE id = ? AND is_active = 1',
            [$remember['user_id']]
        );

        if (!$user) {
            return null;
        }

        // Last used aktualisieren
        $this->db->update(
            'UPDATE remember_tokens SET last_used_at = NOW() WHERE token = ?',
            [$token]
        );

        // Session erstellen
        $this->createSession($user);

        return [
            'user' => $this->sanitizeUser($user),
            'token' => $this->generateJWT($user)
        ];
    }

    /**
     * Remember-Me Token löschen
     */
    public function deleteRememberToken(string $token): void
    {
        $this->db->delete(
            'DELETE FROM remember_tokens WHERE token = ?',
            [$token]
        );
    }

    /**
     * Bereinigt Benutzerdaten für die Ausgabe
     */
    private function sanitizeUser(array $user): array
    {
        unset($user['password']);
        return $user;
    }

    /**
     * CSRF-Token generieren
     */
    public function generateCsrfToken(): string
    {
        return $this->session->generateCsrfToken();
    }

    /**
     * CSRF-Token validieren
     */
    public function validateCsrfToken(string $token): bool
    {
        return $this->session->validateCsrfToken($token);
    }

    /**
     * CSRF-Token abrufen
     */
    public function getCsrfToken(): string
    {
        return $this->session->getCsrfToken();
    }
}