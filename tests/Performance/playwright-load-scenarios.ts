/**
 * Playwright Load-Testing-Szenarien für Metropol Portal
 * Realistische Lasttest-Szenarien mit Browser-Simulation
 * Entwickelt von 2Brands Media GmbH
 */

import { test, expect, Browser, BrowserContext, Page } from '@playwright/test';
import { chromium, firefox, webkit } from '@playwright/test';

// Konfiguration für Load-Tests
interface LoadTestConfig {
  baseURL: string;
  concurrentUsers: number;
  duration: number; // Sekunden
  scenario: string;
  userDistribution: {
    fieldWorkers: number;
    admins: number;
    supervisors: number;
  };
  devices: {
    desktop: number;
    mobile: number;
    tablet: number;
  };
  browsers: {
    chrome: number;
    firefox: number;
    safari: number;
  };
}

// Standard-Konfigurationen für verschiedene Tageszeiten
const scenarioConfigs: { [key: string]: LoadTestConfig } = {
  morningRush: {
    baseURL: process.env.BASE_URL || 'http://localhost:8000',
    concurrentUsers: 50,
    duration: 120, // 2 Minuten
    scenario: 'morning_rush_7_9am',
    userDistribution: {
      fieldWorkers: 80, // 40 Benutzer
      admins: 15,      // 7-8 Benutzer
      supervisors: 5   // 2-3 Benutzer
    },
    devices: {
      desktop: 30,     // Büro-PCs für Admins
      mobile: 60,      // Field Workers mit Smartphones
      tablet: 10       // Supervisor mit Tablets
    },
    browsers: {
      chrome: 70,      // Haupt-Browser
      firefox: 20,     // Alternative
      safari: 10       // Mobile Safari
    }
  },

  lunchUpdate: {
    baseURL: process.env.BASE_URL || 'http://localhost:8000',
    concurrentUsers: 30,
    duration: 60, // 1 Minute
    scenario: 'lunch_update_12_1pm',
    userDistribution: {
      fieldWorkers: 85, // 25-26 Benutzer
      admins: 10,      // 3 Benutzer
      supervisors: 5   // 1-2 Benutzer
    },
    devices: {
      desktop: 20,
      mobile: 70,      // Hauptsächlich mobile Updates
      tablet: 10
    },
    browsers: {
      chrome: 75,
      firefox: 15,
      safari: 10
    }
  },

  eveningClose: {
    baseURL: process.env.BASE_URL || 'http://localhost:8000',
    concurrentUsers: 25,
    duration: 90, // 1.5 Minuten
    scenario: 'evening_close_5_6pm',
    userDistribution: {
      fieldWorkers: 70, // 17-18 Benutzer
      admins: 20,      // 5 Benutzer
      supervisors: 10  // 2-3 Benutzer
    },
    devices: {
      desktop: 40,     // Mehr Desktop für Reports
      mobile: 50,      // Field Worker abschließen
      tablet: 10
    },
    browsers: {
      chrome: 65,
      firefox: 25,
      safari: 10
    }
  },

  normalLoad: {
    baseURL: process.env.BASE_URL || 'http://localhost:8000',
    concurrentUsers: 25,
    duration: 300, // 5 Minuten
    scenario: 'normal_load_25_users',
    userDistribution: {
      fieldWorkers: 65,
      admins: 25,
      supervisors: 10
    },
    devices: {
      desktop: 35,
      mobile: 55,
      tablet: 10
    },
    browsers: {
      chrome: 70,
      firefox: 20,
      safari: 10
    }
  },

  peakLoad: {
    baseURL: process.env.BASE_URL || 'http://localhost:8000',
    concurrentUsers: 100,
    duration: 120, // 2 Minuten
    scenario: 'peak_load_100_users',
    userDistribution: {
      fieldWorkers: 70,
      admins: 20,
      supervisors: 10
    },
    devices: {
      desktop: 30,
      mobile: 60,
      tablet: 10
    },
    browsers: {
      chrome: 75,
      firefox: 15,
      safari: 10
    }
  },

  stressTest: {
    baseURL: process.env.BASE_URL || 'http://localhost:8000',
    concurrentUsers: 200,
    duration: 180, // 3 Minuten
    scenario: 'stress_test_200_plus_users',
    userDistribution: {
      fieldWorkers: 75,
      admins: 15,
      supervisors: 10
    },
    devices: {
      desktop: 25,
      mobile: 65,
      tablet: 10
    },
    browsers: {
      chrome: 80,
      firefox: 15,
      safari: 5
    }
  }
};

