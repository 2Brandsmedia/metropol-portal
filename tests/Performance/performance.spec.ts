import { test, expect } from '@playwright/test';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { PlaylistPage } from '../page-objects/PlaylistPage';

/**
 * Performance & Core Web Vitals Tests
 * Entwickelt von 2Brands Media GmbH
 */

test.describe('Performance Tests', () => {
  test('Lighthouse Score > 95', async ({ page }) => {
    // Installiere Lighthouse
    const lighthouse = await import('lighthouse');
    const chromeLauncher = await import('chrome-launcher');
    
    const chrome = await chromeLauncher.launch({ chromeFlags: ['--headless'] });
    const options = {
      logLevel: 'info',
      output: 'json',
      port: chrome.port,
    };
    
    const runnerResult = await lighthouse.default('http://localhost:8080', options);
    await chrome.kill();
    
    const scores = {
      performance: runnerResult.lhr.categories.performance.score * 100,
      accessibility: runnerResult.lhr.categories.accessibility.score * 100,
      bestPractices: runnerResult.lhr.categories['best-practices'].score * 100,
      seo: runnerResult.lhr.categories.seo.score * 100,
    };
    
    // Performance-Ziel aus CLAUDE.md
    expect(scores.performance).toBeGreaterThanOrEqual(95);
    expect(scores.accessibility).toBeGreaterThanOrEqual(90);
    expect(scores.bestPractices).toBeGreaterThanOrEqual(90);
    expect(scores.seo).toBeGreaterThanOrEqual(90);
  });

  test('Core Web Vitals - FCP < 1.5s', async ({ page }) => {
    await page.goto('/');
    
    const metrics = await page.evaluate(() => {
      return new Promise((resolve) => {
        new PerformanceObserver((list) => {
          const entries = list.getEntries();
          const fcp = entries.find(entry => entry.name === 'first-contentful-paint');
          if (fcp) {
            resolve({ fcp: fcp.startTime });
          }
        }).observe({ entryTypes: ['paint'] });
      });
    });
    
    expect(metrics.fcp).toBeLessThan(1500); // 1.5 Sekunden
  });

  test('Core Web Vitals - LCP < 2.5s', async ({ page }) => {
    await page.goto('/');
    
    const lcp = await page.evaluate(() => {
      return new Promise((resolve) => {
        new PerformanceObserver((list) => {
          const entries = list.getEntries();
          const lastEntry = entries[entries.length - 1];
          resolve(lastEntry.startTime);
        }).observe({ entryTypes: ['largest-contentful-paint'] });
        
        // Timeout nach 5 Sekunden
        setTimeout(() => resolve(5000), 5000);
      });
    });
    
    expect(lcp).toBeLessThan(2500); // 2.5 Sekunden
  });

  test('Core Web Vitals - CLS < 0.1', async ({ page }) => {
    await page.goto('/');
    
    // Warte auf Seiten-Stabilität
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    const cls = await page.evaluate(() => {
      let clsValue = 0;
      new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          if (!entry.hadRecentInput) {
            clsValue += entry.value;
          }
        }
      }).observe({ entryTypes: ['layout-shift'] });
      
      return new Promise((resolve) => {
        setTimeout(() => resolve(clsValue), 3000);
      });
    });
    
    expect(cls).toBeLessThan(0.1);
  });

  test('Login Performance < 100ms', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();
    
    const loginTime = await loginPage.measureLoginPerformance(
      'test.user@metropol.de',
      'TestPassword123!'
    );
    
    expect(loginTime).toBeLessThan(100);
  });

  test('Dashboard Load Time < 1s', async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.fill('#username', 'test.user@metropol.de');
    await page.fill('#password', 'TestPassword123!');
    await page.click('button[type="submit"]');
    
    // Measure dashboard load
    const startTime = Date.now();
    await page.goto('/dashboard');
    await page.waitForLoadState('networkidle');
    const endTime = Date.now();
    
    expect(endTime - startTime).toBeLessThan(1000);
  });

  test('Route Calculation < 300ms', async ({ page }) => {
    const loginPage = new LoginPage(page);
    const dashboardPage = new DashboardPage(page);
    const playlistPage = new PlaylistPage(page);
    
    // Login und Navigation
    await loginPage.goto();
    await loginPage.login('test.user@metropol.de', 'TestPassword123!');
    await dashboardPage.createNewPlaylist();
    
    // Füge Stopps hinzu
    await playlistPage.playlistTitle.fill('Performance Test');
    await playlistPage.addStop('Alexanderplatz 1, 10178 Berlin', 30);
    await playlistPage.addStop('Potsdamer Platz 1, 10785 Berlin', 45);
    await playlistPage.addStop('Brandenburger Tor, 10117 Berlin', 20);
    
    // Messe Route-Berechnung
    const startTime = Date.now();
    await playlistPage.calculateRoute();
    const endTime = Date.now();
    
    expect(endTime - startTime).toBeLessThan(300);
  });

  test('Memory Leaks Detection', async ({ page }) => {
    await page.goto('/login');
    
    // Initial Memory Snapshot
    const initialMemory = await page.evaluate(() => {
      if (performance.memory) {
        return performance.memory.usedJSHeapSize;
      }
      return 0;
    });
    
    // Perform multiple operations
    for (let i = 0; i < 5; i++) {
      await page.goto('/');
      await page.goto('/login');
      await page.fill('#username', `test${i}@test.de`);
      await page.fill('#password', 'test');
    }
    
    // Force Garbage Collection
    await page.evaluate(() => {
      if (window.gc) {
        window.gc();
      }
    });
    
    await page.waitForTimeout(1000);
    
    // Final Memory Snapshot
    const finalMemory = await page.evaluate(() => {
      if (performance.memory) {
        return performance.memory.usedJSHeapSize;
      }
      return 0;
    });
    
    // Memory sollte nicht mehr als 50% ansteigen
    const memoryIncrease = (finalMemory - initialMemory) / initialMemory;
    expect(memoryIncrease).toBeLessThan(0.5);
  });

  test('Bundle Size Check', async ({ page }) => {
    const response = await page.goto('/');
    
    // Sammle alle geladenen Ressourcen
    const resources = await page.evaluate(() => 
      performance.getEntriesByType('resource').map(r => ({
        name: r.name,
        size: r.transferSize,
        type: r.initiatorType
      }))
    );
    
    // JavaScript Bundle Size
    const jsSize = resources
      .filter(r => r.type === 'script')
      .reduce((sum, r) => sum + r.size, 0);
    
    // CSS Bundle Size
    const cssSize = resources
      .filter(r => r.type === 'link' || r.name.endsWith('.css'))
      .reduce((sum, r) => sum + r.size, 0);
    
    // Bundle Sizes sollten optimiert sein
    expect(jsSize).toBeLessThan(500 * 1024); // 500KB für JS
    expect(cssSize).toBeLessThan(100 * 1024); // 100KB für CSS
  });

  test('Time to Interactive (TTI) < 3s', async ({ page }) => {
    await page.goto('/');
    
    const tti = await page.evaluate(() => {
      return new Promise((resolve) => {
        if ('PerformanceObserver' in window) {
          const observer = new PerformanceObserver((list) => {
            const perfEntries = list.getEntries();
            const navEntry = perfEntries.find(entry => entry.name === document.location.href);
            if (navEntry) {
              resolve(navEntry.loadEventEnd);
            }
          });
          observer.observe({ entryTypes: ['navigation'] });
        } else {
          // Fallback
          window.addEventListener('load', () => {
            resolve(performance.timing.loadEventEnd - performance.timing.navigationStart);
          });
        }
      });
    });
    
    expect(tti).toBeLessThan(3000); // 3 Sekunden
  });

  test('API Response Times', async ({ page, request }) => {
    // Login für authentifizierte Requests
    await page.goto('/login');
    await page.fill('#username', 'test.user@metropol.de');
    await page.fill('#password', 'TestPassword123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
    
    // Teste verschiedene API-Endpunkte
    const endpoints = [
      '/api/auth/status',
      '/api/playlists',
      '/api/i18n/translations'
    ];
    
    for (const endpoint of endpoints) {
      const startTime = Date.now();
      const response = await request.get(endpoint);
      const endTime = Date.now();
      
      expect(response.status()).toBe(200);
      expect(endTime - startTime).toBeLessThan(200); // 200ms Ziel
    }
  });
});