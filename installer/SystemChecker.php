<?php

declare(strict_types=1);

namespace Installer;

/**
 * SystemChecker - Prüft Systemanforderungen
 * 
 * @author 2Brands Media GmbH
 */
class SystemChecker
{
    private array $requirements = [];
    private array $recommendations = [];
    
    public function __construct()
    {
        $this->checkAll();
    }
    
    /**
     * Führt alle Checks durch
     */
    private function checkAll(): void
    {
        // PHP Version
        $this->requirements['php_version'] = [
            'name' => 'PHP Version',
            'required' => '8.1.0',
            'current' => PHP_VERSION,
            'passed' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'message' => 'PHP 8.1 oder höher wird benötigt'
        ];
        
        // PHP Extensions
        $requiredExtensions = [
            'pdo' => 'PDO Extension',
            'pdo_mysql' => 'PDO MySQL Driver',
            'json' => 'JSON Extension',
            'mbstring' => 'Multibyte String Extension',
            'openssl' => 'OpenSSL Extension',
            'curl' => 'cURL Extension',
            'fileinfo' => 'Fileinfo Extension',
            'zip' => 'ZIP Extension'
        ];
        
        foreach ($requiredExtensions as $ext => $name) {
            $this->requirements["ext_$ext"] = [
                'name' => $name,
                'required' => 'Aktiviert',
                'current' => extension_loaded($ext) ? 'Aktiviert' : 'Fehlt',
                'passed' => extension_loaded($ext),
                'message' => "$name wird benötigt"
            ];
        }
        
        // Schreibrechte
        $writableDirs = [
            '../' => 'Hauptverzeichnis (für .env)',
            '../public/assets/cache/' => 'Cache-Verzeichnis',
            '../storage/' => 'Storage-Verzeichnis',
            '../database/' => 'Datenbank-Verzeichnis'
        ];
        
        foreach ($writableDirs as $dir => $name) {
            $path = __DIR__ . '/' . $dir;
            $writable = is_writable(dirname($path));
            
            $this->requirements["write_$dir"] = [
                'name' => "Schreibrechte: $name",
                'required' => 'Beschreibbar',
                'current' => $writable ? 'OK' : 'Keine Schreibrechte',
                'passed' => $writable,
                'message' => "Das Verzeichnis muss beschreibbar sein"
            ];
        }
        
        // Empfehlungen
        $this->checkRecommendations();
    }
    
    /**
     * Prüft empfohlene Einstellungen
     */
    private function checkRecommendations(): void
    {
        // Memory Limit
        $memoryLimit = $this->getBytes(ini_get('memory_limit'));
        $recommendedMemory = 128 * 1024 * 1024; // 128M
        
        $this->recommendations['memory_limit'] = [
            'name' => 'PHP Memory Limit',
            'recommended' => '128M',
            'current' => ini_get('memory_limit'),
            'passed' => $memoryLimit >= $recommendedMemory,
            'message' => 'Mindestens 128MB empfohlen für optimale Performance'
        ];
        
        // Max Execution Time
        $maxExecTime = (int) ini_get('max_execution_time');
        $this->recommendations['max_execution_time'] = [
            'name' => 'Max Execution Time',
            'recommended' => '60',
            'current' => $maxExecTime,
            'passed' => $maxExecTime >= 60 || $maxExecTime === 0,
            'message' => 'Mindestens 60 Sekunden empfohlen'
        ];
        
        // Upload Max Filesize
        $uploadSize = $this->getBytes(ini_get('upload_max_filesize'));
        $recommendedUpload = 10 * 1024 * 1024; // 10M
        
        $this->recommendations['upload_max_filesize'] = [
            'name' => 'Upload Max Filesize',
            'recommended' => '10M',
            'current' => ini_get('upload_max_filesize'),
            'passed' => $uploadSize >= $recommendedUpload,
            'message' => 'Mindestens 10MB empfohlen für Datei-Uploads'
        ];
        
        // OPcache
        $this->recommendations['opcache'] = [
            'name' => 'OPcache',
            'recommended' => 'Aktiviert',
            'current' => extension_loaded('Zend OPcache') ? 'Aktiviert' : 'Deaktiviert',
            'passed' => extension_loaded('Zend OPcache'),
            'message' => 'OPcache verbessert die Performance erheblich'
        ];
    }
    
    /**
     * Konvertiert PHP-Größenangaben in Bytes
     */
    private function getBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int) $val;
        
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
    
    /**
     * Prüft ob alle Requirements erfüllt sind
     */
    public function allRequirementsMet(): bool
    {
        foreach ($this->requirements as $req) {
            if (!$req['passed']) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Gibt alle Requirements zurück
     */
    public function getRequirements(): array
    {
        return $this->requirements;
    }
    
    /**
     * Gibt alle Empfehlungen zurück
     */
    public function getRecommendations(): array
    {
        return $this->recommendations;
    }
    
    /**
     * Generiert einen HTML-Report
     */
    public function getHtmlReport(): string
    {
        $html = '<div class="space-y-6">';
        
        // Requirements
        $html .= '<div>';
        $html .= '<h3 class="text-lg font-semibold mb-3">Systemanforderungen</h3>';
        $html .= '<div class="space-y-2">';
        
        foreach ($this->requirements as $req) {
            $icon = $req['passed'] 
                ? '<svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>'
                : '<svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>';
            
            $html .= '<div class="flex items-center justify-between p-3 bg-gray-50 rounded">';
            $html .= '<div class="flex items-center space-x-3">';
            $html .= $icon;
            $html .= '<div>';
            $html .= '<p class="font-medium">' . htmlspecialchars((string)$req['name']) . '</p>';
            $html .= '<p class="text-sm text-gray-600">' . htmlspecialchars((string)$req['message']) . '</p>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<div class="text-right">';
            $html .= '<p class="text-sm">Benötigt: <span class="font-medium">' . htmlspecialchars((string)$req['required']) . '</span></p>';
            $html .= '<p class="text-sm">Aktuell: <span class="font-medium ' . ($req['passed'] ? 'text-green-600' : 'text-red-600') . '">' . htmlspecialchars((string)$req['current']) . '</span></p>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        // Empfehlungen
        $html .= '<div>';
        $html .= '<h3 class="text-lg font-semibold mb-3">Empfehlungen</h3>';
        $html .= '<div class="space-y-2">';
        
        foreach ($this->recommendations as $rec) {
            $icon = $rec['passed'] 
                ? '<svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>'
                : '<svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>';
            
            $html .= '<div class="flex items-center justify-between p-3 bg-gray-50 rounded">';
            $html .= '<div class="flex items-center space-x-3">';
            $html .= $icon;
            $html .= '<div>';
            $html .= '<p class="font-medium">' . htmlspecialchars((string)$rec['name']) . '</p>';
            $html .= '<p class="text-sm text-gray-600">' . htmlspecialchars((string)$rec['message']) . '</p>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<div class="text-right">';
            $html .= '<p class="text-sm">Empfohlen: <span class="font-medium">' . htmlspecialchars((string)$rec['recommended']) . '</span></p>';
            $html .= '<p class="text-sm">Aktuell: <span class="font-medium ' . ($rec['passed'] ? 'text-green-600' : 'text-yellow-600') . '">' . htmlspecialchars((string)$rec['current']) . '</span></p>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
}