// Test-Daten
const testUsers = {
  fieldWorkers: [
    { email: 'field1@test.com', password: 'field123', name: 'Max Mustermann' },
    { email: 'field2@test.com', password: 'field123', name: 'Anna Schmidt' },
    { email: 'field3@test.com', password: 'field123', name: 'Tom Weber' },
    { email: 'field4@test.com', password: 'field123', name: 'Lisa Meyer' },
    { email: 'field5@test.com', password: 'field123', name: 'Chris Müller' }
  ],
  admins: [
    { email: 'admin@test.com', password: 'admin123', name: 'Admin User' },
    { email: 'admin2@test.com', password: 'admin123', name: 'Sarah Admin' }
  ],
  supervisors: [
    { email: 'supervisor@test.com', password: 'super123', name: 'Supervisor One' },
    { email: 'supervisor2@test.com', password: 'super123', name: 'Mark Supervisor' }
  ]
};

const berlinAddresses = [
  'Hauptstraße 1, 10115 Berlin',
  'Friedrichstraße 50, 10117 Berlin',
  'Unter den Linden 77, 10117 Berlin',
  'Alexanderplatz 5, 10178 Berlin',
  'Potsdamer Platz 1, 10785 Berlin',
  'Kurfürstendamm 100, 10711 Berlin',
  'Hackescher Markt 2, 10178 Berlin',
  'Gendarmenmarkt 1, 10117 Berlin',
  'Brandenburger Tor, 10117 Berlin',
  'Checkpoint Charlie, 10969 Berlin',
  'Museumsinsel, 10178 Berlin',
  'Prenzlauer Berg 15, 10405 Berlin',
  'Kreuzberg SO36, 10997 Berlin',
  'Charlottenburg Palace, 14059 Berlin',
  'Tempelhof Airport, 12101 Berlin'
];

// Performance-Metriken sammeln
class PerformanceMetrics {
  private metrics: Map<string, number[]> = new Map();
  
  addMetric(name: string, value: number): void {
    if (!this.metrics.has(name)) {
      this.metrics.set(name, []);
    }
    this.metrics.get(name)!.push(value);
  }

  getStats(name: string): { min: number; max: number; avg: number; p95: number; p99: number } | null {
    const values = this.metrics.get(name);
    if (!values || values.length === 0) return null;

    const sorted = [...values].sort((a, b) => a - b);
    const len = sorted.length;

    return {
      min: sorted[0],
      max: sorted[len - 1],
      avg: values.reduce((a, b) => a + b, 0) / len,
      p95: sorted[Math.floor(len * 0.95)],
      p99: sorted[Math.floor(len * 0.99)]
    };
  }

  getAllStats(): { [key: string]: any } {
    const result: { [key: string]: any } = {};
    for (const [name, _] of this.metrics) {
      result[name] = this.getStats(name);
    }
    return result;
  }
}

// Benutzer-Session-Klasse
class UserSession {
  private page: Page;
  private userType: string;
  private userData: any;
  private metrics: PerformanceMetrics;
  private baseURL: string;
  private sessionStartTime: number;

  constructor(page: Page, userType: string, userData: any, metrics: PerformanceMetrics, baseURL: string) {
    this.page = page;
    this.userType = userType;
    self.userData = userData;
    this.metrics = metrics;
    this.baseURL = baseURL;
    this.sessionStartTime = Date.now();
  }

  async login(): Promise<boolean> {
    const startTime = Date.now();
    
    try {
      await this.page.goto(`${this.baseURL}/login`);
      
      // Warten auf Login-Form
      await this.page.waitForSelector('#email', { timeout: 5000 });
      
      // Login-Daten eingeben
      await this.page.fill('#email', this.userData.email);
      await this.page.fill('#password', this.userData.password);
      
      // Login-Button klicken
      await this.page.click('button[type="submit"]');
      
      // Erfolgreiche Weiterleitung prüfen
      await this.page.waitForURL(/\/dashboard/, { timeout: 10000 });
      
      const duration = Date.now() - startTime;
      this.metrics.addMetric('login_duration', duration);
      this.metrics.addMetric(`${this.userType}_login_duration`, duration);
      
      // Ziel: Login unter 100ms (aus CLAUDE.md)
      if (duration > 100) {
        console.warn(`⚠️  Login für ${this.userType} dauerte ${duration}ms (Ziel: <100ms)`);
      }
      
      return true;
    } catch (error) {
      const duration = Date.now() - startTime;
      this.metrics.addMetric('login_errors', 1);
      console.error(`❌ Login-Fehler für ${this.userType}:`, error);
      return false;
    }
  }

