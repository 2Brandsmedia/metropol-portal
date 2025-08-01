import { test as base } from '@playwright/test';
import type { Page } from '@playwright/test';

/**
 * Auth Fixture für wiederverwendbare Authentifizierungs-Logik
 * Entwickelt von 2Brands Media GmbH
 */

type AuthFixtures = {
  authenticatedPage: Page;
  adminPage: Page;
};

export const test = base.extend<AuthFixtures>({
  authenticatedPage: async ({ page }, use) => {
    // Standard-Mitarbeiter Login
    await page.goto('/login');
    await page.fill('#username', 'test.user@metropol.de');
    await page.fill('#password', 'TestPassword123!');
    await page.click('button[type="submit"]');
    
    // Warte auf erfolgreiche Authentifizierung
    await page.waitForURL('/dashboard');
    await page.waitForSelector('[data-testid="user-menu"]');
    
    await use(page);
    
    // Cleanup: Logout
    await page.click('[data-testid="user-menu"]');
    await page.click('[data-testid="logout-button"]');
  },
  
  adminPage: async ({ page }, use) => {
    // Admin Login
    await page.goto('/login');
    await page.fill('#username', 'admin@metropol.de');
    await page.fill('#password', 'AdminPassword123!');
    await page.click('button[type="submit"]');
    
    // Warte auf Admin-Dashboard
    await page.waitForURL('/admin/dashboard');
    await page.waitForSelector('[data-testid="admin-menu"]');
    
    await use(page);
    
    // Cleanup: Logout
    await page.click('[data-testid="user-menu"]');
    await page.click('[data-testid="logout-button"]');
  },
});

export { expect } from '@playwright/test';