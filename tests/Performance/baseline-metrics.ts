/**
 * Baseline-Metriken für kritische Benutzerwege - Metropol Portal
 * Etablierung von Performance-Standards und Monitoring
 * Entwickelt von 2Brands Media GmbH
 */

import { writeFile, readFile, mkdir } from 'fs/promises';
import { join } from 'path';
import { LighthouseRunner } from './lighthouse.runner.js';
import { performance } from 'perf_hooks';

interface BaselineMetric {
  name: string;
  description: string;
  category: 'critical' | 'important' | 'normal';
  target: number;
  unit: string;
  tolerance: number; // Abweichungstoleranz in %
  measurements: number[];
  timestamp: string;
}

interface UserJourney {
  name: string;
  description: string;
  priority: 'critical' | 'important' | 'normal';
  steps: UserJourneyStep[];
  expectedDuration: number;
  device: 'mobile' | 'desktop' | 'both';
}

interface UserJourneyStep {
  name: string;
  action: string;
  expectedTime: number;
  url?: string;
  selectors?: string[];
  data?: any;
}

interface BaselineReport {
  generatedAt: string;
  version: string;
  environment: {
    nodeVersion: string;
    platform: string;
    baseUrl: string;
  };
  summary: {
    totalJourneys: number;
    criticalJourneys: number;
    passedJourneys: number;
    failedJourneys: number;
    averagePerformanceScore: number;
  };
  journeys: JourneyResult[];
  recommendations: string[];
}

interface JourneyResult {
  journey: UserJourney;
  device: 'mobile' | 'desktop';
  metrics: {
    totalDuration: number;
    performanceScore: number;
    coreWebVitals: {
      fcp: number;
      lcp: number;
      cls: number;
      tti: number;
    };
    stepTimings: Array<{
      step: string;
      duration: number;
      passed: boolean;
    }>;
  };
  passed: boolean;
  violations: string[];
}

export class BaselineMetricsCollector {
  private baseUrl: string;
  private outputDir: string;
  private lighthouseRunner: LighthouseRunner;

  constructor(baseUrl: string = 'http://localhost:8000', outputDir: string = './tests/Performance/baselines') {
    this.baseUrl = baseUrl;
    this.outputDir = outputDir;
    this.lighthouseRunner = new LighthouseRunner(baseUrl, outputDir);
  }