  async performFieldWorkerMorningRoutine(): Promise<void> {
    if (!await this.login()) return;

    try {
      // 1. Dashboard anzeigen
      const dashboardStart = Date.now();
      await this.page.goto(`${this.baseURL}/dashboard`);
      await this.page.waitForLoadState('networkidle');
      this.metrics.addMetric('dashboard_load_time', Date.now() - dashboardStart);

      // Realistische Denkzeit
      await this.page.waitForTimeout(this.randomDelay(2000, 5000));

      // 2. Heutige Playlist laden
      const playlistStart = Date.now();
      await this.page.click('a[href*="/playlists"]');
      await this.page.waitForSelector('.playlist-item', { timeout: 10000 });
      this.metrics.addMetric('playlist_load_time', Date.now() - playlistStart);

      // Erste Playlist auswählen
      const firstPlaylist = await this.page.$('.playlist-item');
      if (firstPlaylist) {
        await firstPlaylist.click();
        await this.page.waitForLoadState('networkidle');

        await this.page.waitForTimeout(this.randomDelay(5000, 10000));

        // 3. Route berechnen
        const routeStart = Date.now();
        await this.page.click('button[data-action="calculate-route"]');
        
        // Warten auf Route-Berechnung
        await this.page.waitForSelector('.route-result', { timeout: 15000 });
        const routeDuration = Date.now() - routeStart;
        this.metrics.addMetric('route_calculation_duration', routeDuration);

        // Ziel: Route-Berechnung unter 300ms (aus CLAUDE.md)
        if (routeDuration > 300) {
          console.warn(`⚠️  Route-Berechnung dauerte ${routeDuration}ms (Ziel: <300ms)`);
        }

        await this.page.waitForTimeout(this.randomDelay(10000, 15000));

        // 4. Route starten
        await this.page.click('button[data-action="start-route"]');
        await this.page.waitForSelector('.route-started', { timeout: 5000 });
      }

      // 5. Logout
      await this.logout();

    } catch (error) {
      this.metrics.addMetrics('field_worker_errors', 1);
      console.error(`❌ Field Worker Morning Routine Fehler:`, error);
    }
  }

  async performFieldWorkerActiveWork(): Promise<void> {
    if (!await this.login()) return;

    try {
      // Aktive Playlist finden
      await this.page.goto(`${this.baseURL}/playlists/active`);
      await this.page.waitForSelector('.active-playlist', { timeout: 10000 });

      const stopElements = await this.page.$$('.stop-item:not(.completed)');
      
      if (stopElements.length > 0) {
        // Zufälligen offenen Stopp auswählen
        const randomStop = stopElements[Math.floor(Math.random() * stopElements.length)];
        
        await randomStop.click();
        await this.page.waitForLoadState('networkidle');
        
        await this.page.waitForTimeout(this.randomDelay(1000, 3000));

        // Stopp als erledigt markieren
        const updateStart = Date.now();
        await this.page.click('button[data-action="complete-stop"]');
        
        // Optional: Notizen hinzufügen (60% Wahrscheinlichkeit)
        if (Math.random() < 0.6) {
          await this.page.fill('textarea[name="notes"]', `Stopp abgeschlossen - ${new Date().toLocaleTimeString()}`);
          await this.page.waitForTimeout(this.randomDelay(10000, 30000)); // Zeit für Notiz-Eingabe
        }
        
        await this.page.click('button[type="submit"]');
        await this.page.waitForSelector('.stop-completed', { timeout: 5000 });
        
        const updateDuration = Date.now() - updateStart;
        this.metrics.addMetric('stop_update_duration', updateDuration);

        // Ziel: Stopp-Update unter 100ms
        if (updateDuration > 100) {
          console.warn(`⚠️  Stopp-Update dauerte ${updateDuration}ms (Ziel: <100ms)`);
        }
      }

      await this.logout();

    } catch (error) {
      this.metrics.addMetric('field_worker_active_errors', 1);
      console.error(`❌ Field Worker Active Work Fehler:`, error);
    }
  }

