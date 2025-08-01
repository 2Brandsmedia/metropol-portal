/**
 * Comprehensive Load Test Runner für Metropol Portal
 * Orchestriert verschiedene Load-Test-Szenarien und generiert Berichte
 * Entwickelt von 2Brands Media GmbH
 */

import { spawn, ChildProcess } from 'child_process';
import * as fs from 'fs';
import * as path from 'path';

interface TestResult {
  scenario: string;
  startTime: Date;
  endTime: Date;
  duration: number;
  success: boolean;
  metrics: {
    [key: string]: {
      min: number;
      max: number;
      avg: number;
      p95: number;
      p99: number;
      count: number;
    };
  };
  thresholds: {
    [key: string]: {
      passed: boolean;
      value: string;
      target: string;
    };
  };
  errors: string[];
  recommendations: string[];
}

interface LoadTestConfig {
  name: string;
  description: string;
  scenarios: ScenarioConfig[];
  infrastructure: {
    expectedConcurrentUsers: number;
    expectedThroughput: number; // requests per second
    targetLatency: number; // milliseconds
  };
}

interface ScenarioConfig {
  name: string;
  type: 'k6' | 'playwright';
  script: string;
  duration: number;
  users: number;
  environment: { [key: string]: string };
  priority: 'critical' | 'high' | 'medium' | 'low';
}

class LoadTestRunner {
  private baseUrl: string;
  private outputDir: string;
  private testResults: TestResult[] = [];
  private startTime: Date;
  
  constructor(baseUrl: string = 'http://localhost:8000', outputDir: string = './test-results') {
    this.baseUrl = baseUrl;
    this.outputDir = outputDir;
    this.startTime = new Date();
    
    // Erstelle Output-Verzeichnis
    if (!fs.existsSync(outputDir)) {
      fs.mkdirSync(outputDir, { recursive: true });
    }
  }

  /**
   * Führt alle Load-Test-Szenarien aus
   */
  async runAllScenarios(): Promise<void> {
    console.log('🚀 Starte umfassende Load-Tests für Metropol Portal');
    console.log('Entwickelt von 2Brands Media GmbH');
    console.log(`📍 Target URL: ${this.baseUrl}`);
    console.log(`📁 Output Directory: ${this.outputDir}`);
    console.log('');

    const configs = this.getLoadTestConfigs();
    
    for (const config of configs) {
      console.log(`\n📋 Starte Test-Suite: ${config.name}`);
      console.log(`📖 ${config.description}`);
      console.log(`👥 Erwartete Benutzer: ${config.infrastructure.expectedConcurrentUsers}`);
      console.log(`⚡ Erwarteter Durchsatz: ${config.infrastructure.expectedThroughput} req/s`);
      console.log(`🎯 Ziel-Latenz: ${config.infrastructure.targetLatency}ms`);
      
      await this.runTestSuite(config);
    }

    // Finale Berichtserstellung
    await this.generateComprehensiveReport();
    console.log('\n🏁 Alle Load-Tests abgeschlossen!');
    console.log(`📊 Detaillierte Berichte verfügbar in: ${this.outputDir}`);
  }

  /**
   * Führt eine Test-Suite aus
   */
  private async runTestSuite(config: LoadTestConfig): Promise<void> {
    const criticalScenarios = config.scenarios.filter(s => s.priority === 'critical');
    const highPriorityScenarios = config.scenarios.filter(s => s.priority === 'high');
    const mediumPriorityScenarios = config.scenarios.filter(s => s.priority === 'medium');
    const lowPriorityScenarios = config.scenarios.filter(s => s.priority === 'low');

    // Kritische Tests zuerst (seriell)
    for (const scenario of criticalScenarios) {
      console.log(`\n🔴 Kritischer Test: ${scenario.name}`);
      const result = await this.runScenario(scenario);
      this.testResults.push(result);
      
      if (!result.success) {
        console.log(`❌ Kritischer Test fehlgeschlagen: ${scenario.name}`);
        console.log('🛑 Stoppe weitere Tests dieser Suite');
        return;
      }
    }

    // High-Priority Tests (parallel mit Begrenzung)
    if (highPriorityScenarios.length > 0) {
      console.log(`\n🟡 Führe ${highPriorityScenarios.length} High-Priority Tests aus...`);
      const highResults = await this.runScenariosParallel(highPriorityScenarios, 2);
      this.testResults.push(...highResults);
    }

    // Medium und Low Priority Tests (parallel)
    const remainingScenarios = [...mediumPriorityScenarios, ...lowPriorityScenarios];
    if (remainingScenarios.length > 0) {
      console.log(`\n🔵 Führe ${remainingScenarios.length} weitere Tests aus...`);
      const remainingResults = await this.runScenariosParallel(remainingScenarios, 3);
      this.testResults.push(...remainingResults);
    }
  }

