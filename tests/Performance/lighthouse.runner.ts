import lighthouse from 'lighthouse';
import { launch } from 'chrome-launcher';
import { writeFile, mkdir } from 'fs/promises';
import { join } from 'path';
import { 
  lighthouseConfig, 
  lighthouseOptions, 
  performanceBudgets,
  mobileConfig,
  desktopConfig 
} from './lighthouse.config.js';

/**
 * Lighthouse Performance Test Runner für Metropol Portal
 * Automatisierte Performance-Tests mit Core Web Vitals
 * Entwickelt von 2Brands Media GmbH
 */

interface TestResult {
  url: string;
  timestamp: string;
  device: 'mobile' | 'desktop';
  metrics: {
    firstContentfulPaint: number;
    largestContentfulPaint: number;
    interactiveTime: number;
    speedIndex: number;
    cumulativeLayoutShift: number;
    totalBlockingTime: number;
    performanceScore: number;
  };
  passed: boolean;
  violations: string[];
}

interface TestScenario {
  name: string;
  url: string;
  description: string;
  priority: 'critical' | 'important' | 'normal';
  timeout?: number;
  preActions?: () => Promise<void>;
}

export class LighthouseRunner {
  private baseUrl: string;
  private outputDir: string;
  private results: TestResult[] = [];

  constructor(baseUrl: string = 'http://localhost:8000', outputDir: string = './tests/Performance/reports') {
    this.baseUrl = baseUrl;
    this.outputDir = outputDir;
  }

  /**
   * Kritische Benutzerreisen für Performance-Tests
   */
  private getTestScenarios(): TestScenario[] {
    return [
      {
        name: 'Login-Prozess',
        url: `${this.baseUrl}/login`,
        description: 'Authentifizierung und Session-Erstellung',
        priority: 'critical',
        timeout: 5000,
      },
      {
        name: 'Dashboard-Laden',
        url: `${this.baseUrl}/dashboard`,
        description: 'Hauptbereich nach Login',
        priority: 'critical',
        timeout: 3000,
      },
      {
        name: 'Playlist-Übersicht',
        url: `${this.baseUrl}/playlists`,
        description: 'Tagesrouten-Verwaltung',
        priority: 'critical',
        timeout: 2000,
      },
      {
        name: 'Playlist-Erstellung',
        url: `${this.baseUrl}/playlists/create`,
        description: 'Neue Playlist mit Karte',
        priority: 'critical',
        timeout: 4000,
      },
      {
        name: 'Route-Berechnung',
        url: `${this.baseUrl}/playlists/1/route`,
        description: 'Optimierte Route mit 20 Stopps',
        priority: 'important',
        timeout: 5000,
      },
      {
        name: 'Mobile-Dashboard',
        url: `${this.baseUrl}/dashboard`,
        description: 'Mobile Ansicht für Außendienst',
        priority: 'critical',
        timeout: 3000,
      },
    ];
  }

  /**
   * Startet Chrome-Browser für Tests
   */
  private async startChrome(): Promise<any> {
    return await launch({
      chromeFlags: lighthouseOptions.chromeFlags,
      port: 0,
      handleSIGINT: false,
    });
  }

  /**
   * Führt Lighthouse-Test für eine URL durch
   */
  private async runLighthouseTest(
    url: string, 
    device: 'mobile' | 'desktop' = 'mobile'
  ): Promise<any> {
    const chrome = await this.startChrome();
    
    try {
      const options = {
        ...lighthouseOptions,
        ...(device === 'mobile' ? mobileConfig : desktopConfig),
        port: chrome.port,
      };

      const result = await lighthouse(url, options, lighthouseConfig);
      
      if (!result) {
        throw new Error(`Lighthouse-Test für ${url} fehlgeschlagen`);
      }

      return result;
    } finally {
      await chrome.kill();
    }
  }