  async performAdminPlaylistManagement(): Promise<void> {
    if (!await this.login()) return;

    try {
      // 1. Alle Playlists anzeigen
      await this.page.goto(`${this.baseURL}/playlists`);
      await this.page.waitForSelector('.playlist-overview', { timeout: 10000 });
      
      await this.page.waitForTimeout(this.randomDelay(5000, 10000));

      // 2. Neue Playlist erstellen (70% Wahrscheinlichkeit)
      if (Math.random() < 0.7) {
        const createStart = Date.now();
        
        await this.page.click('button[data-action="create-playlist"]');
        await this.page.waitForSelector('#playlist-form', { timeout: 5000 });

        // Playlist-Daten eingeben
        await this.page.fill('#playlist-name', `Test-Playlist-${Date.now()}`);
        await this.page.fill('#playlist-description', 'Automatisch generierte Playlist für Load-Test');
        await this.page.fill('#playlist-date', new Date().toISOString().split('T')[0]);

        // Stopps hinzufügen (5-20 Stopps)
        const stopCount = Math.floor(Math.random() * 16) + 5;
        for (let i = 0; i < stopCount && i < berlinAddresses.length; i++) {
          await this.page.click('button[data-action="add-stop"]');
          await this.page.fill(`.stop-address-${i}`, berlinAddresses[i]);
          await this.page.fill(`.stop-duration-${i}`, String(Math.floor(Math.random() * 45) + 15));
          
          // Kurze Pause zwischen Stopps
          await this.page.waitForTimeout(this.randomDelay(500, 1500));
        }

        // Playlist speichern
        await this.page.click('button[type="submit"]');
        await this.page.waitForSelector('.playlist-created', { timeout: 15000 });
        
        const createDuration = Date.now() - createStart;
        this.metrics.addMetric('playlist_creation_duration', createDuration);

        await this.page.waitForTimeout(this.randomDelay(20000, 40000));

        // Route optimieren
        await this.page.click('button[data-action="optimize-route"]');
        await this.page.waitForSelector('.route-optimized', { timeout: 20000 });
      }

      // 3. Bestehende Playlist bearbeiten (80% Wahrscheinlichkeit)
      if (Math.random() < 0.8) {
        const playlistElements = await this.page.$$('.playlist-item:not(.active)');
        
        if (playlistElements.length > 0) {
          const randomPlaylist = playlistElements[Math.floor(Math.random() * playlistElements.length)];
          await randomPlaylist.click();
          await this.page.waitForLoadState('networkidle');

          await this.page.waitForTimeout(this.randomDelay(5000, 15000));

          // Playlist-Details bearbeiten
          await this.page.click('button[data-action="edit-playlist"]');
          await this.page.waitForSelector('#edit-playlist-form', { timeout: 5000 });

          const currentName = await this.page.inputValue('#playlist-name');
          await this.page.fill('#playlist-name', currentName + ' (aktualisiert)');

          await this.page.click('button[type="submit"]');
          await this.page.waitForSelector('.playlist-updated', { timeout: 5000 });
        }
      }

      await this.logout();

    } catch (error) {
      this.metrics.addMetric('admin_errors', 1);
      console.error(`❌ Admin Playlist Management Fehler:`, error);
    }
  }

  async performSupervisorMonitoring(): Promise<void> {
    if (!await this.login()) return;

    try {
      // 1. Team-Fortschritt anzeigen
      const progressStart = Date.now();
      await this.page.goto(`${this.baseURL}/reports/progress`);
      await this.page.waitForSelector('.progress-report', { timeout: 15000 });
      this.metrics.addMetric('progress_report_load_time', Date.now() - progressStart);

      await this.page.waitForTimeout(this.randomDelay(10000, 20000));

      // 2. Individuelle Fortschritte prüfen (80% Wahrscheinlichkeit)
      if (Math.random() < 0.8) {
        const workerElements = await this.page.$$('.worker-progress-item');
        
        for (const workerElement of workerElements.slice(0, 3)) { // Max 3 Worker
          await workerElement.click();
          await this.page.waitForSelector('.individual-progress', { timeout: 10000 });
          await this.page.waitForTimeout(this.randomDelay(5000, 15000));
          
          // Zurück zur Übersicht
          await this.page.goBack();
          await this.page.waitForLoadState('networkidle');
        }
      }

      // 3. Performance-Berichte generieren (60% Wahrscheinlichkeit)
      if (Math.random() < 0.6) {
        await this.page.waitForTimeout(this.randomDelay(15000, 30000));

        const reportStart = Date.now();
        await this.page.click('button[data-action="generate-performance-report"]');
        await this.page.waitForSelector('.performance-report', { timeout: 30000 });
        
        const reportDuration = Date.now() - reportStart;
        this.metrics.addMetric('performance_report_generation_time', reportDuration);

        // Ziel: Bericht-Generierung unter 1s
        if (reportDuration > 1000) {
          console.warn(`⚠️  Performance-Bericht dauerte ${reportDuration}ms (Ziel: <1000ms)`);
        }
      }

      await this.logout();

    } catch (error) {
      this.metrics.addMetric('supervisor_errors', 1);
      console.error(`❌ Supervisor Monitoring Fehler:`, error);
    }
  }