  /**
   * Führt Szenarien parallel aus
   */
  private async runScenariosParallel(scenarios: ScenarioConfig[], maxConcurrent: number): Promise<TestResult[]> {
    const results: TestResult[] = [];
    const chunks = this.chunkArray(scenarios, maxConcurrent);
    
    for (const chunk of chunks) {
      const chunkPromises = chunk.map(scenario => this.runScenario(scenario));
      const chunkResults = await Promise.allSettled(chunkPromises);
      
      for (const result of chunkResults) {
        if (result.status === 'fulfilled') {
          results.push(result.value);
        } else {
          console.error('❌ Paralleler Test fehlgeschlagen:', result.reason);
        }
      }
    }
    
    return results;
  }

  /**
   * Führt ein einzelnes Szenario aus
   */
  private async runScenario(scenario: ScenarioConfig): Promise<TestResult> {
    const startTime = new Date();
    console.log(`  ⏳ Starte: ${scenario.name} (${scenario.users} Benutzer, ${scenario.duration}s)`);

    const result: TestResult = {
      scenario: scenario.name,
      startTime,
      endTime: startTime,
      duration: 0,
      success: false,
      metrics: {},
      thresholds: {},
      errors: [],
      recommendations: []
    };

    try {
      if (scenario.type === 'k6') {
        await this.runK6Scenario(scenario, result);
      } else if (scenario.type === 'playwright') {
        await this.runPlaywrightScenario(scenario, result);
      }
      
      result.endTime = new Date();
      result.duration = result.endTime.getTime() - startTime.getTime();
      
      console.log(`  ✅ Abgeschlossen: ${scenario.name} (${result.duration}ms)`);
      
      // Analysiere Ergebnisse
      this.analyzeResults(result);
      
    } catch (error: any) {
      result.endTime = new Date();
      result.duration = result.endTime.getTime() - startTime.getTime();
      result.errors.push(error.message || error.toString());
      console.log(`  ❌ Fehler: ${scenario.name} - ${error.message}`);
    }

    return result;
  }

  /**
   * Führt K6-Szenario aus
   */
  private async runK6Scenario(scenario: ScenarioConfig, result: TestResult): Promise<void> {
    return new Promise((resolve, reject) => {
      const scriptPath = path.join(__dirname, scenario.script);
      const outputFile = path.join(this.outputDir, `${scenario.name}-${Date.now()}.json`);
      
      const env = {
        ...process.env,
        BASE_URL: this.baseUrl,
        SCENARIO: scenario.name,
        ...scenario.environment
      };

      const args = [
        'run',
        '--out', `json=${outputFile}`,
        '--summary-trend-stats', 'min,avg,med,max,p(95),p(99)',
        scriptPath
      ];

      const k6Process = spawn('k6', args, { env });
      
      let stdout = '';
      let stderr = '';
      
      k6Process.stdout.on('data', (data) => {
        stdout += data.toString();
      });
      
      k6Process.stderr.on('data', (data) => {
        stderr += data.toString();
      });
      
      k6Process.on('close', (code) => {
        if (code === 0) {
          // Parse K6 output
          this.parseK6Output(outputFile, result);
          result.success = true;
          resolve();
        } else {
          result.errors.push(`K6 exited with code ${code}`);
          if (stderr) result.errors.push(stderr);
          reject(new Error(`K6 failed with code ${code}`));
        }
      });

      // Timeout nach Szenario-Dauer + Buffer
      setTimeout(() => {
        k6Process.kill('SIGTERM');
        reject(new Error('K6 scenario timeout'));
      }, (scenario.duration + 60) * 1000);
    });
  }

