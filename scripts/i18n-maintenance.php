<?php

declare(strict_types=1);

/**
 * I18n Wartungs-Script
 * 
 * Führt automatisierte Übersetzungswartung und Konsistenzprüfungen durch
 * 
 * Verwendung:
 * php scripts/i18n-maintenance.php [command] [options]
 * 
 * Kommandos:
 * - check: Führt vollständige Konsistenzprüfung durch
 * - sync: Synchronisiert Übersetzungsdateien
 * - report: Generiert Coverage-Bericht
 * - backup: Erstellt Backup der Übersetzungsdateien
 * - unused: Zeigt ungenutzte Übersetzungsschlüssel
 * 
 * @author 2Brands Media GmbH
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Session;
use App\Agents\I18nAgent;

class I18nMaintenanceCLI
{
    private I18nAgent $i18nAgent;
    private array $colors;

    public function __construct()
    {
        // Terminal-Farben für bessere Lesbarkeit
        $this->colors = [
            'red' => "\033[31m",
            'green' => "\033[32m",
            'yellow' => "\033[33m",
            'blue' => "\033[34m",
            'magenta' => "\033[35m",
            'cyan' => "\033[36m",
            'white' => "\033[37m",
            'reset' => "\033[0m",
            'bold' => "\033[1m"
        ];

        // I18nAgent initialisieren
        $config = new Config();
        $session = new Session();
        $this->i18nAgent = new I18nAgent($config, $session);
        
        echo $this->colorize("🌐 I18n Wartungssystem gestartet\n\n", 'cyan', true);
    }

    public function run(array $args): void
    {
        $command = $args[1] ?? 'help';
        $options = $this->parseOptions(array_slice($args, 2));

        try {
            switch ($command) {
                case 'check':
                    $this->runCheck();
                    break;
                    
                case 'sync':
                    $this->runSync($options);
                    break;
                    
                case 'report':
                    $this->runReport();
                    break;
                    
                case 'backup':
                    $this->runBackup();
                    break;
                    
                case 'unused':
                    $this->runUnusedKeys();
                    break;
                    
                case 'status':
                    $this->runStatus();
                    break;
                    
                case 'help':
                default:
                    $this->showHelp();
                    break;
            }
        } catch (Exception $e) {
            echo $this->colorize("❌ Fehler: " . $e->getMessage() . "\n", 'red', true);
            exit(1);
        }
    }

    private function runCheck(): void
    {
        echo $this->colorize("🔍 Führe vollständige Konsistenzprüfung durch...\n\n", 'blue', true);
        
        $startTime = microtime(true);
        $results = $this->i18nAgent->performMaintenanceCheck();
        $endTime = microtime(true);
        
        $this->displayCheckResults($results);
        
        $duration = round(($endTime - $startTime) * 1000, 2);
        echo $this->colorize("\n⏱️  Prüfung abgeschlossen in {$duration}ms\n", 'cyan');
    }

    private function runSync(array $options): void
    {
        $createStubs = !isset($options['no-stubs']);
        
        echo $this->colorize("🔄 Synchronisiere Übersetzungsdateien...\n", 'blue', true);
        
        if ($createStubs) {
            echo $this->colorize("📝 Erstelle Stubs für fehlende Übersetzungen\n", 'yellow');
        } else {
            echo $this->colorize("⚠️  Keine Stubs werden erstellt (--no-stubs)\n", 'yellow');
        }
        
        $results = $this->i18nAgent->synchronizeTranslations($createStubs);
        
        echo "\n" . $this->colorize("Synchronisationsergebnisse:", 'green', true) . "\n";
        foreach ($results as $lang => $result) {
            $icon = strpos($result, 'hinzugefügt') !== false ? '✅' : '✓';
            echo "  {$icon} {$lang}: {$result}\n";
        }
    }

    private function runReport(): void
    {
        echo $this->colorize("📊 Generiere Coverage-Bericht...\n\n", 'blue', true);
        
        $report = $this->i18nAgent->generateCoverageReport();
        
        $this->displayCoverageReport($report);
    }

    private function runBackup(): void
    {
        echo $this->colorize("💾 Erstelle Backup der Übersetzungsdateien...\n", 'blue', true);
        
        $result = $this->i18nAgent->runMaintenanceCommand('backup');
        
        echo $this->colorize("✅ Backup erstellt: " . $result['backup_created'] . "\n", 'green');
    }

    private function runUnusedKeys(): void
    {
        echo $this->colorize("🔍 Suche nach ungenutzten Übersetzungsschlüsseln...\n\n", 'blue', true);
        
        $unusedKeys = $this->i18nAgent->findUnusedTranslationKeys();
        
        if (empty($unusedKeys)) {
            echo $this->colorize("✅ Keine ungenutzten Schlüssel gefunden!\n", 'green');
        } else {
            echo $this->colorize("⚠️  " . count($unusedKeys) . " ungenutzte Schlüssel gefunden:\n\n", 'yellow', true);
            
            foreach ($unusedKeys as $key) {
                echo "  🗑️  {$key}\n";
            }
            
            echo "\n" . $this->colorize("💡 Tipp: Prüfen Sie diese Schlüssel und entfernen Sie sie bei Bedarf.\n", 'cyan');
        }
    }

    private function runStatus(): void
    {
        echo $this->colorize("📈 I18n System Status\n\n", 'blue', true);
        
        $status = $this->i18nAgent->getMaintenanceStatus();
        $coverage = $this->i18nAgent->checkCoverage();
        
        echo $this->colorize("Wartungsstatus:", 'green', true) . "\n";
        echo "  Wartungsmodus: " . ($status['maintenance_mode'] ? '🔄 Aktiv' : '✅ Inaktiv') . "\n";
        echo "  Inkonsistenzen: " . $status['inconsistencies_count'] . "\n";
        echo "  Letzte Prüfung: " . ($status['last_check'] > 0 ? date('Y-m-d H:i:s', $status['last_check']) : 'Nie') . "\n";
        echo "  Cache-Status: " . $status['cache_status'] . "\n\n";
        
        echo $this->colorize("Übersetzungsabdeckung:", 'green', true) . "\n";
        foreach ($coverage as $lang => $percentage) {
            $status = $this->getCoverageStatusIcon($percentage);
            echo "  {$status} {$lang}: {$percentage}%\n";
        }
    }

    private function displayCheckResults(array $results): void
    {
        // Konsistenzprüfung
        $this->displaySection("Konsistenzprüfung", $results['consistency_check']);
        
        // Fehlende Schlüssel
        $this->displaySection("Fehlende Schlüssel", $results['missing_keys']);
        
        // Ungenutzte Schlüssel
        if (!empty($results['unused_keys'])) {
            echo $this->colorize("🗑️  Ungenutzte Schlüssel (" . count($results['unused_keys']) . "):", 'yellow', true) . "\n";
            foreach (array_slice($results['unused_keys'], 0, 10) as $key) {
                echo "    • {$key}\n";
            }
            if (count($results['unused_keys']) > 10) {
                $remaining = count($results['unused_keys']) - 10;
                echo "    ... und {$remaining} weitere\n";
            }
            echo "\n";
        }
        
        // Strukturelle Unterschiede
        $this->displaySection("Strukturelle Unterschiede", $results['structural_diff']);
        
        // Platzhalter-Validierung
        $this->displaySection("Platzhalter-Validierung", $results['placeholder_validation']);
        
        // Empfehlungen
        if (!empty($results['recommendations'])) {
            echo $this->colorize("💡 Empfehlungen:", 'cyan', true) . "\n";
            foreach ($results['recommendations'] as $rec) {
                $icon = $this->getSeverityIcon($rec['severity']);
                echo "  {$icon} {$rec['message']}\n";
                echo "     → {$rec['action']}\n";
            }
            echo "\n";
        }
        
        // Zusammenfassung
        $this->displaySummary($results);
    }

    private function displaySection(string $title, array $data): void
    {
        if (empty($data)) {
            echo $this->colorize("✅ {$title}: Alles in Ordnung\n\n", 'green');
            return;
        }
        
        echo $this->colorize("⚠️  {$title}:\n", 'yellow', true);
        
        foreach ($data as $lang => $issues) {
            if (is_array($issues)) {
                echo "  📍 {$lang}:\n";
                foreach ($issues as $type => $items) {
                    if (is_array($items) && !empty($items)) {
                        echo "    • " . ucfirst(str_replace('_', ' ', $type)) . ": " . count($items) . "\n";
                        // Erste 5 Items zeigen
                        foreach (array_slice($items, 0, 5) as $item) {
                            echo "      - {$item}\n";
                        }
                        if (count($items) > 5) {
                            echo "      ... und " . (count($items) - 5) . " weitere\n";
                        }
                    }
                }
            }
        }
        echo "\n";
    }

    private function displayCoverageReport(array $report): void
    {
        echo $this->colorize("📊 Coverage-Bericht", 'blue', true) . "\n";
        echo "Zeitstempel: " . $report['timestamp'] . "\n";
        echo "Gesamtschlüssel: " . $report['total_keys'] . "\n";
        echo "Systemzustand: " . $this->getHealthStatusIcon($report['overall_health']) . " " . $report['overall_health'] . "\n\n";
        
        echo $this->colorize("Sprachdetails:", 'green', true) . "\n";
        foreach ($report['languages'] as $lang => $details) {
            $statusIcon = $this->getCoverageStatusIcon($details['coverage_percentage']);
            echo "  {$statusIcon} {$lang}:\n";
            echo "    Coverage: {$details['coverage_percentage']}% ({$details['status']})\n";
            echo "    Schlüssel: {$details['total_keys']} / " . $report['total_keys'] . "\n";
            if ($details['missing_keys'] > 0) {
                echo "    Fehlend: {$details['missing_keys']}\n";
            }
            echo "\n";
        }
    }

    private function displaySummary(array $results): void
    {
        $totalIssues = 0;
        $totalIssues += count($results['missing_keys']);
        $totalIssues += count($results['unused_keys']);
        $totalIssues += count($results['structural_diff']);
        $totalIssues += count($results['placeholder_validation']);
        
        echo $this->colorize("📋 Zusammenfassung:", 'blue', true) . "\n";
        
        if ($totalIssues === 0) {
            echo $this->colorize("🎉 Perfekt! Keine Probleme gefunden.\n", 'green', true);
        } else {
            echo "  Gefundene Probleme: {$totalIssues}\n";
            echo "  Empfehlungen: " . count($results['recommendations']) . "\n";
            
            if ($totalIssues > 0) {
                echo "\n" . $this->colorize("💡 Führen Sie 'sync' aus, um automatische Korrekturen anzuwenden.\n", 'cyan');
            }
        }
    }

    private function parseOptions(array $args): array
    {
        $options = [];
        foreach ($args as $arg) {
            if (strpos($arg, '--') === 0) {
                $options[substr($arg, 2)] = true;
            }
        }
        return $options;
    }

    private function getSeverityIcon(string $severity): string
    {
        return match ($severity) {
            'high' => '🚨',
            'medium' => '⚠️',
            'low' => '💡',
            default => 'ℹ️'
        };
    }

    private function getCoverageStatusIcon(float $percentage): string
    {
        if ($percentage >= 100) return '🟢';
        if ($percentage >= 95) return '🟡';
        if ($percentage >= 80) return '🟠';
        return '🔴';
    }

    private function getHealthStatusIcon(string $health): string
    {
        return match ($health) {
            'excellent' => '🟢',
            'needs_attention' => '🟡',
            'critical' => '🔴',
            default => '⚪'
        };
    }

    private function colorize(string $text, string $color = 'white', bool $bold = false): string
    {
        $result = '';
        if ($bold) {
            $result .= $this->colors['bold'];
        }
        $result .= $this->colors[$color] . $text . $this->colors['reset'];
        return $result;
    }

    private function showHelp(): void
    {
        echo $this->colorize("🌐 I18n Wartungssystem - Hilfe\n\n", 'cyan', true);
        
        echo $this->colorize("Verwendung:", 'green', true) . "\n";
        echo "  php scripts/i18n-maintenance.php [command] [options]\n\n";
        
        echo $this->colorize("Verfügbare Kommandos:", 'green', true) . "\n";
        echo "  check     Führt vollständige Konsistenzprüfung durch\n";
        echo "  sync      Synchronisiert Übersetzungsdateien\n";
        echo "            --no-stubs: Keine Stubs für fehlende Übersetzungen erstellen\n";
        echo "  report    Generiert detaillierten Coverage-Bericht\n";
        echo "  backup    Erstellt Backup aller Übersetzungsdateien\n";
        echo "  unused    Zeigt ungenutzte Übersetzungsschlüssel\n";
        echo "  status    Zeigt aktuellen System-Status\n";
        echo "  help      Zeigt diese Hilfe\n\n";
        
        echo $this->colorize("Beispiele:", 'green', true) . "\n";
        echo "  php scripts/i18n-maintenance.php check\n";
        echo "  php scripts/i18n-maintenance.php sync\n";
        echo "  php scripts/i18n-maintenance.php sync --no-stubs\n";
        echo "  php scripts/i18n-maintenance.php report\n\n";
        
        echo $this->colorize("💡 Tipp:", 'cyan') . " Führen Sie regelmäßig 'check' aus, um die Übersetzungsqualität sicherzustellen.\n";
    }
}

// Script ausführen
if (php_sapi_name() === 'cli') {
    $cli = new I18nMaintenanceCLI();
    $cli->run($argv);
} else {
    die("Dieses Script kann nur über die Kommandozeile ausgeführt werden.\n");
}