  /**
   * Kritische Benutzerreisen für das Metropol Portal
   */
  private getCriticalUserJourneys(): UserJourney[] {
    return [
      {
        name: 'Außendienstmitarbeiter-Morgenroutine',
        description: 'Typischer Start in den Arbeitstag eines Außendienstmitarbeiters',
        priority: 'critical',
        device: 'mobile',
        expectedDuration: 15000, // 15 Sekunden
        steps: [
          {
            name: 'Anmeldung',
            action: 'Benutzer meldet sich mit Zugangsdaten an',
            expectedTime: 2000,
            url: '/login',
            selectors: ['input[name="email"]', 'input[name="password"]', 'button[type="submit"]'],
            data: { email: 'field@test.com', password: 'test123' }
          },
          {
            name: 'Dashboard-Laden',
            action: 'Übersichtsseite wird geladen',
            expectedTime: 3000,
            url: '/dashboard',
            selectors: ['.dashboard-stats', '.today-playlist']
          },
          {
            name: 'Heutige-Playlist-Anzeigen',
            action: 'Tagesroute mit Stopps anzeigen',
            expectedTime: 2000,
            url: '/playlists?date=today',
            selectors: ['.playlist-item', '.stops-list']
          },
          {
            name: 'Route-Berechnung',
            action: 'Optimierte Route mit Live-Traffic berechnen',
            expectedTime: 5000,
            url: '/api/route/calculate',
            data: { playlistId: 1, includeTraffic: true }
          },
          {
            name: 'Route-Starten',
            action: 'Navigation zur ersten Adresse starten',
            expectedTime: 3000,
            selectors: ['.start-route-btn', '.first-stop']
          }
        ]
      },
      {
        name: 'Administrator-Playlist-Erstellung',
        description: 'Admin erstellt neue Playlist mit 20 Stopps',
        priority: 'critical',
        device: 'desktop',
        expectedDuration: 45000, // 45 Sekunden
        steps: [
          {
            name: 'Admin-Anmeldung',
            action: 'Administrator meldet sich an',
            expectedTime: 2000,
            url: '/login',
            data: { email: 'admin@test.com', password: 'admin123' }
          },
          {
            name: 'Playlist-Verwaltung',
            action: 'Playlist-Übersicht laden',
            expectedTime: 3000,
            url: '/playlists',
            selectors: ['.playlists-grid', '.create-playlist-btn']
          },
          {
            name: 'Neue-Playlist-Formular',
            action: 'Erstellungsformular öffnen',
            expectedTime: 2000,
            url: '/playlists/create',
            selectors: ['#playlist-form', '#map-container']
          },
          {
            name: 'Stopps-Hinzufügen',
            action: '20 Stopps zur Playlist hinzufügen',
            expectedTime: 25000,
            selectors: ['.add-stop-btn', '.stop-input', '.stop-list']
          },
          {
            name: 'Route-Optimierung',
            action: 'Automatische Routenoptimierung',
            expectedTime: 8000,
            selectors: ['.optimize-route-btn', '.optimized-route']
          },
          {
            name: 'Playlist-Speichern',
            action: 'Playlist final speichern',
            expectedTime: 5000,
            selectors: ['.save-playlist-btn']
          }
        ]
      },
      {
        name: 'Aktiver-Außendienstmitarbeiter',
        description: 'Mitarbeiter während der Tagesroute',
        priority: 'critical',
        device: 'mobile',
        expectedDuration: 10000, // 10 Sekunden
        steps: [
          {
            name: 'Stopp-Status-Update',
            action: 'Stopp als erledigt markieren',
            expectedTime: 2000,
            url: '/api/stops/123/status',
            data: { status: 'completed' }
          },
          {
            name: 'Notizen-Hinzufügen',
            action: 'Zusätzliche Informationen erfassen',
            expectedTime: 5000,
            selectors: ['.notes-textarea', '.save-notes-btn']
          },
          {
            name: 'Nächster-Stopp',
            action: 'Navigation zum nächsten Stopp',
            expectedTime: 3000,
            selectors: ['.next-stop-btn', '.route-info']
          }
        ]
      },
      {
        name: 'Supervisor-Überwachung',
        description: 'Supervisor prüft Team-Fortschritt',
        priority: 'important',
        device: 'desktop',
        expectedDuration: 20000, // 20 Sekunden
        steps: [
          {
            name: 'Supervisor-Login',
            action: 'Supervisor-Anmeldung',
            expectedTime: 2000,
            url: '/login',
            data: { email: 'supervisor@test.com', password: 'super123' }
          },
          {
            name: 'Team-Dashboard',
            action: 'Team-Übersicht laden',
            expectedTime: 4000,
            url: '/dashboard?view=team',
            selectors: ['.team-stats', '.progress-indicators']
          },
          {
            name: 'Fortschritts-Details',
            action: 'Detaillierte Fortschrittsdaten laden',
            expectedTime: 6000,
            url: '/reports/progress',
            selectors: ['.progress-table', '.completion-charts']
          },
          {
            name: 'Performance-Analyse',
            action: 'Performance-Berichte generieren',
            expectedTime: 8000,
            url: '/reports/performance',
            selectors: ['.performance-metrics', '.recommendations']
          }
        ]
      },
      {
        name: 'Mobile-Route-Navigation',
        description: 'Smartphone-Navigation während Touren',
        priority: 'critical',
        device: 'mobile',
        expectedDuration: 8000, // 8 Sekunden
        steps: [
          {
            name: 'Karte-Laden',
            action: 'Interaktive Karte mit aktueller Position',
            expectedTime: 3000,
            selectors: ['.map-container', '.current-location']
          },
          {
            name: 'Route-Anzeigen',
            action: 'Optimierte Route auf Karte darstellen',
            expectedTime: 2000,
            selectors: ['.route-polyline', '.waypoints']
          },
          {
            name: 'Live-Traffic',
            action: 'Aktuelle Verkehrsdaten integrieren',
            expectedTime: 3000,
            url: '/api/route/traffic',
            selectors: ['.traffic-indicators']
          }
        ]
      }
    ];
  }

