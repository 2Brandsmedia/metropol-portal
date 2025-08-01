<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Agents\I18nAgent;
use App\Core\Config;
use App\Core\Session;
use Exception;

/**
 * I18nMaintenanceController - Web-Interface für Übersetzungswartung
 * 
 * Stellt Web-Endpunkte für die automatisierte Übersetzungsverwaltung bereit
 * 
 * @author 2Brands Media GmbH
 */
class I18nMaintenanceController
{
    private I18nAgent $i18nAgent;
    private Config $config;
    private Session $session;

    public function __construct(Config $config, Session $session)
    {
        $this->config = $config;
        $this->session = $session;
        $this->i18nAgent = new I18nAgent($config, $session);
    }

    /**
     * Zeigt das Übersetzungs-Dashboard
     */
    public function dashboard(Request $request): Response
    {
        try {
            $status = $this->i18nAgent->getMaintenanceStatus();
            $coverage = $this->i18nAgent->checkCoverage();
            $report = $this->i18nAgent->generateCoverageReport();
            
            $data = [
                'status' => $status,
                'coverage' => $coverage,
                'report' => $report,
                'languages' => $this->i18nAgent->getAvailableLanguages(),
                'page_title' => 'Übersetzungsverwaltung'
            ];
            
            return Response::view('i18n/dashboard', $data);
            
        } catch (Exception $e) {
            return Response::json([
                'error' => 'Fehler beim Laden des Dashboards: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Führt Konsistenzprüfung durch
     */
    public function check(Request $request): Response
    {
        try {
            $startTime = microtime(true);
            $results = $this->i18nAgent->performMaintenanceCheck();
            $endTime = microtime(true);
            
            $results['performance'] = [
                'duration_ms' => round(($endTime - $startTime) * 1000, 2),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            return Response::json([
                'success' => true,
                'data' => $results,
                'message' => 'Konsistenzprüfung erfolgreich abgeschlossen'
            ]);
            
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Konsistenzprüfung fehlgeschlagen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Synchronisiert Übersetzungsdateien
     */
    public function sync(Request $request): Response
    {
        try {
            $createStubs = $request->input('create_stubs', true);
            
            // Backup erstellen vor Synchronisation
            $backupResult = $this->i18nAgent->runMaintenanceCommand('backup');
            
            $results = $this->i18nAgent->synchronizeTranslations($createStubs);
            
            return Response::json([
                'success' => true,
                'data' => [
                    'sync_results' => $results,
                    'backup_path' => $backupResult['backup_created']
                ],
                'message' => 'Synchronisation erfolgreich abgeschlossen'
            ]);
            
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Synchronisation fehlgeschlagen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generiert Coverage-Bericht
     */
    public function report(Request $request): Response
    {
        try {
            $report = $this->i18nAgent->generateCoverageReport();
            $recommendations = $this->i18nAgent->generateRecommendations();
            
            $data = [
                'report' => $report,
                'recommendations' => $recommendations
            ];
            
            if ($request->expectsJson()) {
                return Response::json([
                    'success' => true,
                    'data' => $data
                ]);
            }
            
            return Response::view('i18n/report', $data);
            
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Bericht-Generierung fehlgeschlagen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Findet ungenutzte Übersetzungsschlüssel
     */
    public function unusedKeys(Request $request): Response
    {
        try {
            $unusedKeys = $this->i18nAgent->findUnusedTranslationKeys();
            
            return Response::json([
                'success' => true,
                'data' => [
                    'unused_keys' => $unusedKeys,
                    'count' => count($unusedKeys)
                ],
                'message' => count($unusedKeys) . ' ungenutzte Schlüssel gefunden'
            ]);
            
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Suche nach ungenutzten Schlüsseln fehlgeschlagen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Erstellt Backup der Übersetzungsdateien
     */
    public function backup(Request $request): Response
    {
        try {
            $result = $this->i18nAgent->runMaintenanceCommand('backup');
            
            return Response::json([
                'success' => true,
                'data' => $result,
                'message' => 'Backup erfolgreich erstellt'
            ]);
            
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Backup-Erstellung fehlgeschlagen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gibt aktuellen Status zurück
     */
    public function status(Request $request): Response
    {
        try {
            $status = $this->i18nAgent->getMaintenanceStatus();
            $coverage = $this->i18nAgent->checkCoverage();
            
            return Response::json([
                'success' => true,
                'data' => [
                    'maintenance_status' => $status,
                    'coverage' => $coverage,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
            
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Status-Abfrage fehlgeschlagen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Zeigt Details zu fehlenden Übersetzungen
     */
    public function missingTranslations(Request $request): Response
    {
        try {
            $missing = $this->i18nAgent->findMissingTranslationKeys();
            $consistency = $this->i18nAgent->checkTranslationConsistency();
            
            return Response::json([
                'success' => true,
                'data' => [
                    'missing_keys' => $missing,
                    'consistency_issues' => $consistency
                ]
            ]);
            
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Abfrage fehlender Übersetzungen fehlgeschlagen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validiert Platzhalter in Übersetzungen
     */
    public function validatePlaceholders(Request $request): Response
    {
        try {
            $validation = $this->i18nAgent->validatePlaceholders();
            
            return Response::json([
                'success' => true,
                'data' => [
                    'placeholder_issues' => $validation,
                    'issues_count' => count($validation)
                ]
            ]);
            
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Platzhalter-Validierung fehlgeschlagen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Führt vollständige Wartung durch
     */
    public function fullMaintenance(Request $request): Response
    {
        try {
            $results = [];
            
            // 1. Backup erstellen
            $results['backup'] = $this->i18nAgent->runMaintenanceCommand('backup');
            
            // 2. Konsistenzprüfung
            $results['check'] = $this->i18nAgent->performMaintenanceCheck();
            
            // 3. Synchronisation (wenn erforderlich)
            $missing = $this->i18nAgent->findMissingTranslationKeys();
            if (!empty($missing)) {
                $results['sync'] = $this->i18nAgent->synchronizeTranslations(true);
            }
            
            // 4. Abschlussbericht
            $results['final_report'] = $this->i18nAgent->generateCoverageReport();
            
            return Response::json([
                'success' => true,
                'data' => $results,
                'message' => 'Vollständige Wartung erfolgreich abgeschlossen'
            ]);
            
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Vollständige Wartung fehlgeschlagen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportiert Übersetzungsstatistiken
     */
    public function exportStats(Request $request): Response
    {
        try {
            $format = $request->input('format', 'json');
            
            $data = [
                'timestamp' => date('Y-m-d H:i:s'),
                'coverage' => $this->i18nAgent->checkCoverage(),
                'report' => $this->i18nAgent->generateCoverageReport(),
                'missing_keys' => $this->i18nAgent->findMissingTranslationKeys(),
                'unused_keys' => $this->i18nAgent->findUnusedTranslationKeys(),
                'recommendations' => $this->i18nAgent->generateRecommendations()
            ];
            
            switch ($format) {
                case 'csv':
                    return $this->exportToCsv($data);
                    
                case 'json':
                default:
                    $response = Response::json([
                        'success' => true,
                        'data' => $data
                    ]);
                    
                    $response->headers['Content-Disposition'] = 'attachment; filename="i18n-stats-' . date('Y-m-d') . '.json"';
                    return $response;
            }
            
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Export fehlgeschlagen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportiert Daten als CSV
     */
    private function exportToCsv(array $data): Response
    {
        $csv = [];
        $csv[] = ['Sprache', 'Coverage %', 'Fehlende Schlüssel', 'Status'];
        
        foreach ($data['coverage'] as $lang => $coverage) {
            $missing = count($data['missing_keys'][$lang] ?? []);
            $status = $this->getCoverageStatus($coverage);
            
            $csv[] = [$lang, $coverage, $missing, $status];
        }
        
        $output = '';
        foreach ($csv as $row) {
            $output .= implode(',', $row) . "\n";
        }
        
        $response = new Response($output);
        $response->headers['Content-Type'] = 'text/csv';
        $response->headers['Content-Disposition'] = 'attachment; filename="i18n-stats-' . date('Y-m-d') . '.csv"';
        
        return $response;
    }

    /**
     * Bestimmt Coverage-Status
     */
    private function getCoverageStatus(float $coverage): string
    {
        if ($coverage >= 100) return 'Vollständig';
        if ($coverage >= 95) return 'Exzellent';
        if ($coverage >= 80) return 'Gut';
        if ($coverage >= 60) return 'Verbesserungsbedarf';
        return 'Kritisch';
    }
}