  /**
   * Extrahiert Performance-Metriken aus Lighthouse-Ergebnis
   */
  private extractMetrics(lighthouseResult: any): TestResult['metrics'] {
    const audits = lighthouseResult.lhr.audits;
    
    return {
      firstContentfulPaint: audits['first-contentful-paint']?.numericValue || 0,
      largestContentfulPaint: audits['largest-contentful-paint']?.numericValue || 0,
      interactiveTime: audits['interactive']?.numericValue || 0,
      speedIndex: audits['speed-index']?.numericValue || 0,
      cumulativeLayoutShift: audits['cumulative-layout-shift']?.numericValue || 0,
      totalBlockingTime: audits['total-blocking-time']?.numericValue || 0,
      performanceScore: lighthouseResult.lhr.categories.performance?.score * 100 || 0,
    };
  }

  /**
   * Überprüft Performance-Budget-Verletzungen
   */
  private checkBudgetViolations(metrics: TestResult['metrics']): string[] {
    const violations: string[] = [];

    if (metrics.firstContentfulPaint > performanceBudgets.firstContentfulPaint) {
      violations.push(`FCP zu langsam: ${metrics.firstContentfulPaint}ms > ${performanceBudgets.firstContentfulPaint}ms`);
    }

    if (metrics.largestContentfulPaint > performanceBudgets.largestContentfulPaint) {
      violations.push(`LCP zu langsam: ${metrics.largestContentfulPaint}ms > ${performanceBudgets.largestContentfulPaint}ms`);
    }

    if (metrics.interactiveTime > performanceBudgets.interactiveTime) {
      violations.push(`TTI zu langsam: ${metrics.interactiveTime}ms > ${performanceBudgets.interactiveTime}ms`);
    }

    if (metrics.cumulativeLayoutShift > performanceBudgets.cumulativeLayoutShift) {
      violations.push(`CLS zu hoch: ${metrics.cumulativeLayoutShift} > ${performanceBudgets.cumulativeLayoutShift}`);
    }

    if (metrics.totalBlockingTime > performanceBudgets.totalBlockingTime) {
      violations.push(`TBT zu hoch: ${metrics.totalBlockingTime}ms > ${performanceBudgets.totalBlockingTime}ms`);
    }

    if (metrics.performanceScore < performanceBudgets.performanceScore) {
      violations.push(`Performance Score zu niedrig: ${metrics.performanceScore} < ${performanceBudgets.performanceScore}`);
    }

    return violations;
  }

  /**
   * Speichert Test-Ergebnisse
   */
  private async saveResults(
    lighthouseResult: any, 
    testResult: TestResult, 
    scenario: TestScenario
  ): Promise<void> {
    await mkdir(this.outputDir, { recursive: true });

    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const deviceSuffix = testResult.device;
    const baseFileName = `${scenario.name.toLowerCase().replace(/[^a-z0-9]/g, '-')}-${deviceSuffix}-${timestamp}`;

    // HTML-Report speichern
    const htmlPath = join(this.outputDir, `${baseFileName}.html`);
    await writeFile(htmlPath, lighthouseResult.report);

    // JSON-Report speichern
    const jsonPath = join(this.outputDir, `${baseFileName}.json`);
    await writeFile(jsonPath, JSON.stringify(lighthouseResult.lhr, null, 2));

    // Zusammenfassung speichern
    const summaryPath = join(this.outputDir, `${baseFileName}-summary.json`);
    await writeFile(summaryPath, JSON.stringify(testResult, null, 2));

    console.log(`📊 Berichte gespeichert: ${baseFileName}`);
  }