  /**
   * Führt Playwright-Szenario aus
   */
  private async runPlaywrightScenario(scenario: ScenarioConfig, result: TestResult): Promise<void> {
    return new Promise((resolve, reject) => {
      const scriptPath = path.join(__dirname, scenario.script);
      const outputFile = path.join(this.outputDir, `${scenario.name}-playwright-${Date.now()}.json`);
      
      const env = {
        ...process.env,
        BASE_URL: this.baseUrl,
        CONCURRENT_USERS: scenario.users.toString(),
        DURATION: scenario.duration.toString(),
        OUTPUT_FILE: outputFile,
        ...scenario.environment
      };

      const playwrightProcess = spawn('npx', ['playwright', 'test', scriptPath], { env });
      
      let stdout = '';
      let stderr = '';
      
      playwrightProcess.stdout.on('data', (data) => {
        stdout += data.toString();
      });
      
      playwrightProcess.stderr.on('data', (data) => {
        stderr += data.toString();
      });
      
      playwrightProcess.on('close', (code) => {
        if (code === 0) {
          this.parsePlaywrightOutput(outputFile, result);
          result.success = true;
          resolve();
        } else {
          result.errors.push(`Playwright exited with code ${code}`);
          if (stderr) result.errors.push(stderr);
          reject(new Error(`Playwright failed with code ${code}`));
        }
      });

      // Safety timeout
      setTimeout(() => {
        playwrightProcess.kill('SIGTERM');
        reject(new Error('Playwright scenario timeout'));
      }, (scenario.duration + 120) * 1000);
    });
  }

  /**
   * Parst K6-Ausgabe
   */
  private parseK6Output(outputFile: string, result: TestResult): void {
    try {
      if (!fs.existsSync(outputFile)) return;
      
      const content = fs.readFileSync(outputFile, 'utf8');
      const lines = content.split('\n').filter(line => line.trim());
      
      lines.forEach(line => {
        try {
          const data = JSON.parse(line);
          
          if (data.type === 'Point' && data.metric) {
            const metricName = data.metric;
            const value = data.data.value;
            
            if (!result.metrics[metricName]) {
              result.metrics[metricName] = {
                min: value,
                max: value,
                avg: value,
                p95: value,
                p99: value,
                count: 1
              };
            } else {
              const metric = result.metrics[metricName];
              metric.min = Math.min(metric.min, value);
              metric.max = Math.max(metric.max, value);
              metric.avg = (metric.avg * metric.count + value) / (metric.count + 1);
              metric.count++;
            }
          }
        } catch (e) {
          // Ignore invalid JSON lines
        }
      });
      
    } catch (error) {
      result.errors.push(`Failed to parse K6 output: ${error}`);
    }
  }

  /**
   * Parst Playwright-Ausgabe
   */
  private parsePlaywrightOutput(outputFile: string, result: TestResult): void {
    try {
      if (!fs.existsSync(outputFile)) return;
      
      const content = fs.readFileSync(outputFile, 'utf8');
      const data = JSON.parse(content);
      
      result.metrics = data.metrics || {};
      result.thresholds = data.thresholds || {};
      
    } catch (error) {
      result.errors.push(`Failed to parse Playwright output: ${error}`);
    }
  }