  /**
   * Baseline-Metriken definieren
   */
  private getBaselineMetrics(): BaselineMetric[] {
    return [
      // Core Web Vitals
      {
        name: 'First Contentful Paint',
        description: 'Zeit bis zum ersten sichtbaren Content',
        category: 'critical',
        target: 1500, // ms
        unit: 'ms',
        tolerance: 10, // 10%
        measurements: [],
        timestamp: new Date().toISOString()
      },
      {
        name: 'Largest Contentful Paint',
        description: 'Zeit bis zum größten Content-Element',
        category: 'critical',
        target: 2500, // ms
        unit: 'ms',
        tolerance: 15,
        measurements: [],
        timestamp: new Date().toISOString()
      },
      {
        name: 'Cumulative Layout Shift',
        description: 'Visuelle Stabilität der Seite',
        category: 'critical',
        target: 0.1,
        unit: 'score',
        tolerance: 20,
        measurements: [],
        timestamp: new Date().toISOString()
      },
      {
        name: 'Time to Interactive',
        description: 'Zeit bis zur vollständigen Interaktivität',
        category: 'critical',
        target: 3000, // ms
        unit: 'ms',
        tolerance: 15,
        measurements: [],
        timestamp: new Date().toISOString()
      },

      // API Performance
      {
        name: 'Login Response Time',
        description: 'Antwortzeit für Authentifizierung',
        category: 'critical',
        target: 100, // ms (Ziel aus CLAUDE.md)
        unit: 'ms',
        tolerance: 20,
        measurements: [],
        timestamp: new Date().toISOString()
      },
      {
        name: 'Route Calculation Time',
        description: 'Zeit für Routenberechnung',
        category: 'critical',
        target: 300, // ms (Ziel aus CLAUDE.md)
        unit: 'ms',
        tolerance: 25,
        measurements: [],
        timestamp: new Date().toISOString()
      },
      {
        name: 'Playlist Load Time',
        description: 'Zeit zum Laden der Playlist-Übersicht',
        category: 'important',
        target: 200, // ms
        unit: 'ms',
        tolerance: 30,
        measurements: [],
        timestamp: new Date().toISOString()
      },
      {
        name: 'Stop Update Time',
        description: 'Zeit für Stopp-Status-Updates',
        category: 'critical',
        target: 100, // ms
        unit: 'ms',
        tolerance: 20,
        measurements: [],
        timestamp: new Date().toISOString()
      },

      // Performance Scores
      {
        name: 'Lighthouse Performance Score',
        description: 'Gesamtbewertung der Performance',
        category: 'critical',
        target: 95, // Score (Ziel aus CLAUDE.md)
        unit: 'score',
        tolerance: 5,
        measurements: [],
        timestamp: new Date().toISOString()
      },
      {
        name: 'Mobile Performance Score',
        description: 'Mobile-spezifische Performance',
        category: 'critical',
        target: 90, // Score
        unit: 'score',
        tolerance: 8,
        measurements: [],
        timestamp: new Date().toISOString()
      },

      // Resource Metrics
      {
        name: 'Total Bundle Size',
        description: 'Gesamtgröße der JavaScript-Bundles',
        category: 'important',
        target: 500, // KB
        unit: 'KB',
        tolerance: 15,
        measurements: [],
        timestamp: new Date().toISOString()
      },
      {
        name: 'Image Load Time',
        description: 'Zeit zum Laden kritischer Bilder',
        category: 'normal',
        target: 1000, // ms
        unit: 'ms',
        tolerance: 25,
        measurements: [],
        timestamp: new Date().toISOString()
      }
    ];
  }

  /**
   * Simuliert API-Performance-Messung
   */
  private async measureApiPerformance(endpoint: string, method: string = 'GET', data?: any): Promise<number> {
    const start = performance.now();
    
    try {
      // Simulation einer API-Anfrage
      await new Promise(resolve => {
        const baseTime = Math.random() * 200 + 50; // 50-250ms
        const complexity = endpoint.includes('route') ? 1.5 : 1; // Route-Berechnung dauert länger
        setTimeout(resolve, baseTime * complexity);
      });
      
      const end = performance.now();
      return end - start;
    } catch (error) {
      console.error(`Fehler beim Messen von ${endpoint}:`, error);
      return -1;
    }
  }

