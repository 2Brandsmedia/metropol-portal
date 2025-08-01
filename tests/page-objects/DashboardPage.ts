import { Page, Locator } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Dashboard Page Object
 * Entwickelt von 2Brands Media GmbH
 */
export class DashboardPage extends BasePage {
  readonly createPlaylistButton: Locator;
  readonly playlistGrid: Locator;
  readonly statsWidget: Locator;
  readonly mapContainer: Locator;
  readonly refreshButton: Locator;

  constructor(page: Page) {
    super(page);
    this.createPlaylistButton = page.locator('[data-testid="create-playlist-btn"]');
    this.playlistGrid = page.locator('[data-testid="playlist-grid"]');
    this.statsWidget = page.locator('[data-testid="stats-widget"]');
    this.mapContainer = page.locator('#map-container');
    this.refreshButton = page.locator('[data-testid="refresh-btn"]');
  }

  async goto() {
    await super.goto('/dashboard');
  }

  async createNewPlaylist() {
    await this.createPlaylistButton.click();
    await this.page.waitForURL('/playlists/new');
  }

  async getPlaylistCount(): Promise<number> {
    const playlists = await this.playlistGrid.locator('.playlist-card').all();
    return playlists.length;
  }

  async openPlaylist(playlistId: string) {
    await this.page.click(`[data-playlist-id="${playlistId}"]`);
    await this.page.waitForURL(`/playlists/${playlistId}`);
  }

  async getStats() {
    const totalStops = await this.statsWidget.locator('[data-stat="total-stops"]').textContent();
    const completedStops = await this.statsWidget.locator('[data-stat="completed-stops"]').textContent();
    const totalDistance = await this.statsWidget.locator('[data-stat="total-distance"]').textContent();
    
    return {
      totalStops: parseInt(totalStops || '0'),
      completedStops: parseInt(completedStops || '0'),
      totalDistance: parseFloat(totalDistance || '0')
    };
  }

  async refreshData() {
    await this.refreshButton.click();
    await this.page.waitForLoadState('networkidle');
  }

  async waitForMapLoad() {
    await this.mapContainer.waitFor({ state: 'visible' });
    // Warte auf Leaflet-Initialisierung
    await this.page.waitForFunction(() => {
      return window.L && window.L.map && document.querySelector('.leaflet-container');
    });
  }
}