  /**
   * Führt Performance-Test für ein Szenario durch
   */
  async runScenario(scenario: TestScenario, device: 'mobile' | 'desktop' = 'mobile'): Promise<TestResult> {
    console.log(`🚀 Starte ${device} Performance-Test: ${scenario.name}`);
    console.log(`   URL: ${scenario.url}`);
    console.log(`   Priorität: ${scenario.priority}`);

    try {
      // Pre-Actions ausführen (z.B. Login)
      if (scenario.preActions) {
        await scenario.preActions();
      }

      // Lighthouse-Test durchführen
      const lighthouseResult = await this.runLighthouseTest(scenario.url, device);
      
      // Metriken extrahieren
      const metrics = this.extractMetrics(lighthouseResult);
      
      // Budget-Verletzungen prüfen
      const violations = this.checkBudgetViolations(metrics);

      const testResult: TestResult = {
        url: scenario.url,
        timestamp: new Date().toISOString(),
        device,
        metrics,
        passed: violations.length === 0,
        violations,
      };

      // Ergebnisse speichern
      await this.saveResults(lighthouseResult, testResult, scenario);

      // Logging
      console.log(`✅ Test abgeschlossen: ${scenario.name}`);
      console.log(`   Performance Score: ${metrics.performanceScore}/100`);
      console.log(`   FCP: ${metrics.firstContentfulPaint}ms`);
      console.log(`   LCP: ${metrics.largestContentfulPaint}ms`);
      console.log(`   TTI: ${metrics.interactiveTime}ms`);
      console.log(`   CLS: ${metrics.cumulativeLayoutShift}`);
      
      if (violations.length > 0) {
        console.log(`⚠️  Budget-Verletzungen: ${violations.length}`);
        violations.forEach(violation => console.log(`     - ${violation}`));
      }

      this.results.push(testResult);
      return testResult;

    } catch (error) {
      console.error(`❌ Fehler bei Test ${scenario.name}:`, error);
      
      const errorResult: TestResult = {
        url: scenario.url,
        timestamp: new Date().toISOString(),
        device,
        metrics: {
          firstContentfulPaint: 0,
          largestContentfulPaint: 0,
          interactiveTime: 0,
          speedIndex: 0,
          cumulativeLayoutShift: 0,
          totalBlockingTime: 0,
          performanceScore: 0,
        },
        passed: false,
        violations: [`Test fehlgeschlagen: ${error.message}`],
      };

      this.results.push(errorResult);
      return errorResult;
    }
  }

  /**
   * Führt alle kritischen Performance-Tests durch
   */
  async runAllTests(): Promise<TestResult[]> {
    console.log('🎯 Starte umfassende Performance-Tests für Metropol Portal');
    console.log('Entwickelt von 2Brands Media GmbH\n');

    const scenarios = this.getTestScenarios();
    
    // Kritische Tests zuerst
    const criticalScenarios = scenarios.filter(s => s.priority === 'critical');
    const otherScenarios = scenarios.filter(s => s.priority !== 'critical');

    // Mobile Tests (Hauptfokus für Außendienst)
    console.log('📱 Mobile Performance-Tests...');
    for (const scenario of criticalScenarios) {
      await this.runScenario(scenario, 'mobile');
    }

    // Desktop Tests für Admin-Bereich
    console.log('\n💻 Desktop Performance-Tests...');
    const adminScenarios = criticalScenarios.filter(s => 
      s.name.includes('Dashboard') || s.name.includes('Playlist')
    );
    
    for (const scenario of adminScenarios) {
      await this.runScenario(scenario, 'desktop');
    }

    // Wichtige aber nicht-kritische Tests
    console.log('\n🔄 Zusätzliche Performance-Tests...');
    for (const scenario of otherScenarios) {
      await this.runScenario(scenario, 'mobile');
    }

    // Zusammenfassung generieren
    await this.generateSummaryReport();

    return this.results;
  }

