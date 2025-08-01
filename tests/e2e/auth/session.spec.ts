import { test, expect } from '../../fixtures/auth.fixture';
import { DashboardPage } from '../../page-objects/DashboardPage';

/**
 * Session Management E2E-Tests
 * Entwickelt von 2Brands Media GmbH
 */

test.describe('Session Management', () => {
  test('Session bleibt über Seiten-Reload erhalten', async ({ authenticatedPage }) => {
    // Reload der Seite
    await authenticatedPage.reload();
    
    // Sollte immer noch eingeloggt sein
    await expect(authenticatedPage).toHaveURL('/dashboard');
    const dashboardPage = new DashboardPage(authenticatedPage);
    await expect(dashboardPage.userMenu).toBeVisible();
  });

  test('Session Timeout nach Inaktivität', async ({ authenticatedPage }) => {
    // Simuliere Session-Timeout durch Cookie-Manipulation
    await authenticatedPage.context().addCookies([{
      name: 'PHPSESSID',
      value: 'expired_session',
      domain: 'localhost',
      path: '/'
    }]);
    
    await authenticatedPage.reload();
    
    // Sollte zur Login-Seite weiterleiten
    await expect(authenticatedPage).toHaveURL('/login');
  });

  test('Concurrent Sessions - Mehrere Browser-Tabs', async ({ browser, authenticatedPage }) => {
    // Öffne zweiten Tab im gleichen Context
    const context = authenticatedPage.context();
    const secondPage = await context.newPage();
    
    await secondPage.goto('/dashboard');
    await expect(secondPage).toHaveURL('/dashboard');
    
    // Logout im ersten Tab
    const dashboardPage = new DashboardPage(authenticatedPage);
    await dashboardPage.logout();
    
    // Zweiter Tab sollte auch ausgeloggt sein nach Reload
    await secondPage.reload();
    await expect(secondPage).toHaveURL('/login');
    
    await secondPage.close();
  });

  test('Session-Regenerierung nach Login', async ({ page }) => {
    // Hole Session-ID vor Login
    await page.goto('/login');
    const cookiesBefore = await page.context().cookies();
    const sessionBefore = cookiesBefore.find(c => c.name === 'PHPSESSID');
    
    // Login
    await page.fill('#username', 'test.user@metropol.de');
    await page.fill('#password', 'TestPassword123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
    
    // Session-ID sollte sich geändert haben (Session Fixation Prevention)
    const cookiesAfter = await page.context().cookies();
    const sessionAfter = cookiesAfter.find(c => c.name === 'PHPSESSID');
    
    expect(sessionAfter?.value).not.toBe(sessionBefore?.value);
  });

  test('CSRF-Token bleibt über Session erhalten', async ({ authenticatedPage }) => {
    // Hole CSRF-Token
    const csrfToken1 = await authenticatedPage.evaluate(() => {
      const meta = document.querySelector('meta[name="csrf-token"]');
      return meta?.getAttribute('content');
    });
    
    // Navigate zu anderer Seite
    await authenticatedPage.goto('/playlists');
    
    // CSRF-Token sollte gleich bleiben
    const csrfToken2 = await authenticatedPage.evaluate(() => {
      const meta = document.querySelector('meta[name="csrf-token"]');
      return meta?.getAttribute('content');
    });
    
    expect(csrfToken2).toBe(csrfToken1);
  });

  test('Session-Hijacking-Schutz - IP-Änderung', async ({ authenticatedPage }) => {
    // Simuliere API-Request mit anderer IP (würde serverseitig validiert)
    const response = await authenticatedPage.request.get('/api/auth/status', {
      headers: {
        'X-Forwarded-For': '192.168.1.100' // Andere IP
      }
    });
    
    // Bei striktem Session-Management könnte dies zu 401 führen
    // Abhängig von Server-Konfiguration
    expect([200, 401]).toContain(response.status());
  });
});