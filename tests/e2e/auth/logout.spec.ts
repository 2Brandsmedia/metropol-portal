import { test, expect } from '../../fixtures/auth.fixture';
import { DashboardPage } from '../../page-objects/DashboardPage';

/**
 * Logout E2E-Tests
 * Entwickelt von 2Brands Media GmbH
 */

test.describe('Authentifizierung - Logout', () => {
  let dashboardPage: DashboardPage;

  test.beforeEach(async ({ authenticatedPage }) => {
    dashboardPage = new DashboardPage(authenticatedPage);
  });

  test('Erfolgreicher Logout', async ({ authenticatedPage }) => {
    await dashboardPage.logout();
    
    // Sollte zur Login-Seite weiterleiten
    await expect(authenticatedPage).toHaveURL('/login');
    
    // Dashboard sollte nicht mehr zugänglich sein
    await authenticatedPage.goto('/dashboard');
    await expect(authenticatedPage).toHaveURL('/login');
  });

  test('Session wird nach Logout ungültig', async ({ authenticatedPage }) => {
    // Speichere Session-Cookie
    const cookiesBefore = await authenticatedPage.context().cookies();
    const sessionBefore = cookiesBefore.find(c => c.name === 'PHPSESSID');
    
    await dashboardPage.logout();
    
    // Session-Cookie sollte gelöscht sein
    const cookiesAfter = await authenticatedPage.context().cookies();
    const sessionAfter = cookiesAfter.find(c => c.name === 'PHPSESSID');
    
    expect(sessionAfter).toBeUndefined();
  });

  test('Geschützte API-Endpunkte nach Logout nicht erreichbar', async ({ authenticatedPage }) => {
    await dashboardPage.logout();
    
    // Versuche API-Aufruf
    const response = await authenticatedPage.request.get('/api/playlists');
    expect(response.status()).toBe(401);
  });

  test('Remember Me Token wird bei Logout entfernt', async ({ page }) => {
    // Login mit Remember Me
    await page.goto('/login');
    await page.fill('#username', 'test.user@metropol.de');
    await page.fill('#password', 'TestPassword123!');
    await page.check('#remember-me');
    await page.click('button[type="submit"]');
    
    await page.waitForURL('/dashboard');
    
    // Logout
    const dashboard = new DashboardPage(page);
    await dashboard.logout();
    
    // Remember Token sollte entfernt sein
    const cookies = await page.context().cookies();
    const rememberToken = cookies.find(c => c.name === 'remember_token');
    expect(rememberToken).toBeUndefined();
  });

  test('Mehrfach-Logout führt nicht zu Fehlern', async ({ authenticatedPage }) => {
    await dashboardPage.logout();
    
    // Zweiter Logout-Versuch
    await authenticatedPage.goto('/api/auth/logout', { waitUntil: 'networkidle' });
    
    // Sollte immer noch bei Login sein, ohne Fehler
    await expect(authenticatedPage).toHaveURL('/login');
    await expect(authenticatedPage.locator('.alert-error')).not.toBeVisible();
  });
});