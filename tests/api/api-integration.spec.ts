import { test, expect } from '@playwright/test';

/**
 * API Integration Tests
 * Entwickelt von 2Brands Media GmbH
 */

test.describe('API Integration Tests', () => {
  let authToken: string;

  test.beforeAll(async ({ request }) => {
    // Login und hole Auth-Token
    const loginResponse = await request.post('/api/auth/login', {
      data: {
        username: 'test.user@metropol.de',
        password: 'TestPassword123!'
      }
    });
    
    expect(loginResponse.ok()).toBeTruthy();
    const cookies = await loginResponse.headers();
    // Extrahiere Session-Cookie oder Token
    authToken = cookies['set-cookie'] || '';
  });

  test.describe('Authentication API', () => {
    test('POST /api/auth/login - Erfolgreicher Login', async ({ request }) => {
      const response = await request.post('/api/auth/login', {
        data: {
          username: 'test.user@metropol.de',
          password: 'TestPassword123!'
        }
      });

      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data).toHaveProperty('user');
      expect(data.user).toHaveProperty('email', 'test.user@metropol.de');
      expect(data.user).toHaveProperty('role');
    });

    test('POST /api/auth/login - Ungültige Credentials', async ({ request }) => {
      const response = await request.post('/api/auth/login', {
        data: {
          username: 'invalid@test.de',
          password: 'wrongpassword'
        }
      });

      expect(response.status()).toBe(401);
      const data = await response.json();
      expect(data).toHaveProperty('error');
    });

    test('GET /api/auth/status - Auth Status prüfen', async ({ request }) => {
      const response = await request.get('/api/auth/status', {
        headers: {
          'Cookie': authToken
        }
      });

      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data).toHaveProperty('authenticated', true);
      expect(data).toHaveProperty('user');
    });

    test('POST /api/auth/logout - Logout', async ({ request }) => {
      const response = await request.post('/api/auth/logout', {
        headers: {
          'Cookie': authToken
        }
      });

      expect(response.status()).toBe(200);
      
      // Verify logout
      const statusResponse = await request.get('/api/auth/status', {
        headers: {
          'Cookie': authToken
        }
      });
      expect(statusResponse.status()).toBe(401);
    });
  });

  test.describe('Playlist API', () => {
    test('GET /api/playlists - Liste aller Playlists', async ({ request }) => {
      const response = await request.get('/api/playlists', {
        headers: {
          'Cookie': authToken
        }
      });

      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(Array.isArray(data.playlists)).toBe(true);
      
      if (data.playlists.length > 0) {
        expect(data.playlists[0]).toHaveProperty('id');
        expect(data.playlists[0]).toHaveProperty('title');
        expect(data.playlists[0]).toHaveProperty('stops');
      }
    });

    test('POST /api/playlists - Neue Playlist erstellen', async ({ request }) => {
      const newPlaylist = {
        title: 'API Test Playlist',
        stops: [
          {
            address: 'Alexanderplatz 1, 10178 Berlin',
            workTime: 30,
            notes: 'API Test Notiz'
          },
          {
            address: 'Potsdamer Platz 1, 10785 Berlin',
            workTime: 45
          }
        ]
      };

      const response = await request.post('/api/playlists', {
        headers: {
          'Cookie': authToken
        },
        data: newPlaylist
      });

      expect(response.status()).toBe(201);
      const data = await response.json();
      expect(data).toHaveProperty('id');
      expect(data).toHaveProperty('title', 'API Test Playlist');
      expect(data.stops).toHaveLength(2);
    });

    test('PUT /api/playlists/:id - Playlist aktualisieren', async ({ request }) => {
      // Erstelle erst eine Playlist
      const createResponse = await request.post('/api/playlists', {
        headers: {
          'Cookie': authToken
        },
        data: {
          title: 'Update Test',
          stops: [{
            address: 'Teststraße 1, Berlin',
            workTime: 20
          }]
        }
      });

      const { id } = await createResponse.json();

      // Update
      const updateResponse = await request.put(`/api/playlists/${id}`, {
        headers: {
          'Cookie': authToken
        },
        data: {
          title: 'Updated Title',
          stops: [{
            address: 'Teststraße 1, Berlin',
            workTime: 25,
            completed: true
          }]
        }
      });

      expect(updateResponse.status()).toBe(200);
      const data = await updateResponse.json();
      expect(data.title).toBe('Updated Title');
      expect(data.stops[0].workTime).toBe(25);
      expect(data.stops[0].completed).toBe(true);
    });

    test('DELETE /api/playlists/:id - Playlist löschen', async ({ request }) => {
      // Erstelle erst eine Playlist
      const createResponse = await request.post('/api/playlists', {
        headers: {
          'Cookie': authToken
        },
        data: {
          title: 'Delete Test',
          stops: [{
            address: 'Teststraße 1, Berlin',
            workTime: 20
          }]
        }
      });

      const { id } = await createResponse.json();

      // Lösche
      const deleteResponse = await request.delete(`/api/playlists/${id}`, {
        headers: {
          'Cookie': authToken
        }
      });

      expect(deleteResponse.status()).toBe(204);

      // Verify deletion
      const getResponse = await request.get(`/api/playlists/${id}`, {
        headers: {
          'Cookie': authToken
        }
      });
      expect(getResponse.status()).toBe(404);
    });
  });

  test.describe('Route Calculation API', () => {
    test('POST /api/routes/calculate - Route berechnen', async ({ request }) => {
      const routeRequest = {
        stops: [
          { lat: 52.521918, lng: 13.413215, address: 'Alexanderplatz' },
          { lat: 52.509935, lng: 13.376199, address: 'Potsdamer Platz' },
          { lat: 52.516275, lng: 13.377704, address: 'Brandenburger Tor' }
        ],
        includeTraffic: false
      };

      const response = await request.post('/api/routes/calculate', {
        headers: {
          'Cookie': authToken
        },
        data: routeRequest
      });

      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data).toHaveProperty('route');
      expect(data).toHaveProperty('totalDistance');
      expect(data).toHaveProperty('totalDuration');
      expect(data.route).toHaveProperty('coordinates');
      
      // Performance Check
      const responseTime = response.headers()['x-response-time'];
      if (responseTime) {
        expect(parseInt(responseTime)).toBeLessThan(300); // 300ms Ziel
      }
    });

    test('GET /api/routes/traffic - Traffic-Daten abrufen', async ({ request }) => {
      const response = await request.get('/api/routes/traffic', {
        headers: {
          'Cookie': authToken
        },
        params: {
          lat1: 52.521918,
          lng1: 13.413215,
          lat2: 52.509935,
          lng2: 13.376199
        }
      });

      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data).toHaveProperty('trafficLevel');
      expect(data).toHaveProperty('estimatedDelay');
    });
  });

  test.describe('Geocoding API', () => {
    test('GET /api/geocode - Adresse zu Koordinaten', async ({ request }) => {
      const response = await request.get('/api/geocode', {
        headers: {
          'Cookie': authToken
        },
        params: {
          address: 'Alexanderplatz 1, 10178 Berlin'
        }
      });

      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data).toHaveProperty('lat');
      expect(data).toHaveProperty('lng');
      expect(data.lat).toBeCloseTo(52.521918, 2);
      expect(data.lng).toBeCloseTo(13.413215, 2);
    });

    test('GET /api/geocode - Cache-Hit', async ({ request }) => {
      const address = 'Potsdamer Platz 1, 10785 Berlin';
      
      // Erster Request
      const response1 = await request.get('/api/geocode', {
        headers: {
          'Cookie': authToken
        },
        params: { address }
      });

      // Zweiter Request (sollte aus Cache kommen)
      const response2 = await request.get('/api/geocode', {
        headers: {
          'Cookie': authToken
        },
        params: { address }
      });

      expect(response2.status()).toBe(200);
      
      // Cache-Header prüfen
      const cacheHeader = response2.headers()['x-cache'];
      if (cacheHeader) {
        expect(cacheHeader).toBe('HIT');
      }
    });
  });

  test.describe('I18n API', () => {
    test('POST /api/i18n/switch - Sprache wechseln', async ({ request }) => {
      const response = await request.post('/api/i18n/switch', {
        headers: {
          'Cookie': authToken
        },
        data: {
          language: 'en'
        }
      });

      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data).toHaveProperty('language', 'en');
      
      // Cookie sollte gesetzt sein
      const cookies = response.headers()['set-cookie'];
      expect(cookies).toContain('language=en');
    });

    test('GET /api/i18n/translations - Übersetzungen laden', async ({ request }) => {
      const response = await request.get('/api/i18n/translations', {
        headers: {
          'Cookie': authToken + '; language=de'
        }
      });

      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data).toHaveProperty('language', 'de');
      expect(data).toHaveProperty('translations');
      expect(Object.keys(data.translations).length).toBeGreaterThan(0);
    });
  });

  test.describe('Error Handling', () => {
    test('404 - Nicht existierende Route', async ({ request }) => {
      const response = await request.get('/api/non-existent-endpoint', {
        headers: {
          'Cookie': authToken
        }
      });

      expect(response.status()).toBe(404);
      const data = await response.json();
      expect(data).toHaveProperty('error');
    });

    test('400 - Ungültige Request-Daten', async ({ request }) => {
      const response = await request.post('/api/playlists', {
        headers: {
          'Cookie': authToken
        },
        data: {
          // title fehlt
          stops: []
        }
      });

      expect(response.status()).toBe(400);
      const data = await response.json();
      expect(data).toHaveProperty('error');
      expect(data.error).toContain('title');
    });

    test('401 - Unautorisierter Zugriff', async ({ request }) => {
      const response = await request.get('/api/playlists');
      // Ohne Auth-Cookie

      expect(response.status()).toBe(401);
      const data = await response.json();
      expect(data).toHaveProperty('error');
    });
  });

  test.describe('Rate Limiting', () => {
    test('API Rate Limits eingehalten', async ({ request }) => {
      const requests = [];
      
      // 10 schnelle Requests
      for (let i = 0; i < 10; i++) {
        requests.push(
          request.get('/api/auth/status', {
            headers: {
              'Cookie': authToken
            }
          })
        );
      }

      const responses = await Promise.all(requests);
      const statusCodes = responses.map(r => r.status());
      
      // Mindestens die meisten sollten erfolgreich sein
      const successCount = statusCodes.filter(code => code === 200).length;
      expect(successCount).toBeGreaterThan(5);
      
      // Check for rate limit headers
      const lastResponse = responses[responses.length - 1];
      const rateLimitHeader = lastResponse.headers()['x-ratelimit-remaining'];
      if (rateLimitHeader) {
        expect(parseInt(rateLimitHeader)).toBeGreaterThanOrEqual(0);
      }
    });
  });
});