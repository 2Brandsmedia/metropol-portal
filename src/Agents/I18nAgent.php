<?php

declare(strict_types=1);

namespace App\Agents;

use App\Core\Config;
use App\Core\Session;
use Exception;

/**
 * I18nAgent - Erweiterte Internationalisierung und Übersetzungsverwaltung
 * 
 * Erfolgskriterium: 100% Übersetzungsabdeckung
 * Features:
 * - Automatische Übersetzungsfile-Überwachung
 * - Konsistenzprüfung zwischen Sprachen
 * - Erkennung fehlender/ungenutzter Übersetzungsschlüssel  
 * - Automatische Synchronisation und Stub-Generierung
 * - Qualitätssicherung mit Berichten und Metriken
 * 
 * @author 2Brands Media GmbH
 */
class I18nAgent
{
    private Config $config;
    private Session $session;
    private string $currentLanguage;
    private array $translations = [];
    private array $availableLanguages = ['de', 'en', 'tr'];
    private string $defaultLanguage = 'de';
    private array $loadedFiles = [];
    
    // Neue Properties für erweiterte Funktionalität
    private string $langPath;
    private array $translationCache = [];
    private array $keyUsageCache = [];
    private array $inconsistencies = [];
    private bool $maintenanceMode = false;

    public function __construct(Config $config, Session $session)
    {
        $this->config = $config;
        $this->session = $session;
        $this->langPath = dirname(__DIR__, 2) . '/lang';
        
        // Aktuelle Sprache bestimmen
        $this->detectLanguage();
        
        // Übersetzungen laden
        $this->loadTranslations();
        
        // Automatische Wartung initialisieren
        $this->initializeMaintenanceSystem();
    }

    /**
     * Gibt eine Übersetzung zurück
     */
    public function translate(string $key, array $replacements = []): string
    {
        $translation = $this->get($key);
        
        // Platzhalter ersetzen
        foreach ($replacements as $placeholder => $value) {
            $translation = str_replace(':' . $placeholder, (string) $value, $translation);
        }
        
        return $translation;
    }

    /**
     * Alias für translate()
     */
    public function t(string $key, array $replacements = []): string
    {
        return $this->translate($key, $replacements);
    }