  /**
   * Analysiert Test-Ergebnisse und generiert Empfehlungen
   */
  private analyzeResults(result: TestResult): void {
    const recommendations: string[] = [];
    
    // Login-Performance prüfen
    if (result.metrics.login_duration) {
      const loginP95 = result.metrics.login_duration.p95;
      if (loginP95 > 100) {
        recommendations.push(`Login-Performance: P95 ${loginP95}ms > Ziel 100ms. Überprüfen Sie Authentifizierungs-Pipeline und Session-Management.`);
      }
    }
    
    // Route-Berechnung prüfen
    if (result.metrics.route_calculation_duration) {
      const routeP95 = result.metrics.route_calculation_duration.p95;
      if (routeP95 > 300) {
        recommendations.push(`Route-Berechnung: P95 ${routeP95}ms > Ziel 300ms. Optimieren Sie Routing-Algorithmus oder implementieren Sie Caching.`);
      }
    }
    
    // Stopp-Updates prüfen
    if (result.metrics.stop_update_duration) {
      const stopUpdateP95 = result.metrics.stop_update_duration.p95;
      if (stopUpdateP95 > 100) {
        recommendations.push(`Stopp-Updates: P95 ${stopUpdateP95}ms > Ziel 100ms. Datenbankabfragen optimieren oder Batch-Updates implementieren.`);
      }
    }
    
    // Fehlerrate prüfen
    if (result.metrics.error_rate) {
      const errorRate = result.metrics.error_rate.avg;
      if (errorRate > 5) {
        recommendations.push(`Fehlerrate: ${errorRate}% > Ziel 5%. Überprüfen Sie Anwendungslogik und Error-Handling.`);
      }
    }
    
    // Throughput-Analyse
    if (result.metrics.http_reqs) {
      const throughput = result.metrics.http_reqs.count / (result.duration / 1000);
      if (throughput < 10) {
        recommendations.push(`Durchsatz: ${throughput.toFixed(2)} req/s ist niedrig. Überprüfen Sie Server-Kapazität und Datenbankverbindungen.`);
      }
    }
    
    result.recommendations = recommendations;
  }

  /**
   * Generiert umfassenden Bericht
   */
  private async generateComprehensiveReport(): Promise<void> {
    const reportData = {
      summary: this.generateSummary(),
      detailed: this.testResults,
      recommendations: this.generateGlobalRecommendations(),
      infrastructure: this.generateInfrastructureRecommendations(),
      slaCompliance: this.analyzeSLACompliance(),
      scaling: this.generateScalingRecommendations(),
      timestamp: new Date().toISOString(),
      testDuration: new Date().getTime() - this.startTime.getTime(),
      metadata: {
        baseUrl: this.baseUrl,
        testRunner: 'LoadTestAgent',
        version: '1.0.0',
        author: '2Brands Media GmbH'
      }
    };

    // JSON-Bericht
    const jsonReport = path.join(this.outputDir, 'comprehensive-report.json');
    fs.writeFileSync(jsonReport, JSON.stringify(reportData, null, 2));

    // HTML-Bericht
    const htmlReport = path.join(this.outputDir, 'comprehensive-report.html');
    fs.writeFileSync(htmlReport, this.generateHTMLReport(reportData));

    // Markdown-Bericht
    const mdReport = path.join(this.outputDir, 'comprehensive-report.md');
    fs.writeFileSync(mdReport, this.generateMarkdownReport(reportData));

    console.log(`\n📄 Berichte generiert:`);
    console.log(`   - JSON: ${jsonReport}`);
    console.log(`   - HTML: ${htmlReport}`);
    console.log(`   - Markdown: ${mdReport}`);
  }

  /**
   * Generiert Test-Zusammenfassung
   */
  private generateSummary(): any {
    const totalTests = this.testResults.length;
    const successfulTests = this.testResults.filter(r => r.success).length;
    const failedTests = totalTests - successfulTests;
    
    const allMetrics = this.testResults.flatMap(r => Object.values(r.metrics));
    const avgLatency = allMetrics.length > 0 ? 
      allMetrics.reduce((sum, m) => sum + m.avg, 0) / allMetrics.length : 0;
    
    return {
      totalTests,
      successfulTests,
      failedTests,
      successRate: (successfulTests / totalTests) * 100,
      averageLatency: Math.round(avgLatency),
      totalDuration: new Date().getTime() - this.startTime.getTime(),
      criticalIssues: this.testResults.reduce((sum, r) => sum + r.errors.length, 0)
    };
  }

  /**
   * Generiert globale Empfehlungen
   */
  private generateGlobalRecommendations(): string[] {
    const recommendations = new Set<string>();
    
    this.testResults.forEach(result => {
      result.recommendations.forEach(rec => recommendations.add(rec));
    });
    
    // Infrastruktur-Empfehlungen
    const failedTests = this.testResults.filter(r => !r.success).length;
    if (failedTests > 0) {
      recommendations.add('System-Stabilität: Mehrere Tests fehlgeschlagen. Infrastruktur und Anwendungslogik überprüfen.');
    }
    
    return Array.from(recommendations);
  }