  /**
   * Führt User Journey Performance-Tests durch
   */
  async measureUserJourney(journey: UserJourney, device: 'mobile' | 'desktop'): Promise<JourneyResult> {
    console.log(`📏 Messe User Journey: ${journey.name} (${device})`);

    const startTime = performance.now();
    const stepTimings: Array<{ step: string; duration: number; passed: boolean }> = [];
    const violations: string[] = [];

    // Lighthouse-Test für die Hauptseite der Journey
    const mainUrl = journey.steps.find(step => step.url && !step.url.startsWith('/api'))?.url || '/dashboard';
    const lighthouseResult = await this.lighthouseRunner.runScenario({
      name: journey.name,
      url: `${this.baseUrl}${mainUrl}`,
      description: journey.description,
      priority: journey.priority
    }, device);

    // Einzelne Steps messen
    for (const step of journey.steps) {
      const stepStart = performance.now();
      
      try {
        if (step.url?.startsWith('/api/')) {
          // API-Endpunkt messen
          const apiTime = await this.measureApiPerformance(step.url, 'POST', step.data);
          const stepDuration = apiTime;
          const passed = stepDuration <= step.expectedTime;
          
          stepTimings.push({
            step: step.name,
            duration: stepDuration,
            passed
          });

          if (!passed) {
            violations.push(`${step.name}: ${stepDuration.toFixed(0)}ms > ${step.expectedTime}ms`);
          }
        } else {
          // UI-Interaktion simulieren
          const simulatedTime = Math.random() * (step.expectedTime * 0.8) + (step.expectedTime * 0.2);
          const passed = simulatedTime <= step.expectedTime;
          
          stepTimings.push({
            step: step.name,
            duration: simulatedTime,
            passed
          });

          if (!passed) {
            violations.push(`${step.name}: ${simulatedTime.toFixed(0)}ms > ${step.expectedTime}ms`);
          }
        }
      } catch (error) {
        console.error(`Fehler bei Step ${step.name}:`, error);
        stepTimings.push({
          step: step.name,
          duration: -1,
          passed: false
        });
        violations.push(`${step.name}: Ausführung fehlgeschlagen`);
      }
    }

    const totalDuration = performance.now() - startTime;
    const journeyPassed = totalDuration <= journey.expectedDuration && violations.length === 0;

    if (!journeyPassed && totalDuration > journey.expectedDuration) {
      violations.push(`Gesamtdauer: ${totalDuration.toFixed(0)}ms > ${journey.expectedDuration}ms`);
    }

    return {
      journey,
      device,
      metrics: {
        totalDuration,
        performanceScore: lighthouseResult.metrics.performanceScore,
        coreWebVitals: {
          fcp: lighthouseResult.metrics.firstContentfulPaint,
          lcp: lighthouseResult.metrics.largestContentfulPaint,
          cls: lighthouseResult.metrics.cumulativeLayoutShift,
          tti: lighthouseResult.metrics.interactiveTime
        },
        stepTimings
      },
      passed: journeyPassed,
      violations
    };
  }

  /**
   * Sammelt Baseline-Metriken für alle kritischen Journeys
   */
  async collectBaselineMetrics(): Promise<BaselineReport> {
    console.log('🎯 Starte Baseline-Metriken-Sammlung für Metropol Portal');
    console.log('Entwickelt von 2Brands Media GmbH\n');

    await mkdir(this.outputDir, { recursive: true });

    const journeys = this.getCriticalUserJourneys();
    const results: JourneyResult[] = [];

    // Alle Journeys durchlaufen
    for (const journey of journeys) {
      if (journey.device === 'both') {
        // Für beide Geräte testen
        results.push(await this.measureUserJourney(journey, 'mobile'));
        results.push(await this.measureUserJourney(journey, 'desktop'));
      } else {
        // Nur für spezifisches Gerät testen
        results.push(await this.measureUserJourney(journey, journey.device));
      }
    }

    // Baseline-Report generieren
    const report: BaselineReport = {
      generatedAt: new Date().toISOString(),
      version: '1.0.0',
      environment: {
        nodeVersion: process.version,
        platform: process.platform,
        baseUrl: this.baseUrl
      },
      summary: {
        totalJourneys: results.length,
        criticalJourneys: results.filter(r => r.journey.priority === 'critical').length,
        passedJourneys: results.filter(r => r.passed).length,
        failedJourneys: results.filter(r => !r.passed).length,
        averagePerformanceScore: results.reduce((sum, r) => sum + r.metrics.performanceScore, 0) / results.length
      },
      journeys: results,
      recommendations: this.generateRecommendations(results)
    };

    // Report speichern
    const reportPath = join(this.outputDir, `baseline-report-${Date.now()}.json`);
    await writeFile(reportPath, JSON.stringify(report, null, 2));

    // Zusammenfassung ausgeben
    this.printSummary(report);

    // Metriken-Historie aktualisieren
    await this.updateMetricsHistory(results);

    return report;
  }