    /**
     * Gibt eine Übersetzung zurück (ohne Platzhalter)
     */
    public function get(string $key, ?string $default = null): string
    {
        $keys = explode('.', $key);
        $value = $this->translations;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                // Fallback zur Standard-Sprache
                if ($this->currentLanguage !== $this->defaultLanguage) {
                    return $this->getFallback($key, $default);
                }
                
                // Warnung loggen
                error_log("I18nAgent: Übersetzung nicht gefunden: {$key} ({$this->currentLanguage})");
                
                return $default ?? $key;
            }
            $value = $value[$k];
        }

        return is_string($value) ? $value : ($default ?? $key);
    }

    /**
     * Setzt die aktuelle Sprache
     */
    public function setLanguage(string $language): void
    {
        if (!in_array($language, $this->availableLanguages)) {
            throw new Exception("Sprache '{$language}' nicht verfügbar");
        }

        $this->currentLanguage = $language;
        
        // In Session speichern
        $this->session->set('language', $language);
        
        // Cookie setzen (1 Jahr)
        setcookie('lang', $language, time() + 365 * 24 * 60 * 60, '/', null, true, true);
        
        // Übersetzungen neu laden
        $this->loadTranslations();
    }

    /**
     * Gibt die aktuelle Sprache zurück
     */
    public function getLanguage(): string
    {
        return $this->currentLanguage;
    }

    /**
     * Gibt alle verfügbaren Sprachen zurück
     */
    public function getAvailableLanguages(): array
    {
        return $this->availableLanguages;
    }

    /**
     * Prüft ob eine Sprache verfügbar ist
     */
    public function hasLanguage(string $language): bool
    {
        return in_array($language, $this->availableLanguages);
    }

    /**
     * Gibt Sprach-Informationen zurück
     */
    public function getLanguageInfo(): array
    {
        return [
            'current' => $this->currentLanguage,
            'available' => $this->availableLanguages,
            'default' => $this->defaultLanguage,
            'names' => [
                'de' => 'Deutsch',
                'en' => 'English',
                'tr' => 'Türkçe'
            ]
        ];
    }

    /**
     * Formatiert ein Datum
     */
    public function formatDate(\DateTime $date, string $format = 'default'): string
    {
        $formats = [
            'de' => [
                'default' => 'd.m.Y',
                'time' => 'H:i',
                'datetime' => 'd.m.Y H:i',
                'long' => 'j. F Y'
            ],
            'en' => [
                'default' => 'm/d/Y',
                'time' => 'h:i A',
                'datetime' => 'm/d/Y h:i A',
                'long' => 'F j, Y'
            ],
            'tr' => [
                'default' => 'd.m.Y',
                'time' => 'H:i',
                'datetime' => 'd.m.Y H:i',
                'long' => 'j F Y'
            ]
        ];

        $formatString = $formats[$this->currentLanguage][$format] ?? $formats[$this->currentLanguage]['default'];
        
        return $date->format($formatString);
    }

    /**
     * Formatiert eine Zahl
     */
    public function formatNumber(float $number, int $decimals = 0): string
    {
        $separators = [
            'de' => [',', '.'],
            'en' => ['.', ','],
            'tr' => [',', '.']
        ];

        [$decimalSep, $thousandsSep] = $separators[$this->currentLanguage];
        
        return number_format($number, $decimals, $decimalSep, $thousandsSep);
    }

    /**
     * Lädt alle Übersetzungen für JSON-Export
     */
    public function getAllTranslations(): array
    {
        return $this->translations;
    }

    /**
     * Prüft die Übersetzungsabdeckung
     */
    public function checkCoverage(): array
    {
        $coverage = [];
        $baseKeys = $this->getTranslationKeys($this->defaultLanguage);

        foreach ($this->availableLanguages as $lang) {
            if ($lang === $this->defaultLanguage) {
                $coverage[$lang] = 100.0;
                continue;
            }

            $langKeys = $this->getTranslationKeys($lang);
            $missing = array_diff($baseKeys, $langKeys);
            
            $coverage[$lang] = count($baseKeys) > 0 
                ? round((1 - count($missing) / count($baseKeys)) * 100, 2)
                : 100.0;
        }

        return $coverage;
    }

    /**
     * Erkennt die Sprache
     */
    private function detectLanguage(): void
    {
        // 1. Session prüfen
        $language = $this->session->get('language');
        
        // 2. Cookie prüfen
        if (!$language && isset($_COOKIE['lang'])) {
            $language = $_COOKIE['lang'];
        }
        
        // 3. Browser-Sprache prüfen
        if (!$language && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $language = $this->detectBrowserLanguage();
        }
        
        // 4. Standard-Sprache verwenden
        if (!$language || !$this->hasLanguage($language)) {
            $language = $this->defaultLanguage;
        }
        
        $this->currentLanguage = $language;
    }

    /**
     * Erkennt die Browser-Sprache
     */
    private function detectBrowserLanguage(): ?string
    {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        
        foreach ($this->availableLanguages as $lang) {
            if (stripos($acceptLanguage, $lang) !== false) {
                return $lang;
            }
        }
        
        return null;
    }

    /**
     * Lädt Übersetzungen
     */
    private function loadTranslations(): void
    {
        $langPath = dirname(__DIR__, 2) . '/lang/' . $this->currentLanguage . '.json';
        
        if (!file_exists($langPath)) {
            throw new Exception("Sprachdatei nicht gefunden: {$langPath}");
        }
        
        $content = file_get_contents($langPath);
        $this->translations = json_decode($content, true) ?? [];
        
        $this->loadedFiles[$this->currentLanguage] = $langPath;
    }

    /**
     * Fallback zur Standard-Sprache
     */
    private function getFallback(string $key, ?string $default): string
    {
        if (!isset($this->loadedFiles[$this->defaultLanguage])) {
            $langPath = dirname(__DIR__, 2) . '/lang/' . $this->defaultLanguage . '.json';
            $content = file_get_contents($langPath);
            $fallbackTranslations = json_decode($content, true) ?? [];
        } else {
            $fallbackTranslations = $this->translations;
        }
        
        $keys = explode('.', $key);
        $value = $fallbackTranslations;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default ?? $key;
            }
            $value = $value[$k];
        }
        
        return is_string($value) ? $value : ($default ?? $key);
    }

    /**
     * Extrahiert alle Übersetzungs-Schlüssel
     */
    private function getTranslationKeys(string $language): array
    {
        $langPath = dirname(__DIR__, 2) . '/lang/' . $language . '.json';
        
        if (!file_exists($langPath)) {
            return [];
        }
        
        $content = file_get_contents($langPath);
        $translations = json_decode($content, true) ?? [];
        
        return $this->extractKeys($translations);
    }

    /**
     * Extrahiert Schlüssel rekursiv
     */
    private function extractKeys(array $array, string $prefix = ''): array
    {
        $keys = [];
        
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value)) {
                $keys = array_merge($keys, $this->extractKeys($value, $fullKey));
            } else {
                $keys[] = $fullKey;
            }
        }
        
        return $keys;
    }

    // ==================== NEUE ERWEITERTE FUNKTIONALITÄT ====================

    /**
     * Initialisiert das automatische Wartungssystem
     */
    private function initializeMaintenanceSystem(): void
    {
        // Prüfung nur alle 5 Minuten durchführen (Cache)
        $cacheKey = 'i18n_last_maintenance_check';
        $lastCheck = $this->session->get($cacheKey, 0);
        
        if (time() - $lastCheck > 300) { // 5 Minuten
            $this->performMaintenanceCheck();
            $this->session->set($cacheKey, time());
        }
    }

    /**
     * Führt eine vollständige Wartungsprüfung durch
     */
    public function performMaintenanceCheck(): array
    {
        $this->maintenanceMode = true;
        
        $results = [
            'consistency_check' => $this->checkTranslationConsistency(),
            'missing_keys' => $this->findMissingTranslationKeys(),
            'unused_keys' => $this->findUnusedTranslationKeys(),
            'structural_diff' => $this->checkStructuralDifferences(),
            'placeholder_validation' => $this->validatePlaceholders(),
            'coverage_report' => $this->generateCoverageReport(),
            'recommendations' => $this->generateRecommendations()
        ];
        
        $this->inconsistencies = array_merge(
            $results['missing_keys'], 
            $results['structural_diff'], 
            $results['placeholder_validation']
        );
        
        $this->maintenanceMode = false;
        
        return $results;
    }

    /**
     * Prüft Konsistenz zwischen allen Übersetzungsdateien
     */
    public function checkTranslationConsistency(): array
    {
        $issues = [];
        $baseTranslations = $this->loadLanguageFile($this->defaultLanguage);
        $baseKeys = $this->extractKeys($baseTranslations);
        
        foreach ($this->availableLanguages as $lang) {
            if ($lang === $this->defaultLanguage) continue;
            
            $langTranslations = $this->loadLanguageFile($lang);
            $langKeys = $this->extractKeys($langTranslations);
            
            // Fehlende Schlüssel
            $missing = array_diff($baseKeys, $langKeys);
            if (!empty($missing)) {
                $issues[$lang]['missing_keys'] = $missing;
            }
            
            // Zusätzliche Schlüssel (möglicherweise verwaist)
            $extra = array_diff($langKeys, $baseKeys);
            if (!empty($extra)) {
                $issues[$lang]['extra_keys'] = $extra;
            }
            
            // Strukturelle Unterschiede
            $structuralDiff = $this->compareStructure($baseTranslations, $langTranslations);
            if (!empty($structuralDiff)) {
                $issues[$lang]['structural_differences'] = $structuralDiff;
            }
        }
        
        return $issues;
    }

    /**
     * Findet fehlende Übersetzungsschlüssel
     */
    public function findMissingTranslationKeys(): array
    {
        $baseKeys = $this->extractKeys($this->loadLanguageFile($this->defaultLanguage));
        $missing = [];
        
        foreach ($this->availableLanguages as $lang) {
            if ($lang === $this->defaultLanguage) continue;
            
            $langKeys = $this->extractKeys($this->loadLanguageFile($lang));
            $missingInLang = array_diff($baseKeys, $langKeys);
            
            if (!empty($missingInLang)) {
                $missing[$lang] = $missingInLang;
            }
        }
        
        return $missing;
    }

    /**
     * Findet ungenutzte Übersetzungsschlüssel im Code
     */
    public function findUnusedTranslationKeys(): array
    {
        $allKeys = $this->extractKeys($this->loadLanguageFile($this->defaultLanguage));
        $usedKeys = $this->scanCodeForTranslationUsage();
        
        $unused = array_diff($allKeys, $usedKeys);
        
        return array_values($unused);
    }

    /**
     * Scannt PHP und JavaScript Dateien nach Übersetzungsverwendung
     */
    private function scanCodeForTranslationUsage(): array
    {
        $usedKeys = [];
        $projectRoot = dirname(__DIR__, 2);
        
        // PHP Dateien scannen
        $phpFiles = $this->findFiles($projectRoot, '*.php');
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Patterns für PHP: $i18n->t('key'), $this->t('key'), translate('key')
            preg_match_all(
                '/(?:\$i18n->t\(|->translate\(|->t\()\s*[\'"]([^\'"]+)[\'"]\s*\)/',
                $content,
                $matches
            );
            
            if (!empty($matches[1])) {
                $usedKeys = array_merge($usedKeys, $matches[1]);
            }
        }
        
        // JavaScript Dateien scannen
        $jsFiles = $this->findFiles($projectRoot, '*.js');
        foreach ($jsFiles as $file) {
            $content = file_get_contents($file);
            
            // Patterns für JS: window.i18n.t('key'), i18n.t('key')
            preg_match_all(
                '/(?:window\.i18n\.t\(|i18n\.t\()\s*[\'"]([^\'"]+)[\'"]\s*\)/',
                $content,
                $matches
            );
            
            if (!empty($matches[1])) {
                $usedKeys = array_merge($usedKeys, $matches[1]);
            }
        }
        
        // Template Dateien scannen (PHP Templates)
        $templateFiles = $this->findFiles($projectRoot . '/templates', '*.php');
        foreach ($templateFiles as $file) {
            $content = file_get_contents($file);
            
            // Pattern für Templates: $t('key')
            preg_match_all(
                '/\$t\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
                $content,
                $matches
            );
            
            if (!empty($matches[1])) {
                $usedKeys = array_merge($usedKeys, $matches[1]);
            }
        }
        
        return array_unique($usedKeys);
    }

    /**
     * Prüft strukturelle Unterschiede zwischen Übersetzungsdateien
     */
    public function checkStructuralDifferences(): array
    {
        $differences = [];
        $baseStructure = $this->getTranslationStructure($this->defaultLanguage);
        
        foreach ($this->availableLanguages as $lang) {
            if ($lang === $this->defaultLanguage) continue;
            
            $langStructure = $this->getTranslationStructure($lang);
            $diff = $this->compareStructure($baseStructure, $langStructure);
            
            if (!empty($diff)) {
                $differences[$lang] = $diff;
            }
        }
        
        return $differences;
    }

    /**
     * Validiert Platzhalter-Konsistenz zwischen Sprachen
     */
    public function validatePlaceholders(): array
    {
        $issues = [];
        $baseTranslations = $this->loadLanguageFile($this->defaultLanguage);
        $basePlaceholders = $this->extractPlaceholders($baseTranslations);
        
        foreach ($this->availableLanguages as $lang) {
            if ($lang === $this->defaultLanguage) continue;
            
            $langTranslations = $this->loadLanguageFile($lang);
            $langPlaceholders = $this->extractPlaceholders($langTranslations);
            
            foreach ($basePlaceholders as $key => $placeholders) {
                if (!isset($langPlaceholders[$key])) continue;
                
                $missing = array_diff($placeholders, $langPlaceholders[$key]);
                $extra = array_diff($langPlaceholders[$key], $placeholders);
                
                if (!empty($missing) || !empty($extra)) {
                    $issues[$lang][$key] = [
                        'missing_placeholders' => $missing,
                        'extra_placeholders' => $extra,
                        'expected' => $placeholders,
                        'actual' => $langPlaceholders[$key]
                    ];
                }
            }
        }
        
        return $issues;
    }

    /**
     * Automatische Synchronisation der Übersetzungsdateien
     */
    public function synchronizeTranslations(bool $createStubs = true): array
    {
        $results = [];
        $baseTranslations = $this->loadLanguageFile($this->defaultLanguage);
        $baseKeys = $this->extractKeys($baseTranslations);
        
        foreach ($this->availableLanguages as $lang) {
            if ($lang === $this->defaultLanguage) continue;
            
            $langTranslations = $this->loadLanguageFile($lang);
            $langKeys = $this->extractKeys($langTranslations);
            
            $missingKeys = array_diff($baseKeys, $langKeys);
            
            if (!empty($missingKeys)) {
                foreach ($missingKeys as $key) {
                    $this->addTranslationStub($lang, $key, $baseTranslations, $createStubs);
                }
                
                $this->saveLanguageFile($lang, $langTranslations);
                $results[$lang] = count($missingKeys) . ' Schlüssel hinzugefügt';
            } else {
                $results[$lang] = 'Keine Änderungen erforderlich';
            }
        }
        
        return $results;
    }

    /**
     * Generiert Qualitätsbericht
     */
    public function generateCoverageReport(): array
    {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_keys' => 0,
            'languages' => [],
            'overall_health' => 'excellent',
            'recommendations' => []
        ];
        
        $baseKeys = $this->extractKeys($this->loadLanguageFile($this->defaultLanguage));
        $report['total_keys'] = count($baseKeys);
        
        foreach ($this->availableLanguages as $lang) {
            $langKeys = $this->extractKeys($this->loadLanguageFile($lang));
            $coverage = count($baseKeys) > 0 ? (count($langKeys) / count($baseKeys)) * 100 : 100;
            
            $report['languages'][$lang] = [
                'coverage_percentage' => round($coverage, 2),
                'total_keys' => count($langKeys),
                'missing_keys' => count($baseKeys) - count($langKeys),
                'status' => $this->getCoverageStatus($coverage)
            ];
            
            if ($coverage < 95) {
                $report['overall_health'] = 'needs_attention';
            }
            if ($coverage < 80) {
                $report['overall_health'] = 'critical';
            }
        }
        
        return $report;
    }

    /**
     * Generiert Verbesserungsempfehlungen
     */
    public function generateRecommendations(): array
    {
        $recommendations = [];
        $consistencyCheck = $this->checkTranslationConsistency();
        $unusedKeys = $this->findUnusedTranslationKeys();
        
        // Fehlende Übersetzungen
        foreach ($consistencyCheck as $lang => $issues) {
            if (!empty($issues['missing_keys'])) {
                $count = count($issues['missing_keys']);
                $recommendations[] = [
                    'type' => 'missing_translations',
                    'severity' => $count > 10 ? 'high' : 'medium',
                    'language' => $lang,
                    'message' => "{$count} fehlende Übersetzungen in {$lang} gefunden",
                    'action' => 'Führen Sie synchronizeTranslations() aus'
                ];
            }
        }
        
        // Ungenutzte Schlüssel
        if (!empty($unusedKeys)) {
            $count = count($unusedKeys);
            $recommendations[] = [
                'type' => 'unused_keys',
                'severity' => 'low',
                'message' => "{$count} ungenutzte Übersetzungsschlüssel gefunden",
                'action' => 'Prüfen und entfernen Sie ungenutzte Schlüssel'
            ];
        }
        
        return $recommendations;
    }

    /**
     * Erstellt automatisches Backup vor Änderungen
     */
    private function createBackup(): string
    {
        $backupDir = $this->langPath . '/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $backupDir . '/backup_' . $timestamp;
        mkdir($backupPath, 0755, true);
        
        foreach ($this->availableLanguages as $lang) {
            $sourceFile = $this->langPath . '/' . $lang . '.json';
            $backupFile = $backupPath . '/' . $lang . '.json';
            copy($sourceFile, $backupFile);
        }
        
        return $backupPath;
    }

    // ==================== HILFSMETHODEN ====================

    /**
     * Lädt eine Sprachdatei
     */
    private function loadLanguageFile(string $language): array
    {
        $filePath = $this->langPath . '/' . $language . '.json';
        
        if (!file_exists($filePath)) {
            throw new Exception("Sprachdatei nicht gefunden: {$filePath}");
        }
        
        $content = file_get_contents($filePath);
        return json_decode($content, true) ?? [];
    }

    /**
     * Speichert eine Sprachdatei
     */
    private function saveLanguageFile(string $language, array $translations): bool
    {
        $filePath = $this->langPath . '/' . $language . '.json';
        $content = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        return file_put_contents($filePath, $content) !== false;
    }

    /**
     * Findet Dateien mit bestimmter Erweiterung
     */
    private function findFiles(string $directory, string $pattern): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }

    /**
     * Extrahiert Platzhalter aus Übersetzungen
     */
    private function extractPlaceholders(array $translations, string $prefix = ''): array
    {
        $placeholders = [];
        
        foreach ($translations as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value)) {
                $placeholders = array_merge(
                    $placeholders, 
                    $this->extractPlaceholders($value, $fullKey)
                );
            } elseif (is_string($value)) {
                preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $value, $matches);
                if (!empty($matches[1])) {
                    $placeholders[$fullKey] = $matches[1];
                }
            }
        }
        
        return $placeholders;
    }

    /**
     * Vergleicht Strukturen von Übersetzungsdateien
     */
    private function compareStructure(array $base, array $target): array
    {
        $differences = [];
        
        foreach ($base as $key => $value) {
            if (!array_key_exists($key, $target)) {
                $differences['missing_sections'][] = $key;
            } elseif (is_array($value) && is_array($target[$key])) {
                $subDiff = $this->compareStructure($value, $target[$key]);
                if (!empty($subDiff)) {
                    $differences['subsection_differences'][$key] = $subDiff;
                }
            } elseif (is_array($value) !== is_array($target[$key])) {
                $differences['type_mismatches'][] = $key;
            }
        }
        
        return $differences;
    }

    /**
     * Holt Übersetzungsstruktur
     */
    private function getTranslationStructure(string $language): array
    {
        return $this->loadLanguageFile($language);
    }

    /**
     * Fügt Übersetzungs-Stub hinzu
     */
    private function addTranslationStub(
        string $language, 
        string $key, 
        array $baseTranslations, 
        bool $createStub = true
    ): void {
        if (!$createStub) return;
        
        $keys = explode('.', $key);
        $value = $baseTranslations;
        
        // Original-Wert aus Basis-Sprache holen
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                $value = $key; // Fallback
                break;
            }
        }
        
        // Stub-Wert erstellen (Original + Sprach-Hinweis)
        $stubValue = is_string($value) ? "[{$language}] " . $value : "[{$language}] Translation needed";
        
        // In Ziel-Sprache einfügen
        $langTranslations = $this->loadLanguageFile($language);
        $this->setNestedValue($langTranslations, $keys, $stubValue);
        $this->saveLanguageFile($language, $langTranslations);
    }

    /**
     * Setzt verschachtelten Wert in Array
     */
    private function setNestedValue(array &$array, array $keys, $value): void
    {
        $current = &$array;
        
        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
        
        $current = $value;
    }

    /**
     * Bestimmt Coverage-Status
     */
    private function getCoverageStatus(float $coverage): string
    {
        if ($coverage >= 100) return 'complete';
        if ($coverage >= 95) return 'excellent';
        if ($coverage >= 80) return 'good';
        if ($coverage >= 60) return 'needs_work';
        return 'critical';
    }

    /**
     * Gibt Wartungsstatus zurück
     */
    public function getMaintenanceStatus(): array
    {
        return [
            'maintenance_mode' => $this->maintenanceMode,
            'inconsistencies_count' => count($this->inconsistencies),
            'last_check' => $this->session->get('i18n_last_maintenance_check', 0),
            'cache_status' => !empty($this->translationCache) ? 'loaded' : 'empty'
        ];
    }

    /**
     * Führt CLI-Kommando für Wartung aus
     */
    public function runMaintenanceCommand(string $command, array $options = []): array
    {
        switch ($command) {
            case 'check':
                return $this->performMaintenanceCheck();
                
            case 'sync':
                $createStubs = $options['create_stubs'] ?? true;
                return $this->synchronizeTranslations($createStubs);
                
            case 'backup':
                $backupPath = $this->createBackup();
                return ['backup_created' => $backupPath];
                
            case 'report':
                return $this->generateCoverageReport();
                
            default:
                throw new Exception("Unbekanntes Wartungskommando: {$command}");
        }
    }
}