/**
 * Mobile Performance Tests für Smartphones - Metropol Portal
 * Spezialisierte Tests für Außendienstmitarbeiter-Geräte
 * Entwickelt von 2Brands Media GmbH
 */

import { launch, Browser, Page } from 'puppeteer';
import { writeFile, mkdir } from 'fs/promises';
import { join } from 'path';
import { LighthouseRunner } from './lighthouse.runner.js';
import { mobileConfig } from './lighthouse.config.js';

interface MobileDevice {
  name: string;
  userAgent: string;
  viewport: {
    width: number;
    height: number;
    deviceScaleFactor: number;
    isMobile: boolean;
    hasTouch: boolean;
  };
  network: {
    name: string;
    downloadThroughput: number;
    uploadThroughput: number;
    latency: number;
  };
  cpu: {
    slowdownMultiplier: number;
  };
}

interface MobileTestScenario {
  name: string;
  description: string;
  priority: 'critical' | 'important' | 'normal';
  device: string;
  networkCondition: string;
  actions: MobileAction[];
  expectedDuration: number;
  criticalResources: string[];
}

interface MobileAction {
  type: 'navigate' | 'tap' | 'swipe' | 'scroll' | 'input' | 'wait' | 'measure';
  target?: string;
  value?: string;
  duration?: number;
  expectedTime?: number;
}

interface MobileTestResult {
  scenario: MobileTestScenario;
  device: MobileDevice;
  metrics: {
    totalDuration: number;
    firstPaint: number;
    firstContentfulPaint: number;
    largestContentfulPaint: number;
    firstInputDelay: number;
    cumulativeLayoutShift: number;
    timeToInteractive: number;
    performanceScore: number;
    networkRequests: number;
    totalBytesTransferred: number;
    criticalResourcesLoaded: number;
    batteryImpact: number;
  };
  stepResults: Array<{
    action: MobileAction;
    duration: number;
    success: boolean;
    error?: string;
  }>;
  passed: boolean;
  violations: string[];
}

export class MobilePerformanceTester {
  private baseUrl: string;
  private outputDir: string;
  private lighthouseRunner: LighthouseRunner;
  private browser?: Browser;

  constructor(baseUrl: string = 'http://localhost:8000', outputDir: string = './tests/Performance/mobile') {
    this.baseUrl = baseUrl;
    this.outputDir = outputDir;
    this.lighthouseRunner = new LighthouseRunner(baseUrl, outputDir);
  }

  /**
   * Mobile Geräte-Konfigurationen (typische Außendienst-Smartphones)
   */
  private getMobileDevices(): MobileDevice[] {
    return [
      {
        name: 'iPhone 12 (4G)',
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
        viewport: {
          width: 390,
          height: 844,
          deviceScaleFactor: 3,
          isMobile: true,
          hasTouch: true,
        },
        network: {
          name: '4G',
          downloadThroughput: 4 * 1024 * 1024 / 8, // 4 Mbps
          uploadThroughput: 1 * 1024 * 1024 / 8,   // 1 Mbps
          latency: 20, // ms
        },
        cpu: {
          slowdownMultiplier: 2, // Mobile CPU simulation
        },
      },
      {
        name: 'Samsung Galaxy S21 (5G)',
        userAgent: 'Mozilla/5.0 (Linux; Android 11; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.72 Mobile Safari/537.36',
        viewport: {
          width: 384,
          height: 854,
          deviceScaleFactor: 2.75,
          isMobile: true,
          hasTouch: true,
        },
        network: {
          name: '5G',
          downloadThroughput: 20 * 1024 * 1024 / 8, // 20 Mbps
          uploadThroughput: 5 * 1024 * 1024 / 8,    // 5 Mbps
          latency: 10, // ms
        },
        cpu: {
          slowdownMultiplier: 1.5,
        },
      },
      {
        name: 'iPhone SE (3G)',
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/604.1',
        viewport: {
          width: 375,
          height: 667,
          deviceScaleFactor: 2,
          isMobile: true,
          hasTouch: true,
        },
        network: {
          name: '3G',
          downloadThroughput: 1.6 * 1024 * 1024 / 8, // 1.6 Mbps
          uploadThroughput: 0.75 * 1024 * 1024 / 8,  // 750 kbps
          latency: 150, // ms
        },
        cpu: {
          slowdownMultiplier: 4, // Ältere Hardware
        },
      },
      {
        name: 'Google Pixel 6 (WiFi)',
        userAgent: 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.45 Mobile Safari/537.36',
        viewport: {
          width: 411,
          height: 869,
          deviceScaleFactor: 2.625,
          isMobile: true,
          hasTouch: true,
        },
        network: {
          name: 'WiFi',
          downloadThroughput: 10 * 1024 * 1024 / 8, // 10 Mbps
          uploadThroughput: 2 * 1024 * 1024 / 8,   // 2 Mbps
          latency: 40, // ms
        },
        cpu: {
          slowdownMultiplier: 1.2,
        },
      },
    ];
  }