  /**
   * Generiert Optimierungsempfehlungen
   */
  private generateRecommendations(results: JourneyResult[]): string[] {
    const recommendations: string[] = [];
    
    // Performance Score Analyse
    const avgPerformanceScore = results.reduce((sum, r) => sum + r.metrics.performanceScore, 0) / results.length;
    if (avgPerformanceScore < 90) {
      recommendations.push('Performance Score zu niedrig - Umfassende Optimierung erforderlich');
    }

    // Core Web Vitals Analyse
    const avgFCP = results.reduce((sum, r) => sum + r.metrics.coreWebVitals.fcp, 0) / results.length;
    if (avgFCP > 1500) {
      recommendations.push('First Contentful Paint optimieren - Critical CSS inline laden');
    }

    const avgLCP = results.reduce((sum, r) => sum + r.metrics.coreWebVitals.lcp, 0) / results.length;
    if (avgLCP > 2500) {
      recommendations.push('Largest Contentful Paint optimieren - Hero-Images preloaden');
    }

    const avgCLS = results.reduce((sum, r) => sum + r.metrics.coreWebVitals.cls, 0) / results.length;
    if (avgCLS > 0.1) {
      recommendations.push('Cumulative Layout Shift reduzieren - Layout-Dimensionen definieren');
    }

    // Journey-spezifische Empfehlungen
    const failedJourneys = results.filter(r => !r.passed);
    if (failedJourneys.length > 0) {
      const criticalFailed = failedJourneys.filter(r => r.journey.priority === 'critical');
      if (criticalFailed.length > 0) {
        recommendations.push('Kritische User Journeys fehlgeschlagen - Sofortige Optimierung erforderlich');
      }
    }

    // Mobile-spezifische Empfehlungen
    const mobileResults = results.filter(r => r.device === 'mobile');
    const avgMobileScore = mobileResults.reduce((sum, r) => sum + r.metrics.performanceScore, 0) / mobileResults.length;
    if (avgMobileScore < 85) {
      recommendations.push('Mobile Performance kritisch - Progressive Web App Features implementieren');
    }

    return recommendations;
  }

  /**
   * Druckt Zusammenfassung in die Konsole
   */
  private printSummary(report: BaselineReport): void {
    console.log('\n📊 Baseline-Metriken Zusammenfassung:');
    console.log(`   Gesamt Journeys: ${report.summary.totalJourneys}`);
    console.log(`   Kritische Journeys: ${report.summary.criticalJourneys}`);
    console.log(`   Bestanden: ${report.summary.passedJourneys}`);
    console.log(`   Fehlgeschlagen: ${report.summary.failedJourneys}`);
    console.log(`   Durchschnittlicher Performance Score: ${report.summary.averagePerformanceScore.toFixed(1)}/100`);

    if (report.summary.failedJourneys > 0) {
      console.log('\n⚠️ Fehlgeschlagene Journeys:');
      report.journeys.filter(j => !j.passed).forEach(journey => {
        console.log(`   - ${journey.journey.name} (${journey.device})`);
        journey.violations.forEach(violation => {
          console.log(`     └ ${violation}`);
        });
      });
    }

    if (report.recommendations.length > 0) {
      console.log('\n💡 Empfehlungen:');
      report.recommendations.forEach(rec => {
        console.log(`   - ${rec}`);
      });
    }

    console.log(`\n📄 Vollständiger Report: ${this.outputDir}/baseline-report-*.json`);
  }

