import { test, expect } from '../../fixtures/auth.fixture';
import { BasePage } from '../../page-objects/BasePage';
import { LoginPage } from '../../page-objects/LoginPage';
import { DashboardPage } from '../../page-objects/DashboardPage';

/**
 * Internationalisierung (i18n) E2E-Tests
 * Entwickelt von 2Brands Media GmbH
 */

test.describe('Multi-Language Support', () => {
  let basePage: BasePage;
  let loginPage: LoginPage;
  let dashboardPage: DashboardPage;

  test.beforeEach(async ({ page }) => {
    basePage = new BasePage(page);
    loginPage = new LoginPage(page);
    dashboardPage = new DashboardPage(page);
  });

  test('Sprachwechsel auf Login-Seite', async ({ page }) => {
    await loginPage.goto();
    
    // Standard sollte Deutsch sein
    await expect(loginPage.submitButton).toContainText('Anmelden');
    
    // Wechsel zu Englisch
    await basePage.changeLanguage('en');
    await expect(loginPage.submitButton).toContainText('Sign in');
    await expect(page.locator('label[for="username"]')).toContainText('Username');
    
    // Wechsel zu Türkisch
    await basePage.changeLanguage('tr');
    await expect(loginPage.submitButton).toContainText('Giriş yap');
    
    // Zurück zu Deutsch
    await basePage.changeLanguage('de');
    await expect(loginPage.submitButton).toContainText('Anmelden');
  });

  test('Sprache bleibt nach Login erhalten', async ({ page }) => {
    await loginPage.goto();
    
    // Wechsel zu Englisch vor Login
    await basePage.changeLanguage('en');
    
    // Login
    await loginPage.login('test.user@metropol.de', 'TestPassword123!');
    await page.waitForURL('/dashboard');
    
    // Sprache sollte immer noch Englisch sein
    await expect(dashboardPage.createPlaylistButton).toContainText('Create Playlist');
    
    // Cookie sollte gesetzt sein
    const cookies = await page.context().cookies();
    const langCookie = cookies.find(c => c.name === 'language');
    expect(langCookie?.value).toBe('en');
  });

  test('Alle UI-Elemente werden übersetzt', async ({ authenticatedPage }) => {
    await dashboardPage.goto();
    
    // Sammle alle Texte in Deutsch
    const textsDE = {
      createButton: await dashboardPage.createPlaylistButton.textContent(),
      userMenu: await dashboardPage.userMenu.textContent(),
      stats: await authenticatedPage.locator('[data-testid="stats-title"]').textContent()
    };
    
    // Wechsel zu Englisch
    await basePage.changeLanguage('en');
    
    const textsEN = {
      createButton: await dashboardPage.createPlaylistButton.textContent(),
      userMenu: await dashboardPage.userMenu.textContent(),
      stats: await authenticatedPage.locator('[data-testid="stats-title"]').textContent()
    };
    
    // Texte sollten sich unterscheiden
    expect(textsDE.createButton).not.toBe(textsEN.createButton);
    expect(textsDE.stats).not.toBe(textsEN.stats);
    
    // Keine unübersetzten Platzhalter
    Object.values(textsEN).forEach(text => {
      expect(text).not.toMatch(/^i18n\./);
      expect(text).not.toMatch(/\{\{.*\}\}/);
    });
  });

  test('Datum- und Zeitformate passen sich an Sprache an', async ({ authenticatedPage }) => {
    await dashboardPage.goto();
    
    // Deutsche Formatierung
    const dateDE = await authenticatedPage.locator('.playlist-date').first().textContent();
    expect(dateDE).toMatch(/\d{2}\.\d{2}\.\d{4}/); // DD.MM.YYYY
    
    // Wechsel zu Englisch
    await basePage.changeLanguage('en');
    
    const dateEN = await authenticatedPage.locator('.playlist-date').first().textContent();
    expect(dateEN).toMatch(/\d{2}\/\d{2}\/\d{4}/); // MM/DD/YYYY
  });

  test('Zahlenformate passen sich an Sprache an', async ({ authenticatedPage }) => {
    await dashboardPage.goto();
    
    // Deutsche Formatierung (Komma als Dezimaltrennzeichen)
    const stats = await dashboardPage.getStats();
    const distanceTextDE = await authenticatedPage.locator('[data-stat="total-distance"]').textContent();
    expect(distanceTextDE).toMatch(/\d+,\d+/); // z.B. "12,5 km"
    
    // Wechsel zu Englisch
    await basePage.changeLanguage('en');
    
    const distanceTextEN = await authenticatedPage.locator('[data-stat="total-distance"]').textContent();
    expect(distanceTextEN).toMatch(/\d+\.\d+/); // z.B. "12.5 km"
  });

  test('Fehlermeldungen in korrekter Sprache', async ({ page }) => {
    await loginPage.goto();
    
    // Deutsch
    await loginPage.login('wrong@email.de', 'wrongpass');
    let errorMessage = await loginPage.getErrorMessage();
    expect(errorMessage).toContain('Ungültige Zugangsdaten');
    
    // Englisch
    await basePage.changeLanguage('en');
    await loginPage.login('wrong@email.de', 'wrongpass');
    errorMessage = await loginPage.getErrorMessage();
    expect(errorMessage).toContain('Invalid credentials');
    
    // Türkisch
    await basePage.changeLanguage('tr');
    await loginPage.login('wrong@email.de', 'wrongpass');
    errorMessage = await loginPage.getErrorMessage();
    expect(errorMessage).toContain('Geçersiz kimlik bilgileri');
  });

  test('URL bleibt bei Sprachwechsel gleich', async ({ authenticatedPage }) => {
    await dashboardPage.goto();
    const urlBefore = authenticatedPage.url();
    
    await basePage.changeLanguage('en');
    const urlAfter = authenticatedPage.url();
    
    // URL sollte sich nicht ändern (keine Sprach-Präfixe)
    expect(urlAfter).toBe(urlBefore);
  });

  test('Dynamisch geladene Inhalte werden übersetzt', async ({ authenticatedPage }) => {
    await dashboardPage.goto();
    await basePage.changeLanguage('en');
    
    // Erstelle neue Playlist
    await dashboardPage.createNewPlaylist();
    
    // Formularlabels sollten auf Englisch sein
    await expect(authenticatedPage.locator('label[for="playlist-title"]')).toContainText('Playlist Title');
    await expect(authenticatedPage.locator('label[for="address"]').first()).toContainText('Address');
  });

  test('RTL-Support für zukünftige Sprachen', async ({ authenticatedPage }) => {
    // Test für potenzielle RTL-Sprachen (z.B. Arabisch)
    // Momentan nicht implementiert, aber Test-Struktur vorhanden
    
    await dashboardPage.goto();
    
    // Wenn RTL-Sprache verfügbar wäre:
    // await basePage.changeLanguage('ar');
    
    // const htmlDir = await authenticatedPage.locator('html').getAttribute('dir');
    // expect(htmlDir).toBe('rtl');
    
    // const bodyStyle = await authenticatedPage.locator('body').evaluate(el => 
    //   window.getComputedStyle(el).direction
    // );
    // expect(bodyStyle).toBe('rtl');
  });

  test('Sprach-Fallback bei fehlenden Übersetzungen', async ({ authenticatedPage }) => {
    await dashboardPage.goto();
    
    // Simuliere fehlende Übersetzung durch direkten API-Call
    const response = await authenticatedPage.request.post('/api/i18n/switch', {
      data: { language: 'en' }
    });
    expect(response.ok()).toBeTruthy();
    
    // Selbst bei fehlenden Übersetzungen sollte kein Platzhalter angezeigt werden
    const allTexts = await authenticatedPage.locator('body').textContent();
    expect(allTexts).not.toMatch(/translation\.missing/i);
    expect(allTexts).not.toMatch(/i18n\./);
  });

  test('Meta-Tags werden für SEO angepasst', async ({ page }) => {
    await loginPage.goto();
    
    // Deutsche Meta-Tags
    let description = await page.locator('meta[name="description"]').getAttribute('content');
    expect(description).toContain('Mitarbeiter');
    
    // Englische Meta-Tags
    await basePage.changeLanguage('en');
    description = await page.locator('meta[name="description"]').getAttribute('content');
    expect(description).toContain('Employee');
    
    // HTML lang-Attribut
    const htmlLang = await page.locator('html').getAttribute('lang');
    expect(htmlLang).toBe('en');
  });

  test('Barrierefreiheit: Screen-Reader-Texte übersetzt', async ({ authenticatedPage }) => {
    await dashboardPage.goto();
    
    // Deutsche aria-labels
    const menuAriaDE = await dashboardPage.userMenu.getAttribute('aria-label');
    expect(menuAriaDE).toContain('Benutzermenü');
    
    // Englische aria-labels
    await basePage.changeLanguage('en');
    const menuAriaEN = await dashboardPage.userMenu.getAttribute('aria-label');
    expect(menuAriaEN).toContain('User menu');
  });
});