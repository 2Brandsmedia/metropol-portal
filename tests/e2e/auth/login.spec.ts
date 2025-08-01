import { test, expect } from '@playwright/test';
import { LoginPage } from '../../page-objects/LoginPage';
import { DashboardPage } from '../../page-objects/DashboardPage';

/**
 * Login E2E-Tests
 * Entwickelt von 2Brands Media GmbH
 */

test.describe('Authentifizierung - Login', () => {
  let loginPage: LoginPage;
  let dashboardPage: DashboardPage;

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page);
    dashboardPage = new DashboardPage(page);
    await loginPage.goto();
  });

  test('Erfolgreicher Login als Mitarbeiter', async ({ page }) => {
    await loginPage.login('test.user@metropol.de', 'TestPassword123!');
    
    // Überprüfe erfolgreiche Weiterleitung
    await expect(page).toHaveURL('/dashboard');
    
    // Überprüfe, dass Dashboard-Elemente sichtbar sind
    await expect(dashboardPage.createPlaylistButton).toBeVisible();
    await expect(dashboardPage.userMenu).toBeVisible();
  });

  test('Erfolgreicher Login als Admin', async ({ page }) => {
    await loginPage.login('admin@metropol.de', 'AdminPassword123!');
    
    // Admin wird zu Admin-Dashboard weitergeleitet
    await expect(page).toHaveURL('/admin/dashboard');
    await expect(page.locator('[data-testid="admin-menu"]')).toBeVisible();
  });

  test('Login mit falschen Zugangsdaten', async ({ page }) => {
    await loginPage.login('wrong@email.de', 'wrongpassword');
    
    // Sollte auf Login-Seite bleiben
    await expect(page).toHaveURL('/login');
    
    // Fehlermeldung sollte angezeigt werden
    const errorMessage = await loginPage.getErrorMessage();
    expect(errorMessage).toContain('Ungültige Zugangsdaten');
  });

  test('Login mit Remember Me Option', async ({ page }) => {
    await loginPage.usernameInput.fill('test.user@metropol.de');
    await loginPage.passwordInput.fill('TestPassword123!');
    await loginPage.rememberMeCheckbox.check();
    await loginPage.submitButton.click();
    
    await expect(page).toHaveURL('/dashboard');
    
    // Überprüfe Cookie
    const cookies = await page.context().cookies();
    const rememberCookie = cookies.find(c => c.name === 'remember_token');
    expect(rememberCookie).toBeDefined();
    expect(rememberCookie?.expires).toBeGreaterThan(Date.now() / 1000 + 86400); // Mindestens 1 Tag
  });

  test('Login Performance - unter 100ms Ziel', async ({ page }) => {
    const loginTime = await loginPage.measureLoginPerformance(
      'test.user@metropol.de',
      'TestPassword123!'
    );
    
    // Performance-Ziel aus CLAUDE.md: Login < 100ms
    expect(loginTime).toBeLessThan(100);
  });

  test('CSRF-Token Validierung', async ({ page }) => {
    // Entferne CSRF-Token
    await page.evaluate(() => {
      const csrfInput = document.querySelector('input[name="csrf_token"]');
      if (csrfInput) csrfInput.remove();
    });
    
    await loginPage.login('test.user@metropol.de', 'TestPassword123!');
    
    // Sollte Fehler wegen fehlendem CSRF-Token zeigen
    const errorMessage = await loginPage.getErrorMessage();
    expect(errorMessage).toContain('Sicherheitsfehler');
  });

  test('Passwort-Feld ist maskiert', async () => {
    const passwordFieldType = await loginPage.passwordInput.getAttribute('type');
    expect(passwordFieldType).toBe('password');
  });

  test('Forgot Password Link funktioniert', async ({ page }) => {
    await loginPage.forgotPasswordLink.click();
    await expect(page).toHaveURL('/forgot-password');
  });

  test('Validierung leerer Felder', async () => {
    await loginPage.submitButton.click();
    
    // HTML5 Validierung sollte greifen
    const usernameValidity = await loginPage.usernameInput.evaluate((el: HTMLInputElement) => 
      el.validity.valid
    );
    const passwordValidity = await loginPage.passwordInput.evaluate((el: HTMLInputElement) => 
      el.validity.valid
    );
    
    expect(usernameValidity).toBe(false);
    expect(passwordValidity).toBe(false);
  });

  test('XSS-Schutz in Login-Feldern', async ({ page }) => {
    const xssPayload = '<script>alert("XSS")</script>';
    
    await loginPage.login(xssPayload, xssPayload);
    
    // Kein Alert sollte erscheinen
    const dialogPromise = page.waitForEvent('dialog', { timeout: 1000 })
      .then(() => true)
      .catch(() => false);
    
    const hasDialog = await dialogPromise;
    expect(hasDialog).toBe(false);
  });
});