  /**
   * Generiert Zusammenfassungsbericht
   */
  private async generateSummaryReport(): Promise<void> {
    const summary = {
      testRun: {
        timestamp: new Date().toISOString(),
        totalTests: this.results.length,
        passedTests: this.results.filter(r => r.passed).length,
        failedTests: this.results.filter(r => !r.passed).length,
      },
      performance: {
        averageScore: this.results.reduce((sum, r) => sum + r.metrics.performanceScore, 0) / this.results.length,
        averageFCP: this.results.reduce((sum, r) => sum + r.metrics.firstContentfulPaint, 0) / this.results.length,
        averageLCP: this.results.reduce((sum, r) => sum + r.metrics.largestContentfulPaint, 0) / this.results.length,
        averageTTI: this.results.reduce((sum, r) => sum + r.metrics.interactiveTime, 0) / this.results.length,
        averageCLS: this.results.reduce((sum, r) => sum + r.metrics.cumulativeLayoutShift, 0) / this.results.length,
      },
      budgetViolations: this.results.flatMap(r => r.violations),
      recommendations: this.generateRecommendations(),
      results: this.results,
    };

    const summaryPath = join(this.outputDir, `performance-summary-${Date.now()}.json`);
    await writeFile(summaryPath, JSON.stringify(summary, null, 2));

    console.log('\n📈 Performance-Zusammenfassung:');
    console.log(`   Gesamttests: ${summary.testRun.totalTests}`);
    console.log(`   Bestanden: ${summary.testRun.passedTests}`);
    console.log(`   Fehlgeschlagen: ${summary.testRun.failedTests}`);
    console.log(`   Durchschnittlicher Performance Score: ${summary.performance.averageScore.toFixed(1)}/100`);
    console.log(`   Durchschnittliche FCP: ${summary.performance.averageFCP.toFixed(0)}ms`);
    console.log(`   Durchschnittliche LCP: ${summary.performance.averageLCP.toFixed(0)}ms`);
    
    if (summary.budgetViolations.length > 0) {
      console.log(`\n⚠️  ${summary.budgetViolations.length} Budget-Verletzungen gefunden`);
    }

    console.log(`\n📊 Vollständiger Bericht gespeichert: ${summaryPath}`);
  }

  /**
   * Generiert Performance-Optimierungs-Empfehlungen
   */
  private generateRecommendations(): string[] {
    const recommendations: string[] = [];
    const avgMetrics = this.results.reduce((acc, result) => {
      acc.fcp += result.metrics.firstContentfulPaint;
      acc.lcp += result.metrics.largestContentfulPaint;
      acc.tti += result.metrics.interactiveTime;
      acc.cls += result.metrics.cumulativeLayoutShift;
      acc.tbt += result.metrics.totalBlockingTime;
      return acc;
    }, { fcp: 0, lcp: 0, tti: 0, cls: 0, tbt: 0 });

    const count = this.results.length;
    avgMetrics.fcp /= count;
    avgMetrics.lcp /= count;
    avgMetrics.tti /= count;
    avgMetrics.cls /= count;
    avgMetrics.tbt /= count;

    if (avgMetrics.fcp > performanceBudgets.firstContentfulPaint) {
      recommendations.push('FCP optimieren: Critical CSS inline laden, Fonts preloaden');
    }

    if (avgMetrics.lcp > performanceBudgets.largestContentfulPaint) {
      recommendations.push('LCP optimieren: Hero-Images preloaden, Server-Response-Zeit reduzieren');
    }

    if (avgMetrics.tti > performanceBudgets.interactiveTime) {
      recommendations.push('TTI optimieren: JavaScript-Bundle aufteilen, Third-Party-Scripts defer');
    }

    if (avgMetrics.cls > performanceBudgets.cumulativeLayoutShift) {
      recommendations.push('CLS reduzieren: Image-Dimensionen definieren, Layout-Shifts vermeiden');
    }

    if (avgMetrics.tbt > performanceBudgets.totalBlockingTime) {
      recommendations.push('TBT reduzieren: Lange Tasks aufteilen, Web Workers nutzen');
    }

    return recommendations;
  }
}

// Export für CLI-Nutzung
export default LighthouseRunner;