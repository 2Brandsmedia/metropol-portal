import { Page, Locator } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Playlist Page Object für Routen-Management
 * Entwickelt von 2Brands Media GmbH
 */
export class PlaylistPage extends BasePage {
  readonly playlistTitle: Locator;
  readonly addStopButton: Locator;
  readonly stopsList: Locator;
  readonly calculateRouteButton: Locator;
  readonly saveButton: Locator;
  readonly deleteButton: Locator;
  readonly routeInfo: Locator;
  readonly trafficToggle: Locator;

  constructor(page: Page) {
    super(page);
    this.playlistTitle = page.locator('input[name="playlist-title"]');
    this.addStopButton = page.locator('[data-testid="add-stop-btn"]');
    this.stopsList = page.locator('[data-testid="stops-list"]');
    this.calculateRouteButton = page.locator('[data-testid="calculate-route-btn"]');
    this.saveButton = page.locator('[data-testid="save-playlist-btn"]');
    this.deleteButton = page.locator('[data-testid="delete-playlist-btn"]');
    this.routeInfo = page.locator('[data-testid="route-info"]');
    this.trafficToggle = page.locator('[data-testid="traffic-toggle"]');
  }

  async createPlaylist(title: string, stops: Array<{
    address: string;
    workTime: number;
    notes?: string;
  }>) {
    await this.playlistTitle.fill(title);

    for (const stop of stops) {
      await this.addStop(stop.address, stop.workTime, stop.notes);
    }

    await this.calculateRoute();
    await this.save();
  }

  async addStop(address: string, workTime: number, notes?: string) {
    await this.addStopButton.click();
    
    const newStop = this.stopsList.locator('.stop-item').last();
    await newStop.locator('input[name="address"]').fill(address);
    await newStop.locator('input[name="work-time"]').fill(workTime.toString());
    
    if (notes) {
      await newStop.locator('textarea[name="notes"]').fill(notes);
    }
    
    // Warte auf Geocoding
    await this.page.waitForResponse(resp => 
      resp.url().includes('/api/geocode') && resp.status() === 200
    );
  }

  async removeStop(index: number) {
    const stopItems = await this.stopsList.locator('.stop-item').all();
    if (stopItems[index]) {
      await stopItems[index].locator('[data-testid="remove-stop-btn"]').click();
    }
  }

  async reorderStop(fromIndex: number, toIndex: number) {
    const stopItems = await this.stopsList.locator('.stop-item').all();
    const dragHandle = stopItems[fromIndex].locator('[data-testid="drag-handle"]');
    const targetStop = stopItems[toIndex];
    
    await dragHandle.dragTo(targetStop);
  }

  async markStopComplete(index: number) {
    const stopItems = await this.stopsList.locator('.stop-item').all();
    await stopItems[index].locator('[data-testid="complete-stop-checkbox"]').check();
  }

  async calculateRoute() {
    await this.calculateRouteButton.click();
    
    // Warte auf Route-Berechnung
    await this.page.waitForResponse(resp => 
      resp.url().includes('/api/routes/calculate') && resp.status() === 200,
      { timeout: 10000 }
    );
    
    await this.routeInfo.waitFor({ state: 'visible' });
  }

  async toggleTraffic() {
    await this.trafficToggle.click();
    await this.page.waitForLoadState('networkidle');
  }

  async save() {
    await this.saveButton.click();
    await this.checkNotification('Playlist erfolgreich gespeichert');
  }

  async delete() {
    await this.deleteButton.click();
    
    // Bestätige im Dialog
    await this.page.click('[data-testid="confirm-delete-btn"]');
    await this.page.waitForURL('/dashboard');
  }

  async getRouteInfo() {
    const distance = await this.routeInfo.locator('[data-info="distance"]').textContent();
    const duration = await this.routeInfo.locator('[data-info="duration"]').textContent();
    const trafficDuration = await this.routeInfo.locator('[data-info="traffic-duration"]').textContent();
    
    return {
      distance: parseFloat(distance?.replace(/[^\d.]/g, '') || '0'),
      duration: parseInt(duration?.replace(/[^\d]/g, '') || '0'),
      trafficDuration: parseInt(trafficDuration?.replace(/[^\d]/g, '') || '0')
    };
  }

  async getStopCount(): Promise<number> {
    const stops = await this.stopsList.locator('.stop-item').all();
    return stops.length;
  }
}