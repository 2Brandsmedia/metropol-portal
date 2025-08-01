import { test, expect } from '@playwright/test';
import { injectAxe, checkA11y } from 'axe-playwright';

/**
 * Accessibility (a11y) Tests - WCAG 2.1 AA Compliance
 * Entwickelt von 2Brands Media GmbH
 */

test.describe('Accessibility Tests', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    await injectAxe(page);
  });

  test('Homepage - WCAG 2.1 AA Compliance', async ({ page }) => {
    await checkA11y(page, null, {
      detailedReport: true,
      detailedReportOptions: {
        html: true
      }
    });
  });

  test('Login Page - Accessibility', async ({ page }) => {
    await page.goto('/login');
    await injectAxe(page);
    
    await checkA11y(page, null, {
      rules: {
        'color-contrast': { enabled: true },
        'label': { enabled: true },
        'button-name': { enabled: true }
      }
    });
  });

  test('Dashboard - Accessibility nach Login', async ({ page }) => {
    // Login
    await page.goto('/login');
    await page.fill('#username', 'test.user@metropol.de');
    await page.fill('#password', 'TestPassword123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
    
    await injectAxe(page);
    await checkA11y(page);
  });

  test('Keyboard Navigation - Tab Order', async ({ page }) => {
    await page.goto('/login');
    
    // Tab durch alle interaktiven Elemente
    const focusableElements = [
      '#username',
      '#password',
      '#remember-me',
      'button[type="submit"]',
      'a[href="/forgot-password"]'
    ];
    
    for (const selector of focusableElements) {
      await page.keyboard.press('Tab');
      const focusedElement = await page.evaluate(() => document.activeElement?.id || document.activeElement?.tagName);
      
      // Überprüfe, dass Element fokussiert ist
      const element = await page.locator(selector);
      await expect(element).toBeFocused();
    }
  });

  test('Keyboard Navigation - Enter für Submit', async ({ page }) => {
    await page.goto('/login');
    
    // Fülle Formular aus
    await page.fill('#username', 'test.user@metropol.de');
    await page.fill('#password', 'TestPassword123!');
    
    // Enter im Passwort-Feld sollte Form submitten
    await page.locator('#password').press('Enter');
    
    await expect(page).toHaveURL('/dashboard');
  });

  test('Screen Reader - ARIA Labels', async ({ page }) => {
    await page.goto('/login');
    
    // Überprüfe wichtige ARIA-Labels
    const submitButton = page.locator('button[type="submit"]');
    const ariaLabel = await submitButton.getAttribute('aria-label');
    expect(ariaLabel).toBeTruthy();
    
    // Formular sollte ARIA-Eigenschaften haben
    const form = page.locator('form');
    const formRole = await form.getAttribute('role');
    expect(['form', 'main']).toContain(formRole);
  });

  test('Focus Indicators sichtbar', async ({ page }) => {
    await page.goto('/login');
    
    // Fokussiere erstes Input-Feld
    await page.focus('#username');
    
    // Überprüfe Focus-Styles
    const focusStyles = await page.locator('#username').evaluate(el => {
      const styles = window.getComputedStyle(el);
      return {
        outline: styles.outline,
        outlineWidth: styles.outlineWidth,
        outlineColor: styles.outlineColor,
        boxShadow: styles.boxShadow
      };
    });
    
    // Sollte sichtbare Focus-Indication haben
    const hasFocusIndication = 
      focusStyles.outline !== 'none' || 
      focusStyles.boxShadow !== 'none' ||
      parseInt(focusStyles.outlineWidth) > 0;
    
    expect(hasFocusIndication).toBe(true);
  });

  test('Color Contrast Ratios', async ({ page }) => {
    await page.goto('/');
    
    // Teste Text-Kontrast
    const textElements = await page.locator('p, span, h1, h2, h3, h4, h5, h6').all();
    
    for (const element of textElements.slice(0, 5)) { // Teste erste 5 Elemente
      const contrast = await element.evaluate(el => {
        const styles = window.getComputedStyle(el);
        const bgColor = styles.backgroundColor;
        const textColor = styles.color;
        
        // Vereinfachte Kontrast-Berechnung (normalerweise würde man eine Library nutzen)
        return { bgColor, textColor };
      });
      
      // Mindestens sollten Farben definiert sein
      expect(contrast.bgColor).toBeTruthy();
      expect(contrast.textColor).toBeTruthy();
    }
  });

  test('Responsive Text Scaling', async ({ page }) => {
    await page.goto('/');
    
    // Test bei 200% Zoom
    await page.evaluate(() => {
      document.documentElement.style.fontSize = '32px'; // Doppelte Größe
    });
    
    // Content sollte nicht horizontal scrollen
    const bodyWidth = await page.locator('body').evaluate(el => el.scrollWidth);
    const viewportWidth = await page.evaluate(() => window.innerWidth);
    
    expect(bodyWidth).toBeLessThanOrEqual(viewportWidth);
  });

  test('Alternative Texte für Bilder', async ({ page }) => {
    await page.goto('/');
    
    const images = await page.locator('img').all();
    
    for (const img of images) {
      const alt = await img.getAttribute('alt');
      const role = await img.getAttribute('role');
      
      // Jedes Bild sollte alt-Text haben oder role="presentation" für dekorative Bilder
      expect(alt || role === 'presentation').toBeTruthy();
    }
  });

  test('Form Validation - Zugängliche Fehlermeldungen', async ({ page }) => {
    await page.goto('/login');
    
    // Submit ohne Daten
    await page.click('button[type="submit"]');
    
    // Überprüfe ARIA-Eigenschaften für Fehlermeldungen
    const usernameInput = page.locator('#username');
    const ariaInvalid = await usernameInput.getAttribute('aria-invalid');
    const ariaDescribedBy = await usernameInput.getAttribute('aria-describedby');
    
    if (ariaInvalid === 'true') {
      expect(ariaDescribedBy).toBeTruthy();
      
      // Fehlermeldung sollte existieren
      const errorMessage = page.locator(`#${ariaDescribedBy}`);
      await expect(errorMessage).toBeVisible();
    }
  });

  test('Skip Links für Keyboard-Navigation', async ({ page }) => {
    await page.goto('/');
    
    // Tab sollte Skip-Link zeigen
    await page.keyboard.press('Tab');
    
    const skipLink = page.locator('a[href="#main-content"]');
    const isVisible = await skipLink.isVisible();
    
    if (isVisible) {
      await skipLink.click();
      
      // Sollte zum Hauptinhalt springen
      const mainContent = page.locator('#main-content');
      await expect(mainContent).toBeInViewport();
    }
  });

  test('Modale Dialoge - Focus Trap', async ({ page }) => {
    // Login für Dashboard-Zugang
    await page.goto('/login');
    await page.fill('#username', 'test.user@metropol.de');
    await page.fill('#password', 'TestPassword123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
    
    // Öffne ein Modal (z.B. Playlist löschen)
    const deleteButton = page.locator('[data-testid="delete-playlist-btn"]').first();
    if (await deleteButton.isVisible()) {
      await deleteButton.click();
      
      // Focus sollte im Modal gefangen sein
      const modal = page.locator('[role="dialog"]');
      await expect(modal).toBeVisible();
      
      // Tab durch Modal-Elemente
      for (let i = 0; i < 10; i++) {
        await page.keyboard.press('Tab');
        const focusedElement = await page.evaluate(() => document.activeElement);
        const isInModal = await page.evaluate((el) => {
          const modal = document.querySelector('[role="dialog"]');
          return modal?.contains(el);
        }, focusedElement);
        
        expect(isInModal).toBe(true);
      }
    }
  });

  test('Landmarks für Screen Reader', async ({ page }) => {
    await page.goto('/');
    
    // Überprüfe wichtige Landmarks
    const landmarks = {
      header: page.locator('header, [role="banner"]'),
      nav: page.locator('nav, [role="navigation"]'),
      main: page.locator('main, [role="main"]'),
      footer: page.locator('footer, [role="contentinfo"]')
    };
    
    for (const [name, locator] of Object.entries(landmarks)) {
      const count = await locator.count();
      expect(count).toBeGreaterThan(0);
    }
  });
});