  private async logout(): Promise<void> {
    try {
      await this.page.click('button[data-action="logout"]');
      await this.page.waitForURL(/\/login/, { timeout: 5000 });
    } catch (error) {
      console.warn('Logout-Warnung:', error);
    }
  }

  private randomDelay(min: number, max: number): number {
    return Math.floor(Math.random() * (max - min + 1)) + min;
  }
}

// Browser-Manager für verschiedene Browser-Types
class BrowserManager {
  private browsers: Map<string, Browser> = new Map();

  async initializeBrowsers(): Promise<void> {
    this.browsers.set('chromium', await chromium.launch({ headless: true }));
    this.browsers.set('firefox', await firefox.launch({ headless: true }));
    this.browsers.set('webkit', await webkit.launch({ headless: true }));
  }

  async createContext(browserType: string, deviceType: string): Promise<BrowserContext> {
    const browser = this.browsers.get(browserType);
    if (!browser) throw new Error(`Browser ${browserType} nicht verfügbar`);

    const contextOptions: any = {};

    // Device-spezifische Konfiguration
    switch (deviceType) {
      case 'mobile':
        contextOptions.viewport = { width: 375, height: 667 };
        contextOptions.userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15';
        contextOptions.isMobile = true;
        contextOptions.hasTouch = true;
        break;
      case 'tablet':
        contextOptions.viewport = { width: 768, height: 1024 };
        contextOptions.userAgent = 'Mozilla/5.0 (iPad; CPU OS 14_6 like Mac OS X) AppleWebKit/605.1.15';
        contextOptions.hasTouch = true;
        break;
      case 'desktop':
      default:
        contextOptions.viewport = { width: 1920, height: 1080 };
        break;
    }

    return await browser.newContext(contextOptions);
  }

  async closeAll(): Promise<void> {
    for (const browser of this.browsers.values()) {
      await browser.close();
    }
    this.browsers.clear();
  }
}

// Haupt-Load-Test-Runner
class LoadTestRunner {
  private config: LoadTestConfig;
  private metrics: PerformanceMetrics;
  private browserManager: BrowserManager;
  private runningUsers: UserSession[] = [];

  constructor(config: LoadTestConfig) {
    this.config = config;
    this.metrics = new PerformanceMetrics();
    this.browserManager = new BrowserManager();
  }

  async run(): Promise<void> {
    console.log(`🚀 Starte Load-Test: ${this.config.scenario}`);
    console.log(`👥 Concurrent Users: ${this.config.concurrentUsers}`);
    console.log(`⏱️  Duration: ${this.config.duration}s`);
    console.log('Entwickelt von 2Brands Media GmbH');

    const startTime = Date.now();

    try {
      await this.browserManager.initializeBrowsers();
      
      // Benutzer parallel starten
      const userPromises: Promise<void>[] = [];
      
      for (let i = 0; i < this.config.concurrentUsers; i++) {
        const userPromise = this.createAndRunUser(i);
        userPromises.push(userPromise);
        
        // Staggered start - nicht alle Benutzer gleichzeitig
        if (i < this.config.concurrentUsers - 1) {
          await new Promise(resolve => setTimeout(resolve, Math.random() * 1000));
        }
      }

      // Auf alle Benutzer warten
      await Promise.allSettled(userPromises);

      const totalDuration = Date.now() - startTime;
      console.log(`✅ Load-Test abgeschlossen in ${totalDuration}ms`);
      
      // Metriken ausgeben
      this.printMetrics();

    } finally {
      await this.browserManager.closeAll();
    }
  }

