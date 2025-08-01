import { Page, Locator } from '@playwright/test';

/**
 * Basis Page Object für gemeinsame Funktionalitäten
 * Entwickelt von 2Brands Media GmbH
 */
export class BasePage {
  readonly page: Page;
  readonly header: Locator;
  readonly userMenu: Locator;
  readonly languageSelector: Locator;
  readonly notification: Locator;

  constructor(page: Page) {
    this.page = page;
    this.header = page.locator('header');
    this.userMenu = page.locator('[data-testid="user-menu"]');
    this.languageSelector = page.locator('[data-testid="language-selector"]');
    this.notification = page.locator('.notification');
  }

  async goto(path: string) {
    await this.page.goto(path);
  }

  async waitForPageLoad() {
    await this.page.waitForLoadState('networkidle');
  }

  async changeLanguage(lang: 'de' | 'en' | 'tr') {
    await this.languageSelector.click();
    await this.page.click(`[data-language="${lang}"]`);
    await this.page.waitForLoadState('networkidle');
  }

  async logout() {
    await this.userMenu.click();
    await this.page.click('[data-testid="logout-button"]');
    await this.page.waitForURL('/login');
  }

  async checkNotification(message: string) {
    await this.notification.waitFor({ state: 'visible' });
    const text = await this.notification.textContent();
    return text?.includes(message);
  }

  async closeNotification() {
    await this.notification.locator('.close').click();
    await this.notification.waitFor({ state: 'hidden' });
  }

  async measurePerformance() {
    const performanceTiming = await this.page.evaluate(() => 
      JSON.stringify(window.performance.timing)
    );
    return JSON.parse(performanceTiming);
  }
}