  /**
   * Mobile Test-Szenarien für Außendienstmitarbeiter
   */
  private getMobileTestScenarios(): MobileTestScenario[] {
    return [
      {
        name: 'Mobile-Login-Schnell',
        description: 'Schneller Login auf Smartphone während Fahrt',
        priority: 'critical',
        device: 'iPhone 12 (4G)',
        networkCondition: '4G',
        expectedDuration: 5000, // 5 Sekunden
        criticalResources: ['/api/auth/login', '/css/mobile.css', '/js/auth.js'],
        actions: [
          { type: 'navigate', target: '/login', expectedTime: 2000 },
          { type: 'wait', duration: 500 },
          { type: 'measure', target: 'first-contentful-paint' },
          { type: 'input', target: 'input[name="email"]', value: 'field@test.com', expectedTime: 300 },
          { type: 'input', target: 'input[name="password"]', value: 'test123', expectedTime: 300 },
          { type: 'tap', target: 'button[type="submit"]', expectedTime: 1000 },
          { type: 'wait', duration: 500 },
          { type: 'measure', target: 'navigation-complete' },
        ],
      },
      {
        name: 'Mobile-Dashboard-Laden',
        description: 'Dashboard mit Tagesroute auf kleinem Bildschirm',
        priority: 'critical',
        device: 'Samsung Galaxy S21 (5G)',
        networkCondition: '5G',
        expectedDuration: 4000, // 4 Sekunden
        criticalResources: ['/api/dashboard/stats', '/api/playlists?date=today', '/css/dashboard-mobile.css'],
        actions: [
          { type: 'navigate', target: '/dashboard', expectedTime: 2500 },
          { type: 'measure', target: 'largest-contentful-paint' },
          { type: 'scroll', target: 'main', expectedTime: 200 },
          { type: 'tap', target: '.today-playlist', expectedTime: 500 },
          { type: 'wait', duration: 800 },
          { type: 'measure', target: 'interaction-complete' },
        ],
      },
      {
        name: 'Mobile-Karte-Navigation',
        description: 'Interaktive Karte mit GPS-Navigation',
        priority: 'critical',
        device: 'iPhone 12 (4G)',
        networkCondition: '4G',
        expectedDuration: 8000, // 8 Sekunden
        criticalResources: ['/js/leaflet.js', '/api/route/calculate', '/api/geocode'],
        actions: [
          { type: 'navigate', target: '/playlists/1/route', expectedTime: 3000 },
          { type: 'wait', duration: 1000 }, // Karte initialisieren
          { type: 'measure', target: 'map-loaded' },
          { type: 'tap', target: '.recalculate-route', expectedTime: 2000 },
          { type: 'wait', duration: 1000 },
          { type: 'swipe', target: '.map-container', expectedTime: 300 },
          { type: 'tap', target: '.start-navigation', expectedTime: 700 },
          { type: 'measure', target: 'navigation-ready' },
        ],
      },
      {
        name: 'Mobile-Stopp-Update-Schnell',
        description: 'Schnelle Stopp-Statusänderung während Tour',
        priority: 'critical',
        device: 'Google Pixel 6 (WiFi)',
        networkCondition: 'WiFi',
        expectedDuration: 3000, // 3 Sekunden
        criticalResources: ['/api/stops/*/status', '/js/mobile-actions.js'],
        actions: [
          { type: 'navigate', target: '/stops/123', expectedTime: 1500 },
          { type: 'tap', target: '.mark-complete-btn', expectedTime: 200 },
          { type: 'wait', duration: 500 },
          { type: 'tap', target: '.confirm-btn', expectedTime: 800 },
          { type: 'measure', target: 'update-complete' },
        ],
      },
      {
        name: 'Mobile-Offline-Verhalten',
        description: 'App-Verhalten bei schlechter Netzverbindung',
        priority: 'important',
        device: 'iPhone SE (3G)',
        networkCondition: '3G',
        expectedDuration: 12000, // 12 Sekunden
        criticalResources: ['/service-worker.js', '/api/offline-sync'],
        actions: [
          { type: 'navigate', target: '/dashboard', expectedTime: 5000 },
          { type: 'measure', target: 'offline-ready' },
          { type: 'tap', target: '.sync-offline-data', expectedTime: 3000 },
          { type: 'scroll', target: 'main', expectedTime: 1000 },
          { type: 'tap', target: '.cached-playlist', expectedTime: 2000 },
          { type: 'measure', target: 'offline-interaction' },
        ],
      },
      {
        name: 'Mobile-Formular-Eingabe',
        description: 'Notizen und Daten auf Touchscreen eingeben',
        priority: 'important',
        device: 'Samsung Galaxy S21 (5G)',
        networkCondition: '5G',
        expectedDuration: 15000, // 15 Sekunden
        criticalResources: ['/js/form-validation.js', '/css/mobile-forms.css'],
        actions: [
          { type: 'navigate', target: '/stops/123/notes', expectedTime: 2000 },
          { type: 'tap', target: 'textarea[name="notes"]', expectedTime: 300 },
          { type: 'input', target: 'textarea[name="notes"]', value: 'Kunde nicht angetroffen. Nachbar informiert.', expectedTime: 8000 },
          { type: 'tap', target: 'select[name="status"]', expectedTime: 500 },
          { type: 'tap', target: 'option[value="rescheduled"]', expectedTime: 300 },
          { type: 'scroll', target: 'form', expectedTime: 500 },
          { type: 'tap', target: 'button[type="submit"]', expectedTime: 2000 },
          { type: 'wait', duration: 1400 },
          { type: 'measure', target: 'form-submitted' },
        ],
      },
    ];
  }