  /**
   * Generiert Infrastruktur-Empfehlungen
   */
  private generateInfrastructureRecommendations(): any {
    return {
      cpu: 'Überwachen Sie CPU-Auslastung während Peak-Zeiten. Bei >80% horizontale Skalierung erwägen.',
      memory: 'RAM-Verbrauch überwachen. PHP-FPM Pool-Größe anpassen bei Memory-Problemen.',
      database: 'MySQL-Performance optimieren: Query-Cache, Index-Optimierung, Connection-Pooling.',
      caching: 'Redis/Memcached für Session- und Route-Caching implementieren.',
      loadBalancer: 'Bei >50 gleichzeitigen Benutzern Load-Balancer mit mehreren App-Servern.',
      cdn: 'CDN für statische Assets implementieren zur Reduzierung der Server-Last.'
    };
  }

  /**
   * Analysiert SLA-Compliance
   */
  private analyzeSLACompliance(): any {
    const slaTargets = {
      'login_duration': 100,
      'route_calculation_duration': 300,
      'stop_update_duration': 100,
      'api_response_time': 200
    };
    
    const compliance: any = {};
    
    Object.entries(slaTargets).forEach(([metric, target]) => {
      const results = this.testResults
        .map(r => r.metrics[metric])
        .filter(m => m !== undefined);
      
      if (results.length > 0) {
        const avgP95 = results.reduce((sum, m) => sum + m.p95, 0) / results.length;
        compliance[metric] = {
          target,
          actual: Math.round(avgP95),
          compliant: avgP95 <= target,
          deviation: avgP95 > target ? Math.round(((avgP95 - target) / target) * 100) : 0
        };
      }
    });
    
    return compliance;
  }

  /**
   * Generiert Skalierungs-Empfehlungen
   */
  private generateScalingRecommendations(): any {
    return {
      horizontal: {
        description: 'Horizontale Skalierung für hohe Last',
        recommendations: [
          'Load Balancer mit 2-3 Web-Servern für >50 gleichzeitige Benutzer',
          'Database Master-Slave Setup für Read-Heavy Workloads',
          'Separate API-Server für Mobile Apps'
        ]
      },
      vertical: {
        description: 'Vertikale Skalierung für bessere Performance',
        recommendations: [
          'CPU: 4+ Cores für PHP-FPM Worker',
          'RAM: 8GB+ für größere OpCache und Query-Cache',
          'SSD Storage für bessere I/O Performance'
        ]
      },
      caching: {
        description: 'Caching-Strategien',
        recommendations: [
          'Redis für Session-Storage und API-Caching',
          'Route-Cache für berechnete Routen (TTL: 30min)',
          'Geocoding-Cache mit längerer TTL (24h)',
          'Browser-Caching für statische Assets'
        ]
      }
    };
  }