  private async createAndRunUser(userIndex: number): Promise<void> {
    const userType = this.determineUserType();
    const userData = this.getUserData(userType);
    const deviceType = this.determineDeviceType();
    const browserType = this.determineBrowserType();

    try {
      const context = await this.browserManager.createContext(browserType, deviceType);
      const page = await context.newPage();

      // Network monitoring für Performance-Metriken
      page.on('response', response => {
        if (response.url().includes('/api/')) {
          this.metrics.addMetric('api_response_time', response.request().timing()?.responseEnd || 0);
        }
      });

      const userSession = new UserSession(page, userType, userData, this.metrics, this.config.baseURL);

      const endTime = Date.now() + (this.config.duration * 1000);
      
      while (Date.now() < endTime) {
        switch (userType) {
          case 'fieldWorker':
            if (Math.random() < 0.4) {
              await userSession.performFieldWorkerActiveWork();
            } else {
              await userSession.performFieldWorkerMorningRoutine();
            }
            break;
          case 'admin':
            await userSession.performAdminPlaylistManagement();
            break;
          case 'supervisor':
            await userSession.performSupervisorMonitoring();
            break;
        }

        // Pause zwischen Aktionen
        await new Promise(resolve => setTimeout(resolve, Math.random() * 30000 + 10000));
      }

      await context.close();

    } catch (error) {
      this.metrics.addMetric('user_errors', 1);
      console.error(`❌ Benutzer ${userIndex} (${userType}) Fehler:`, error);
    }
  }

  private determineUserType(): string {
    const rand = Math.random() * 100;
    const dist = this.config.userDistribution;
    
    if (rand < dist.fieldWorkers) return 'fieldWorker';
    if (rand < dist.fieldWorkers + dist.admins) return 'admin';
    return 'supervisor';
  }

  private getUserData(userType: string): any {
    switch (userType) {
      case 'fieldWorker':
        return testUsers.fieldWorkers[Math.floor(Math.random() * testUsers.fieldWorkers.length)];
      case 'admin':
        return testUsers.admins[Math.floor(Math.random() * testUsers.admins.length)];
      case 'supervisor':
        return testUsers.supervisors[Math.floor(Math.random() * testUsers.supervisors.length)];
      default:
        return testUsers.fieldWorkers[0];
    }
  }

  private determineDeviceType(): string {
    const rand = Math.random() * 100;
    const devices = this.config.devices;
    
    if (rand < devices.desktop) return 'desktop';
    if (rand < devices.desktop + devices.mobile) return 'mobile';
    return 'tablet';
  }

  private determineBrowserType(): string {
    const rand = Math.random() * 100;
    const browsers = this.config.browsers;
    
    if (rand < browsers.chrome) return 'chromium';
    if (rand < browsers.chrome + browsers.firefox) return 'firefox';
    return 'webkit';
  }

  private printMetrics(): void {
    console.log('\n📊 Performance-Metriken:');
    console.log('========================');
    
    const allStats = this.metrics.getAllStats();
    
    for (const [metricName, stats] of Object.entries(allStats)) {
      if (stats) {
        console.log(`\n${metricName}:`);
        console.log(`  Min: ${stats.min}ms`);
        console.log(`  Avg: ${Math.round(stats.avg)}ms`);
        console.log(`  Max: ${stats.max}ms`);
        console.log(`  P95: ${stats.p95}ms`);
        console.log(`  P99: ${stats.p99}ms`);
        
        // SLA-Validierung
        this.validateSLA(metricName, stats);
      }
    }

    console.log('\nEntwickelt von 2Brands Media GmbH');
  }

  private validateSLA(metricName: string, stats: any): void {
    const slaTargets: { [key: string]: number } = {
      'login_duration': 100,
      'route_calculation_duration': 300,
      'stop_update_duration': 100,
      'dashboard_load_time': 200,
      'playlist_load_time': 150
    };

    const target = slaTargets[metricName];
    if (target && stats.p95 > target) {
      console.log(`  ⚠️  SLA-Verletzung: P95 ${stats.p95}ms > Ziel ${target}ms`);
    } else if (target) {
      console.log(`  ✅ SLA erfüllt: P95 ${stats.p95}ms <= Ziel ${target}ms`);
    }
  }
}

// Test-Definitionen für verschiedene Szenarien
for (const [scenarioName, config] of Object.entries(scenarioConfigs)) {
  test(`Load Test: ${scenarioName}`, async () => {
    const runner = new LoadTestRunner(config);
    await runner.run();
  });
}

// Export für programmatische Nutzung
export { LoadTestRunner, scenarioConfigs, PerformanceMetrics };