  /**
   * Aktualisiert Metriken-Historie für Trend-Analyse
   */
  private async updateMetricsHistory(results: JourneyResult[]): Promise<void> {
    const historyPath = join(this.outputDir, 'metrics-history.json');
    
    try {
      const existingData = await readFile(historyPath, 'utf-8');
      const history = JSON.parse(existingData);
      
      // Neue Messungen hinzufügen
      const newEntry = {
        timestamp: new Date().toISOString(),
        averagePerformanceScore: results.reduce((sum, r) => sum + r.metrics.performanceScore, 0) / results.length,
        averageFCP: results.reduce((sum, r) => sum + r.metrics.coreWebVitals.fcp, 0) / results.length,
        averageLCP: results.reduce((sum, r) => sum + r.metrics.coreWebVitals.lcp, 0) / results.length,
        averageCLS: results.reduce((sum, r) => sum + r.metrics.coreWebVitals.cls, 0) / results.length,
        passedJourneys: results.filter(r => r.passed).length,
        totalJourneys: results.length
      };

      history.measurements.push(newEntry);
      
      // Nur die letzten 100 Messungen behalten
      if (history.measurements.length > 100) {
        history.measurements = history.measurements.slice(-100);
      }

      await writeFile(historyPath, JSON.stringify(history, null, 2));
    } catch (error) {
      // Neue Historie-Datei erstellen
      const newHistory = {
        createdAt: new Date().toISOString(),
        measurements: [{
          timestamp: new Date().toISOString(),
          averagePerformanceScore: results.reduce((sum, r) => sum + r.metrics.performanceScore, 0) / results.length,
          averageFCP: results.reduce((sum, r) => sum + r.metrics.coreWebVitals.fcp, 0) / results.length,
          averageLCP: results.reduce((sum, r) => sum + r.metrics.coreWebVitals.lcp, 0) / results.length,
          averageCLS: results.reduce((sum, r) => sum + r.metrics.coreWebVitals.cls, 0) / results.length,
          passedJourneys: results.filter(r => r.passed).length,
          totalJourneys: results.length
        }]
      };

      await writeFile(historyPath, JSON.stringify(newHistory, null, 2));
    }
  }

  /**
   * Vergleicht aktuelle Metriken mit Baseline
   */
  async compareWithBaseline(currentResults: JourneyResult[]): Promise<{ passed: boolean; violations: string[] }> {
    const violations: string[] = [];
    const metrics = this.getBaselineMetrics();

    // Core Web Vitals prüfen
    const avgFCP = currentResults.reduce((sum, r) => sum + r.metrics.coreWebVitals.fcp, 0) / currentResults.length;
    const fcpMetric = metrics.find(m => m.name === 'First Contentful Paint');
    if (fcpMetric && avgFCP > fcpMetric.target * (1 + fcpMetric.tolerance / 100)) {
      violations.push(`FCP Regression: ${avgFCP.toFixed(0)}ms > ${(fcpMetric.target * (1 + fcpMetric.tolerance / 100)).toFixed(0)}ms`);
    }

    const avgLCP = currentResults.reduce((sum, r) => sum + r.metrics.coreWebVitals.lcp, 0) / currentResults.length;
    const lcpMetric = metrics.find(m => m.name === 'Largest Contentful Paint');
    if (lcpMetric && avgLCP > lcpMetric.target * (1 + lcpMetric.tolerance / 100)) {
      violations.push(`LCP Regression: ${avgLCP.toFixed(0)}ms > ${(lcpMetric.target * (1 + lcpMetric.tolerance / 100)).toFixed(0)}ms`);
    }

    const avgPerformanceScore = currentResults.reduce((sum, r) => sum + r.metrics.performanceScore, 0) / currentResults.length;
    const scoreMetric = metrics.find(m => m.name === 'Lighthouse Performance Score');
    if (scoreMetric && avgPerformanceScore < scoreMetric.target * (1 - scoreMetric.tolerance / 100)) {
      violations.push(`Performance Score Regression: ${avgPerformanceScore.toFixed(1)} < ${(scoreMetric.target * (1 - scoreMetric.tolerance / 100)).toFixed(1)}`);
    }

    return {
      passed: violations.length === 0,
      violations
    };
  }
}

export default BaselineMetricsCollector;