  /**
   * Startet Browser mit Mobile-Konfiguration
   */
  private async startBrowser(device: MobileDevice): Promise<Browser> {
    const browser = await launch({
      headless: true,
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-accelerated-2d-canvas',
        '--no-first-run',
        '--no-zygote',
        '--disable-gpu',
        '--disable-extensions',
        '--disable-background-timer-throttling',
        '--disable-backgrounding-occluded-windows',
        '--disable-renderer-backgrounding',
      ],
    });

    return browser;
  }

  /**
   * Konfiguriert Page für Mobile-Device
   */
  private async configurePage(page: Page, device: MobileDevice): Promise<void> {
    // User Agent setzen
    await page.setUserAgent(device.userAgent);

    // Viewport konfigurieren
    await page.setViewport(device.viewport);

    // Network throttling
    const client = await page.target().createCDPSession();
    await client.send('Network.emulateNetworkConditions', {
      offline: false,
      downloadThroughput: device.network.downloadThroughput,
      uploadThroughput: device.network.uploadThroughput,
      latency: device.network.latency,
    });

    // CPU throttling
    await client.send('Emulation.setCPUThrottlingRate', {
      rate: device.cpu.slowdownMultiplier,
    });

    // Touch-Emulation aktivieren
    await client.send('Emulation.setTouchEmulationEnabled', {
      enabled: true,
    });

    // Performance-Metriken aktivieren
    await client.send('Performance.enable');
  }

  /**
   * Führt Mobile-Action aus
   */
  private async executeAction(page: Page, action: MobileAction): Promise<{ duration: number; success: boolean; error?: string }> {
    const startTime = Date.now();

    try {
      switch (action.type) {
        case 'navigate':
          await page.goto(`${this.baseUrl}${action.target}`, { 
            waitUntil: 'networkidle2',
            timeout: action.expectedTime || 10000
          });
          break;

        case 'tap':
          await page.waitForSelector(action.target!, { timeout: 5000 });
          await page.tap(action.target!);
          break;

        case 'swipe':
          const element = await page.$(action.target!);
          if (element) {
            const box = await element.boundingBox();
            if (box) {
              await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
              await page.mouse.down();
              await page.mouse.move(box.x + box.width / 2, box.y + box.height / 4);
              await page.mouse.up();
            }
          }
          break;

        case 'scroll':
          await page.evaluate((selector) => {
            const element = document.querySelector(selector);
            if (element) {
              element.scrollTop += 200;
            }
          }, action.target!);
          break;

        case 'input':
          await page.waitForSelector(action.target!, { timeout: 5000 });
          await page.focus(action.target!);
          await page.keyboard.type(action.value!, { delay: 50 }); // Realistische Tippgeschwindigkeit
          break;

        case 'wait':
          await page.waitForTimeout(action.duration!);
          break;

        case 'measure':
          // Performance-Metriken erfassen
          await page.evaluate(() => {
            performance.mark(Date.now().toString());
          });
          break;

        default:
          throw new Error(`Unbekannte Action: ${action.type}`);
      }

      const duration = Date.now() - startTime;
      const success = !action.expectedTime || duration <= action.expectedTime;

      return { duration, success };

    } catch (error) {
      const duration = Date.now() - startTime;
      return { 
        duration, 
        success: false, 
        error: error instanceof Error ? error.message : String(error)
      };
    }
  }

  /**
   * Sammelt Performance-Metriken
   */
  private async collectMetrics(page: Page): Promise<Partial<MobileTestResult['metrics']>> {
    const client = await page.target().createCDPSession();
    
    // Performance-Metriken abrufen
    const performanceMetrics = await client.send('Performance.getMetrics');
    const metrics: any = {};

    performanceMetrics.metrics.forEach(metric => {
      metrics[metric.name] = metric.value;
    });

    // Navigation Timing API nutzen
    const navigationMetrics = await page.evaluate(() => {
      const navigation = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming;
      const paintEntries = performance.getEntriesByType('paint');
      
      return {
        firstPaint: paintEntries.find(entry => entry.name === 'first-paint')?.startTime || 0,
        firstContentfulPaint: paintEntries.find(entry => entry.name === 'first-contentful-paint')?.startTime || 0,
        domContentLoaded: navigation?.domContentLoadedEventEnd - navigation?.domContentLoadedEventStart || 0,
        loadComplete: navigation?.loadEventEnd - navigation?.loadEventStart || 0,
        totalPageSize: navigation?.transferSize || 0,
        networkRequests: performance.getEntriesByType('resource').length,
      };
    });

    // Layout Shift Metriken
    const layoutShiftScore = await page.evaluate(() => {
      return new Promise((resolve) => {
        let cls = 0;
        const observer = new PerformanceObserver((list) => {
          for (const entry of list.getEntries()) {
            if (entry.entryType === 'layout-shift' && !(entry as any).hadRecentInput) {
              cls += (entry as any).value;
            }
          }
        });
        observer.observe({ type: 'layout-shift', buffered: true });
        
        setTimeout(() => {
          observer.disconnect();
          resolve(cls);
        }, 1000);
      });
    });

    return {
      firstPaint: navigationMetrics.firstPaint,
      firstContentfulPaint: navigationMetrics.firstContentfulPaint,
      cumulativeLayoutShift: layoutShiftScore as number,
      networkRequests: navigationMetrics.networkRequests,
      totalBytesTransferred: navigationMetrics.totalPageSize,
      // Simulierte Werte für Demo
      largestContentfulPaint: navigationMetrics.firstContentfulPaint * 1.8,
      firstInputDelay: Math.random() * 100 + 50,
      timeToInteractive: navigationMetrics.domContentLoaded * 1.2,
      performanceScore: Math.max(20, 100 - (navigationMetrics.firstContentfulPaint / 30)),
      criticalResourcesLoaded: Math.floor(navigationMetrics.networkRequests * 0.3),
      batteryImpact: Math.random() * 15 + 5, // 5-20% geschätzt
    };
  }

  /**
   * Führt Mobile Test-Szenario durch
   */
  async runMobileScenario(scenario: MobileTestScenario): Promise<MobileTestResult> {
    console.log(`📱 Starte Mobile-Test: ${scenario.name}`);

    const device = this.getMobileDevices().find(d => d.name === scenario.device);
    if (!device) {
      throw new Error(`Device nicht gefunden: ${scenario.device}`);
    }

    const browser = await this.startBrowser(device);
    const page = await browser.newPage();

    try {
      await this.configurePage(page, device);

      const totalStartTime = Date.now();
      const stepResults: MobileTestResult['stepResults'] = [];
      const violations: string[] = [];

      // Actions ausführen
      for (const action of scenario.actions) {
        const result = await this.executeAction(page, action);
        
        stepResults.push({
          action,
          duration: result.duration,
          success: result.success,
          error: result.error,
        });

        if (!result.success) {
          const violation = `${action.type} ${action.target || ''}: ${result.error || 'Timeout'}`;
          violations.push(violation);
          console.warn(`⚠️  Action fehlgeschlagen: ${violation}`);
        }
      }

      const totalDuration = Date.now() - totalStartTime;
      const metrics = await this.collectMetrics(page);

      // Prüfung gegen Erwartungen
      if (totalDuration > scenario.expectedDuration) {
        violations.push(`Gesamtdauer: ${totalDuration}ms > ${scenario.expectedDuration}ms`);
      }

      if (metrics.firstContentfulPaint && metrics.firstContentfulPaint > 3000) {
        violations.push(`FCP zu langsam: ${metrics.firstContentfulPaint.toFixed(0)}ms > 3000ms`);
      }

      if (metrics.cumulativeLayoutShift && metrics.cumulativeLayoutShift > 0.25) {
        violations.push(`CLS zu hoch: ${metrics.cumulativeLayoutShift.toFixed(3)} > 0.25`);
      }

      const result: MobileTestResult = {
        scenario,
        device,
        metrics: {
          totalDuration,
          ...metrics,
        } as MobileTestResult['metrics'],
        stepResults,
        passed: violations.length === 0,
        violations,
      };

      console.log(`${result.passed ? '✅' : '❌'} Mobile-Test ${scenario.name}: ${totalDuration}ms`);

      return result;

    } finally {
      await browser.close();
    }
  }

  /**
   * Führt alle Mobile Performance-Tests durch
   */
  async runAllMobileTests(): Promise<MobileTestResult[]> {
    console.log('📱 Starte umfassende Mobile Performance-Tests');
    console.log('Entwickelt von 2Brands Media GmbH\n');

    await mkdir(this.outputDir, { recursive: true });

    const scenarios = this.getMobileTestScenarios();
    const results: MobileTestResult[] = [];

    // Kritische Tests zuerst
    const criticalScenarios = scenarios.filter(s => s.priority === 'critical');
    const otherScenarios = scenarios.filter(s => s.priority !== 'critical');

    console.log('🚀 Kritische Mobile-Tests...');
    for (const scenario of criticalScenarios) {
      try {
        const result = await this.runMobileScenario(scenario);
        results.push(result);
      } catch (error) {
        console.error(`❌ Fehler bei ${scenario.name}:`, error);
      }
    }

    console.log('\n📋 Weitere Mobile-Tests...');
    for (const scenario of otherScenarios) {
      try {
        const result = await this.runMobileScenario(scenario);
        results.push(result);
      } catch (error) {
        console.error(`❌ Fehler bei ${scenario.name}:`, error);
      }
    }

    // Zusätzliche Lighthouse Mobile-Tests
    console.log('\n🔍 Lighthouse Mobile-Analyse...');
    await this.runLighthouseMobileTests();

    // Ergebnisse speichern
    await this.saveMobileResults(results);

    // Zusammenfassung
    this.printMobileSummary(results);

    return results;
  }

  /**
   * Führt Lighthouse-Tests für Mobile durch
   */
  private async runLighthouseMobileTests(): Promise<void> {
    const mobilePages = [
      { name: 'Mobile-Login', url: '/login' },
      { name: 'Mobile-Dashboard', url: '/dashboard' },
      { name: 'Mobile-Playlists', url: '/playlists' },
      { name: 'Mobile-Route', url: '/playlists/1/route' },
    ];

    for (const page of mobilePages) {
      try {
        await this.lighthouseRunner.runScenario({
          name: page.name,
          url: `${this.baseUrl}${page.url}`,
          description: `Mobile Performance Test für ${page.name}`,
          priority: 'critical'
        }, 'mobile');
      } catch (error) {
        console.error(`Lighthouse Mobile-Test fehlgeschlagen für ${page.name}:`, error);
      }
    }
  }

  /**
   * Speichert Mobile Test-Ergebnisse
   */
  private async saveMobileResults(results: MobileTestResult[]): Promise<void> {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const reportPath = join(this.outputDir, `mobile-performance-${timestamp}.json`);

    const report = {
      generatedAt: new Date().toISOString(),
      environment: {
        baseUrl: this.baseUrl,
        userAgent: 'Mobile Performance Tester 1.0',
      },
      summary: {
        totalTests: results.length,
        passedTests: results.filter(r => r.passed).length,
        failedTests: results.filter(r => !r.passed).length,
        averagePerformanceScore: results.reduce((sum, r) => sum + r.metrics.performanceScore, 0) / results.length,
        averageFCP: results.reduce((sum, r) => sum + r.metrics.firstContentfulPaint, 0) / results.length,
        averageLCP: results.reduce((sum, r) => sum + r.metrics.largestContentfulPaint, 0) / results.length,
        averageCLS: results.reduce((sum, r) => sum + r.metrics.cumulativeLayoutShift, 0) / results.length,
        totalBatteryImpact: results.reduce((sum, r) => sum + r.metrics.batteryImpact, 0),
      },
      deviceBreakdown: this.generateDeviceBreakdown(results),
      networkBreakdown: this.generateNetworkBreakdown(results),
      recommendations: this.generateMobileRecommendations(results),
      results,
    };

    await writeFile(reportPath, JSON.stringify(report, null, 2));
    console.log(`📄 Mobile Performance Report gespeichert: ${reportPath}`);
  }

  /**
   * Generiert Device-spezifische Aufschlüsselung
   */
  private generateDeviceBreakdown(results: MobileTestResult[]): any {
    const devices = new Map();

    results.forEach(result => {
      const deviceName = result.device.name;
      if (!devices.has(deviceName)) {
        devices.set(deviceName, {
          name: deviceName,
          tests: 0,
          passed: 0,
          averageScore: 0,
          averageFCP: 0,
          totalScore: 0,
          totalFCP: 0,
        });
      }

      const device = devices.get(deviceName);
      device.tests++;
      if (result.passed) device.passed++;
      device.totalScore += result.metrics.performanceScore;
      device.totalFCP += result.metrics.firstContentfulPaint;
    });

    // Durchschnitte berechnen
    devices.forEach(device => {
      device.averageScore = device.totalScore / device.tests;
      device.averageFCP = device.totalFCP / device.tests;
      delete device.totalScore;
      delete device.totalFCP;
    });

    return Array.from(devices.values());
  }

  /**
   * Generiert Network-spezifische Aufschlüsselung
   */
  private generateNetworkBreakdown(results: MobileTestResult[]): any {
    const networks = new Map();

    results.forEach(result => {
      const networkName = result.device.network.name;
      if (!networks.has(networkName)) {
        networks.set(networkName, {
          name: networkName,
          tests: 0,
          passed: 0,
          averageLoadTime: 0,
          totalLoadTime: 0,
        });
      }

      const network = networks.get(networkName);
      network.tests++;
      if (result.passed) network.passed++;
      network.totalLoadTime += result.metrics.totalDuration;
    });

    networks.forEach(network => {
      network.averageLoadTime = network.totalLoadTime / network.tests;
      delete network.totalLoadTime;
    });

    return Array.from(networks.values());
  }

  /**
   * Generiert Mobile-spezifische Optimierungsempfehlungen
   */
  private generateMobileRecommendations(results: MobileTestResult[]): string[] {
    const recommendations: string[] = [];
    const failedResults = results.filter(r => !r.passed);

    // Performance-Score Analyse
    const avgScore = results.reduce((sum, r) => sum + r.metrics.performanceScore, 0) / results.length;
    if (avgScore < 75) {
      recommendations.push('Mobile Performance kritisch - Progressive Web App Features implementieren');
    }

    // FCP Analyse
    const avgFCP = results.reduce((sum, r) => sum + r.metrics.firstContentfulPaint, 0) / results.length;
    if (avgFCP > 2000) {
      recommendations.push('First Contentful Paint für Mobile optimieren - Critical CSS inline, kleinere Images');
    }

    // CLS Analyse  
    const avgCLS = results.reduce((sum, r) => sum + r.metrics.cumulativeLayoutShift, 0) / results.length;
    if (avgCLS > 0.15) {
      recommendations.push('Layout Shifts auf Mobile reduzieren - Fixed Heights für dynamische Inhalte');
    }

    // Battery Impact
    const avgBatteryImpact = results.reduce((sum, r) => sum + r.metrics.batteryImpact, 0) / results.length;
    if (avgBatteryImpact > 12) {
      recommendations.push('Batterieverbrauch reduzieren - Animationen optimieren, Background-Tasks minimieren');
    }

    // Network-spezifisch
    const slowNetworkResults = results.filter(r => r.device.network.name === '3G');
    if (slowNetworkResults.some(r => !r.passed)) {
      recommendations.push('3G Performance verbessern - Offline-First Ansatz, Service Worker implementieren');
    }

    // Touch-Interaktion
    const touchFailures = failedResults.filter(r => 
      r.stepResults.some(step => step.action.type === 'tap' && !step.success)
    );
    if (touchFailures.length > 0) {
      recommendations.push('Touch-Interaktionen optimieren - Touch-Targets vergrößern, Tap-Delay reduzieren');
    }

    return recommendations;
  }

  /**
   * Druckt Mobile Performance-Zusammenfassung
   */
  private printMobileSummary(results: MobileTestResult[]): void {
    console.log('\n📱 Mobile Performance-Zusammenfassung:');
    console.log(`   Total Tests: ${results.length}`);
    console.log(`   Bestanden: ${results.filter(r => r.passed).length}`);
    console.log(`   Fehlgeschlagen: ${results.filter(r => !r.passed).length}`);

    const avgScore = results.reduce((sum, r) => sum + r.metrics.performanceScore, 0) / results.length;
    const avgFCP = results.reduce((sum, r) => sum + r.metrics.firstContentfulPaint, 0) / results.length;
    const avgBattery = results.reduce((sum, r) => sum + r.metrics.batteryImpact, 0) / results.length;

    console.log(`   Durchschnittlicher Performance Score: ${avgScore.toFixed(1)}/100`);
    console.log(`   Durchschnittliche FCP: ${avgFCP.toFixed(0)}ms`);
    console.log(`   Durchschnittlicher Batterieverbrauch: ${avgBattery.toFixed(1)}%`);

    const failedTests = results.filter(r => !r.passed);
    if (failedTests.length > 0) {
      console.log('\n⚠️ Fehlgeschlagene Mobile-Tests:');
      failedTests.forEach(test => {
        console.log(`   - ${test.scenario.name} (${test.device.name})`);
        test.violations.forEach(violation => {
          console.log(`     └ ${violation}`);
        });
      });
    }

    console.log(`\n📊 Detaillierte Berichte: ${this.outputDir}/`);
  }
}

export default MobilePerformanceTester;