<?php

declare(strict_types=1);

namespace App\Validators;

/**
 * Authentifizierungs-Validator
 * 
 * @author 2Brands Media GmbH
 */
class AuthValidator
{
    /**
     * Validiert Login-Daten
     */
    public function validateLogin(array $data): array
    {
        $errors = [];

        // E-Mail
        if (empty($data['email'])) {
            $errors['email'] = 'E-Mail-Adresse ist erforderlich';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Ungültige E-Mail-Adresse';
        }

        // Passwort
        if (empty($data['password'])) {
            $errors['password'] = 'Passwort ist erforderlich';
        } elseif (strlen($data['password']) < 6) {
            $errors['password'] = 'Passwort muss mindestens 6 Zeichen lang sein';
        }

        return $errors;
    }

    /**
     * Validiert Registrierungs-Daten
     */
    public function validateRegistration(array $data): array
    {
        $errors = [];

        // Name
        if (empty($data['name'])) {
            $errors['name'] = 'Name ist erforderlich';
        } elseif (strlen($data['name']) < 2) {
            $errors['name'] = 'Name muss mindestens 2 Zeichen lang sein';
        } elseif (strlen($data['name']) > 255) {
            $errors['name'] = 'Name darf maximal 255 Zeichen lang sein';
        }

        // E-Mail
        if (empty($data['email'])) {
            $errors['email'] = 'E-Mail-Adresse ist erforderlich';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Ungültige E-Mail-Adresse';
        } elseif (strlen($data['email']) > 255) {
            $errors['email'] = 'E-Mail-Adresse darf maximal 255 Zeichen lang sein';
        }

        // Passwort
        if (empty($data['password'])) {
            $errors['password'] = 'Passwort ist erforderlich';
        } else {
            $passwordErrors = $this->validatePasswordStrength($data['password']);
            if (!empty($passwordErrors)) {
                $errors['password'] = $passwordErrors;
            }
        }

        // Passwort-Bestätigung
        if (empty($data['password_confirmation'])) {
            $errors['password_confirmation'] = 'Passwort-Bestätigung ist erforderlich';
        } elseif ($data['password'] !== $data['password_confirmation']) {
            $errors['password_confirmation'] = 'Passwörter stimmen nicht überein';
        }

        // Rolle (optional)
        if (!empty($data['role'])) {
            if (!in_array($data['role'], ['admin', 'employee'])) {
                $errors['role'] = 'Ungültige Rolle';
            }
        }

        // Sprache (optional)
        if (!empty($data['language'])) {
            if (!in_array($data['language'], ['de', 'en', 'tr'])) {
                $errors['language'] = 'Ungültige Sprache';
            }
        }

        return $errors;
    }

    /**
     * Validiert Passwort-Vergessen-Anfrage
     */
    public function validateForgotPassword(array $data): array
    {
        $errors = [];

        // E-Mail
        if (empty($data['email'])) {
            $errors['email'] = 'E-Mail-Adresse ist erforderlich';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Ungültige E-Mail-Adresse';
        }

        return $errors;
    }

    /**
     * Validiert Passwort-Reset
     */
    public function validateResetPassword(array $data): array
    {
        $errors = [];

        // Token
        if (empty($data['token'])) {
            $errors['token'] = 'Reset-Token ist erforderlich';
        } elseif (strlen($data['token']) !== 64) { // 32 Bytes hex = 64 Zeichen
            $errors['token'] = 'Ungültiges Reset-Token';
        }

        // Neues Passwort
        if (empty($data['password'])) {
            $errors['password'] = 'Neues Passwort ist erforderlich';
        } else {
            $passwordErrors = $this->validatePasswordStrength($data['password']);
            if (!empty($passwordErrors)) {
                $errors['password'] = $passwordErrors;
            }
        }

        // Passwort-Bestätigung
        if (empty($data['password_confirmation'])) {
            $errors['password_confirmation'] = 'Passwort-Bestätigung ist erforderlich';
        } elseif ($data['password'] !== $data['password_confirmation']) {
            $errors['password_confirmation'] = 'Passwörter stimmen nicht überein';
        }

        return $errors;
    }