  /**
   * Generiert HTML-Bericht
   */
  private generateHTMLReport(data: any): string {
    return `
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Load Test Report - Metropol Portal</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: #333; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; }
        .metric-card { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #007bff; }
        .metric-value { font-size: 2em; font-weight: bold; color: #007bff; }
        .success { color: #28a745; }
        .warning { color: #ffc107; }
        .error { color: #dc3545; }
        .recommendations { background: #e7f3ff; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .sla-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .sla-table th, .sla-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .sla-table th { background-color: #f8f9fa; }
        .compliant { background-color: #d4edda; }
        .non-compliant { background-color: #f8d7da; }
        footer { text-align: center; margin-top: 40px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Load Test Report - Metropol Portal</h1>
        <p><strong>Entwickelt von:</strong> 2Brands Media GmbH</p>
        <p><strong>Test-Zeitpunkt:</strong> ${new Date(data.timestamp).toLocaleString('de-DE')}</p>
        <p><strong>Gesamt-Dauer:</strong> ${Math.round(data.testDuration / 1000)}s</p>
        
        <h2>📊 Zusammenfassung</h2>
        <div class="summary">
            <div class="metric-card">
                <div class="metric-value ${data.summary.successRate === 100 ? 'success' : 'warning'}">${data.summary.successRate.toFixed(1)}%</div>
                <div>Erfolgsrate</div>
                <small>${data.summary.successfulTests}/${data.summary.totalTests} Tests erfolgreich</small>
            </div>
            <div class="metric-card">
                <div class="metric-value">${data.summary.averageLatency}ms</div>
                <div>Durchschnittliche Latenz</div>
            </div>
            <div class="metric-card">
                <div class="metric-value ${data.summary.criticalIssues === 0 ? 'success' : 'error'}">${data.summary.criticalIssues}</div>
                <div>Kritische Probleme</div>
            </div>
        </div>

        <h2>🎯 SLA-Compliance</h2>
        <table class="sla-table">
            <thead>
                <tr>
                    <th>Metrik</th>
                    <th>Ziel</th>
                    <th>Aktuell</th>
                    <th>Status</th>
                    <th>Abweichung</th>
                </tr>
            </thead>
            <tbody>
                ${Object.entries(data.slaCompliance).map(([metric, info]: [string, any]) => `
                    <tr class="${info.compliant ? 'compliant' : 'non-compliant'}">
                        <td>${metric}</td>
                        <td>${info.target}ms</td>
                        <td>${info.actual}ms</td>
                        <td>${info.compliant ? '✅ Erfüllt' : '❌ Verletzt'}</td>
                        <td>${info.deviation > 0 ? `+${info.deviation}%` : '-'}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>

        <h2>💡 Empfehlungen</h2>
        ${data.recommendations.map((rec: string) => `
            <div class="recommendations">
                <strong>⚠️</strong> ${rec}
            </div>
        `).join('')}

        <h2>🏗️ Skalierungs-Empfehlungen</h2>
        <h3>Horizontale Skalierung</h3>
        <ul>
            ${data.scaling.horizontal.recommendations.map((rec: string) => `<li>${rec}</li>`).join('')}
        </ul>
        
        <h3>Vertikale Skalierung</h3>
        <ul>
            ${data.scaling.vertical.recommendations.map((rec: string) => `<li>${rec}</li>`).join('')}
        </ul>

        <h3>Caching-Strategien</h3>
        <ul>
            ${data.scaling.caching.recommendations.map((rec: string) => `<li>${rec}</li>`).join('')}
        </ul>

        <footer>
            <p>Entwickelt von <strong>2Brands Media GmbH</strong></p>
            <p>LoadTestAgent v1.0.0 - ${new Date().getFullYear()}</p>
        </footer>
    </div>
</body>
</html>`;
  }

  /**
   * Generiert Markdown-Bericht
   */
  private generateMarkdownReport(data: any): string {
    return `# 🚀 Load Test Report - Metropol Portal

**Entwickelt von:** 2Brands Media GmbH  
**Test-Zeitpunkt:** ${new Date(data.timestamp).toLocaleString('de-DE')}  
**Gesamt-Dauer:** ${Math.round(data.testDuration / 1000)}s

## 📊 Zusammenfassung

- **Erfolgsrate:** ${data.summary.successRate.toFixed(1)}% (${data.summary.successfulTests}/${data.summary.totalTests} Tests)
- **Durchschnittliche Latenz:** ${data.summary.averageLatency}ms
- **Kritische Probleme:** ${data.summary.criticalIssues}

## 🎯 SLA-Compliance

| Metrik | Ziel | Aktuell | Status | Abweichung |
|--------|------|---------|--------|------------|
${Object.entries(data.slaCompliance).map(([metric, info]: [string, any]) => 
  `| ${metric} | ${info.target}ms | ${info.actual}ms | ${info.compliant ? '✅ Erfüllt' : '❌ Verletzt'} | ${info.deviation > 0 ? `+${info.deviation}%` : '-'} |`
).join('\n')}

## 💡 Empfehlungen

${data.recommendations.map((rec: string) => `- ⚠️ ${rec}`).join('\n')}

## 🏗️ Skalierungs-Empfehlungen

### Horizontale Skalierung
${data.scaling.horizontal.recommendations.map((rec: string) => `- ${rec}`).join('\n')}

### Vertikale Skalierung
${data.scaling.vertical.recommendations.map((rec: string) => `- ${rec}`).join('\n')}

### Caching-Strategien
${data.scaling.caching.recommendations.map((rec: string) => `- ${rec}`).join('\n')}

---
*Entwickelt von **2Brands Media GmbH** - LoadTestAgent v1.0.0*`;
  }

  /**
   * Hilfsfunktion für Array-Chunking
   */
  private chunkArray<T>(array: T[], size: number): T[][] {
    const chunks: T[][] = [];
    for (let i = 0; i < array.length; i += size) {
      chunks.push(array.slice(i, i + size));
    }
    return chunks;
  }

  /**
   * Definiert alle Load-Test-Konfigurationen
   */
  private getLoadTestConfigs(): LoadTestConfig[] {
    return [
      {
        name: 'Tageszeit-Szenarien',
        description: 'Simuliert reale Benutzungszeiten: Morgenrush, Lunch-Updates, Abend-Abschluss',
        infrastructure: {
          expectedConcurrentUsers: 50,
          expectedThroughput: 100,
          targetLatency: 200
        },
        scenarios: [
          {
            name: 'morningRush',
            type: 'k6',
            script: 'k6-realtime-scenarios.js',
            duration: 300, // 5 Minuten
            users: 50,
            environment: { SCENARIO: 'morningRush' },
            priority: 'critical'
          },
          {
            name: 'lunchUpdate',
            type: 'k6',
            script: 'k6-realtime-scenarios.js',
            duration: 180, // 3 Minuten
            users: 30,
            environment: { SCENARIO: 'lunchUpdate' },
            priority: 'critical'
          },
          {
            name: 'eveningClose',
            type: 'k6',
            script: 'k6-realtime-scenarios.js',
            duration: 240, // 4 Minuten
            users: 25,
            environment: { SCENARIO: 'eveningClose' },
            priority: 'high'
          }
        ]
      },
      {
        name: 'Kapazitäts-Tests',
        description: 'Testet System-Grenzen: Normal Load, Peak Load, Stress Test',
        infrastructure: {
          expectedConcurrentUsers: 200,
          expectedThroughput: 300,
          targetLatency: 500
        },
        scenarios: [
          {
            name: 'normalLoad',
            type: 'k6',
            script: 'k6-realtime-scenarios.js',
            duration: 300, // 5 Minuten
            users: 25,
            environment: { SCENARIO: 'normalLoad' },
            priority: 'high'
          },
          {
            name: 'peakLoad',
            type: 'k6',
            script: 'k6-realtime-scenarios.js',
            duration: 120, // 2 Minuten
            users: 100,
            environment: { SCENARIO: 'peakLoad' },
            priority: 'high'
          },
          {
            name: 'stressTest',
            type: 'k6',
            script: 'k6-realtime-scenarios.js',
            duration: 180, // 3 Minuten
            users: 200,
            environment: { SCENARIO: 'stressTest' },
            priority: 'medium'
          }
        ]
      },
      {
        name: 'Browser-Simulation',
        description: 'Realistische Browser-Tests mit verschiedenen Geräten und Browsern',
        infrastructure: {
          expectedConcurrentUsers: 25,
          expectedThroughput: 50,
          targetLatency: 300
        },
        scenarios: [
          {
            name: 'morningRushBrowser',
            type: 'playwright',
            script: 'playwright-load-scenarios.ts',
            duration: 180, // 3 Minuten
            users: 25,
            environment: { SCENARIO: 'morningRush' },
            priority: 'medium'
          },
          {
            name: 'lunchUpdateBrowser',
            type: 'playwright',
            script: 'playwright-load-scenarios.ts',
            duration: 120, // 2 Minuten
            users: 15,
            environment: { SCENARIO: 'lunchUpdate' },
            priority: 'low'
          }
        ]
      }
    ];
  }
}

// CLI-Interface
if (require.main === module) {
  const args = process.argv.slice(2);
  const baseUrl = args[0] || 'http://localhost:8000';
  const outputDir = args[1] || './test-results';
  
  const runner = new LoadTestRunner(baseUrl, outputDir);
  
  runner.runAllScenarios().catch(error => {
    console.error('❌ Load-Test-Runner Fehler:', error);
    process.exit(1);
  });
}

export default LoadTestRunner;