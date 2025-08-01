import { Config } from 'lighthouse';
import { Options } from 'lighthouse/types/externs';

/**
 * Lighthouse-Konfiguration für Metropol Portal Performance-Tests
 * Entwickelt von 2Brands Media GmbH
 */

export const lighthouseConfig: Config = {
  extends: 'lighthouse:default',
  
  // Core Web Vitals Fokus
  categories: {
    performance: {
      title: 'Performance',
      supportedModes: ['navigation', 'timespan', 'snapshot'],
      auditRefs: [
        { id: 'first-contentful-paint', weight: 10 },
        { id: 'largest-contentful-paint', weight: 25 },
        { id: 'interactive', weight: 10 },
        { id: 'speed-index', weight: 10 },
        { id: 'cumulative-layout-shift', weight: 25 },
        { id: 'total-blocking-time', weight: 30 },
      ],
    },
    'web-vitals': {
      title: 'Core Web Vitals',
      supportedModes: ['navigation'],
      auditRefs: [
        { id: 'first-contentful-paint', weight: 33 },
        { id: 'largest-contentful-paint', weight: 33 },
        { id: 'cumulative-layout-shift', weight: 34 },
      ],
    },
  },
  
  // Audit-spezifische Konfiguration
  audits: [
    'first-contentful-paint',
    'largest-contentful-paint',
    'interactive',
    'speed-index',
    'cumulative-layout-shift',
    'total-blocking-time',
    'metrics',
    'network-requests',
    'network-server-latency',
    'main-thread-tasks',
    'diagnostics',
    'resource-summary',
    'third-party-summary',
    'bootup-time',
    'mainthread-work-breakdown',
    'dom-size',
    'critical-request-chains',
    'user-timings',
    'screenshot-thumbnails',
    'final-screenshot',
    'largest-contentful-paint-element',
    'layout-shift-elements',
    'long-tasks',
    'non-composited-animations',
    'unused-css-rules',
    'unused-javascript',
    'modern-image-formats',
    'uses-optimized-images',
    'uses-text-compression',
    'uses-responsive-images',
    'efficient-animated-content',
    'duplicated-javascript',
    'legacy-javascript',
    'preload-lcp-image',
    'total-byte-weight',
    'uses-long-cache-ttl',
    'uses-rel-preconnect',
    'font-display',
    'third-party-facades',
  ],
  
  // Schwellenwerte für Performance-Metriken (nach Metropol Portal Zielen)
  passes: [
    {
      passName: 'defaultPass',
      recordTrace: true,
      useThrottling: true,
      pauseAfterFcpMs: 1000,
      pauseAfterLoadMs: 1000,
      networkQuietThresholdMs: 1000,
      cpuQuietThresholdMs: 1000,
    },
  ],
  
  settings: {
    onlyAudits: [
      'first-contentful-paint',
      'largest-contentful-paint',
      'interactive',
      'speed-index',
      'cumulative-layout-shift',
      'total-blocking-time',
    ],
  },
};

export const lighthouseOptions: Options = {
  // Chrome Flags für realistische Bedingungen
  chromeFlags: [
    '--headless',
    '--disable-gpu',
    '--no-sandbox',
    '--disable-dev-shm-usage',
    '--disable-extensions',
    '--disable-background-timer-throttling',
    '--disable-backgrounding-occluded-windows',
    '--disable-renderer-backgrounding',
    '--disable-features=TranslateUI',
    '--disable-ipc-flooding-protection',
    '--enable-features=NetworkService,NetworkServiceLogging',
    '--force-color-profile=srgb',
    '--metrics-recording-only',
    '--use-mock-keychain',
  ],
  
  // Netzwerk- und CPU-Drosselung für realistische Bedingungen
  throttling: {
    rttMs: 40,        // Round Trip Time
    throughputKbps: 10 * 1024, // 10 Mbps down
    cpuSlowdownMultiplier: 1,   // Kein CPU-Throttling für Server-Tests
    requestLatencyMs: 0,
    downloadThroughputKbps: 10 * 1024,
    uploadThroughputKbps: 1 * 1024, // 1 Mbps up
  },
  
  // Mobile Simulation für Smartphone-Tests
  emulatedFormFactor: 'mobile',
  
  // Anzahl der Durchläufe für statistische Relevanz
  runs: 3,
  
  // Output-Konfiguration
  output: ['json', 'html'],
  outputPath: './tests/Performance/reports/',
  
  // Zeitlimits
  maxWaitForFcp: 15 * 1000,     // 15 Sekunden
  maxWaitForLoad: 35 * 1000,    // 35 Sekunden
  
  // Logging
  logLevel: 'info',
  
  // Lokalisierung
  locale: 'de-DE',
  
  // Zusätzliche Einstellungen für CI/CD
  port: 0, // Zufälliger Port
  hostname: '127.0.0.1',
};

// Performance-Budgets nach Metropol Portal Zielen
export const performanceBudgets = {
  // Core Web Vitals Ziele
  firstContentfulPaint: 1500,      // < 1.5s (gut)
  largestContentfulPaint: 2500,    // < 2.5s (gut)
  interactiveTime: 3000,           // < 3s (TTI Ziel)
  speedIndex: 2000,                // < 2s für gute UX
  cumulativeLayoutShift: 0.1,      // < 0.1 (gut)
  totalBlockingTime: 200,          // < 200ms (gut)
  
  // Zusätzliche Metriken
  totalByteWeight: 1600000,        // < 1.6 MB
  domSize: 1500,                   // < 1500 Elemente
  
  // Kritische Pfade (in ms)
  loginProcess: 100,               // < 100ms (Ziel aus CLAUDE.md)
  playlistCreation: 200,           // < 200ms für API-Response
  routeCalculation: 300,           // < 300ms (Ziel aus CLAUDE.md)
  
  // Lighthouse Score Ziele
  performanceScore: 95,            // > 95 (Ziel aus CLAUDE.md)
  accessibilityScore: 95,          // WCAG 2.1 AA
  bestPracticesScore: 95,
  seoScore: 90,
};

// Mobile-spezifische Konfiguration für Smartphone-Tests
export const mobileConfig: Partial<Options> = {
  emulatedFormFactor: 'mobile',
  throttling: {
    rttMs: 150,       // Langsamere mobile Verbindung
    throughputKbps: 1.6 * 1024, // 1.6 Mbps (Regular 3G)
    cpuSlowdownMultiplier: 4,    // Mobile CPU Simulation
    requestLatencyMs: 150,
    downloadThroughputKbps: 1.6 * 1024,
    uploadThroughputKbps: 750,
  },
};

// Desktop-Konfiguration für Admin-Benutzer
export const desktopConfig: Partial<Options> = {
  emulatedFormFactor: 'desktop',
  throttling: {
    rttMs: 40,
    throughputKbps: 10 * 1024,
    cpuSlowdownMultiplier: 1,
    requestLatencyMs: 0,
    downloadThroughputKbps: 10 * 1024,
    uploadThroughputKbps: 1 * 1024,
  },
};