    /**
     * Validiert Passwort-Änderung
     */
    public function validateChangePassword(array $data): array
    {
        $errors = [];

        // Aktuelles Passwort
        if (empty($data['current_password'])) {
            $errors['current_password'] = 'Aktuelles Passwort ist erforderlich';
        }

        // Neues Passwort
        if (empty($data['new_password'])) {
            $errors['new_password'] = 'Neues Passwort ist erforderlich';
        } else {
            $passwordErrors = $this->validatePasswordStrength($data['new_password']);
            if (!empty($passwordErrors)) {
                $errors['new_password'] = $passwordErrors;
            }

            // Prüfen ob neues Passwort != altes Passwort
            if (!empty($data['current_password']) && $data['current_password'] === $data['new_password']) {
                $errors['new_password'] = 'Neues Passwort muss sich vom aktuellen unterscheiden';
            }
        }

        // Passwort-Bestätigung
        if (empty($data['new_password_confirmation'])) {
            $errors['new_password_confirmation'] = 'Passwort-Bestätigung ist erforderlich';
        } elseif ($data['new_password'] !== $data['new_password_confirmation']) {
            $errors['new_password_confirmation'] = 'Passwörter stimmen nicht überein';
        }

        return $errors;
    }

    /**
     * Validiert Passwort-Stärke
     */
    private function validatePasswordStrength(string $password): array
    {
        $errors = [];

        // Mindestlänge
        if (strlen($password) < 8) {
            $errors[] = 'Mindestens 8 Zeichen';
        }

        // Maximallänge
        if (strlen($password) > 255) {
            $errors[] = 'Maximal 255 Zeichen';
        }

        // Komplexitäts-Anforderungen (optional, kann aktiviert werden)
        $hasLowercase = preg_match('/[a-z]/', $password);
        $hasUppercase = preg_match('/[A-Z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);
        $hasSpecial = preg_match('/[^a-zA-Z0-9]/', $password);

        // Für erhöhte Sicherheit können diese Checks aktiviert werden:
        /*
        if (!$hasLowercase) {
            $errors[] = 'Mindestens ein Kleinbuchstabe';
        }
        if (!$hasUppercase) {
            $errors[] = 'Mindestens ein Großbuchstabe';
        }
        if (!$hasNumber) {
            $errors[] = 'Mindestens eine Zahl';
        }
        if (!$hasSpecial) {
            $errors[] = 'Mindestens ein Sonderzeichen';
        }
        */

        // Einfache Passwörter verhindern
        $commonPasswords = [
            'password', 'passwort', '12345678', '123456789', 'qwertyui',
            'metropol', 'admin123', 'test1234', 'password1'
        ];

        if (in_array(strtolower($password), $commonPasswords)) {
            $errors[] = 'Passwort ist zu einfach';
        }

        return $errors;
    }

    /**
     * Validiert Profil-Update
     */
    public function validateProfileUpdate(array $data): array
    {
        $errors = [];

        // Name (optional)
        if (isset($data['name'])) {
            if (empty($data['name'])) {
                $errors['name'] = 'Name darf nicht leer sein';
            } elseif (strlen($data['name']) < 2) {
                $errors['name'] = 'Name muss mindestens 2 Zeichen lang sein';
            } elseif (strlen($data['name']) > 255) {
                $errors['name'] = 'Name darf maximal 255 Zeichen lang sein';
            }
        }

        // E-Mail (optional)
        if (isset($data['email'])) {
            if (empty($data['email'])) {
                $errors['email'] = 'E-Mail darf nicht leer sein';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Ungültige E-Mail-Adresse';
            }
        }

        // Telefon (optional)
        if (isset($data['phone']) && !empty($data['phone'])) {
            if (!preg_match('/^[\+\-\(\)\s0-9]+$/', $data['phone'])) {
                $errors['phone'] = 'Ungültige Telefonnummer';
            } elseif (strlen($data['phone']) > 50) {
                $errors['phone'] = 'Telefonnummer darf maximal 50 Zeichen lang sein';
            }
        }

        // Sprache (optional)
        if (isset($data['language'])) {
            if (!in_array($data['language'], ['de', 'en', 'tr'])) {
                $errors['language'] = 'Ungültige Sprache';
            }
        }

        return $errors;
    }
}