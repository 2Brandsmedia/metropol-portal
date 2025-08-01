/**
 * Load-Testing-Konfiguration für Metropol Portal
 * Realistische Benutzerszenarien und Lastverteilung
 * Entwickelt von 2Brands Media GmbH
 */

export interface LoadTestConfig {
  name: string;
  description: string;
  duration: string;
  users: {
    start: number;
    max: number;
    rampUp: string;
    rampDown: string;
  };
  scenarios: LoadTestScenario[];
  thresholds: {
    [key: string]: string[];
  };
}

export interface LoadTestScenario {
  name: string;
  weight: number;
  executor: string;
  options: {
    vus?: number;
    duration?: string;
    stages?: Array<{
      duration: string;
      target: number;
    }>;
  };
  env?: {
    [key: string]: string;
  };
}

// Basis-Konfiguration für verschiedene Test-Modi
export const loadTestConfigs: { [key: string]: LoadTestConfig } = {
  // Smoke Test - Minimale Last für Funktionalitätsprüfung
  smoke: {
    name: 'Smoke Test',
    description: 'Grundfunktionalität bei minimaler Last prüfen',
    duration: '1m',
    users: {
      start: 1,
      max: 5,
      rampUp: '30s',
      rampDown: '30s',
    },
    scenarios: [
      {
        name: 'login_scenario',
        weight: 30,
        executor: 'ramping-vus',
        options: {
          stages: [
            { duration: '30s', target: 2 },
            { duration: '30s', target: 0 },
          ],
        },
      },
      {
        name: 'playlist_browsing',
        weight: 40,
        executor: 'ramping-vus',
        options: {
          stages: [
            { duration: '30s', target: 3 },
            { duration: '30s', target: 0 },
          ],
        },
      },
      {
        name: 'route_calculation',
        weight: 30,
        executor: 'ramping-vus',
        options: {
          stages: [
            { duration: '30s', target: 2 },
            { duration: '30s', target: 0 },
          ],
        },
      },
    ],
    thresholds: {
      http_req_duration: ['p(95)<1000'], // 95% unter 1s
      http_req_failed: ['rate<0.1'],     // Fehlerrate unter 10%
    },
  },

  // Load Test - Normale Betriebslast
  load: {
    name: 'Load Test',
    description: 'Normale Betriebslast simulieren (10-20 gleichzeitige Benutzer)',
    duration: '10m',
    users: {
      start: 5,
      max: 20,
      rampUp: '2m',
      rampDown: '1m',
    },
    scenarios: [
      {
        name: 'morning_rush',
        weight: 25,
        executor: 'ramping-vus',
        options: {
          stages: [
            { duration: '2m', target: 15 },  // Anstieg
            { duration: '5m', target: 15 },  // Plateauphase
            { duration: '2m', target: 10 },  // Abfall
            { duration: '1m', target: 0 },   // Ende
          ],
        },
      },
      {
        name: 'continuous_work',
        weight: 50,
        executor: 'ramping-vus',
        options: {
          stages: [
            { duration: '1m', target: 8 },
            { duration: '8m', target: 12 },
            { duration: '1m', target: 0 },
          ],
        },
      },
      {
        name: 'route_optimization',
        weight: 25,
        executor: 'ramping-vus',
        options: {
          stages: [
            { duration: '2m', target: 5 },
            { duration: '6m', target: 8 },
            { duration: '2m', target: 0 },
          ],
        },
      },
    ],
    thresholds: {
      http_req_duration: ['p(95)<500', 'p(99)<1000'], // 95% unter 500ms, 99% unter 1s
      http_req_failed: ['rate<0.05'],                  // Fehlerrate unter 5%
      'http_req_duration{name:login}': ['p(95)<100'],  // Login unter 100ms (Ziel)
      'http_req_duration{name:route}': ['p(95)<300'],  // Route unter 300ms (Ziel)
    },
  },

  // Stress Test - Überlasten des Systems
  stress: {
    name: 'Stress Test',
    description: 'System-Grenzen ermitteln (bis 50 Benutzer)',
    duration: '15m',
    users: {
      start: 10,
      max: 50,
      rampUp: '5m',
      rampDown: '2m',
    },
    scenarios: [
      {
        name: 'heavy_load',
        weight: 100,
        executor: 'ramping-vus',
        options: {
          stages: [
            { duration: '2m', target: 20 },  // Normale Last
            { duration: '3m', target: 35 },  // Erhöhte Last
            { duration: '5m', target: 50 },  // Maximale Last
            { duration: '3m', target: 20 },  // Rückgang
            { duration: '2m', target: 0 },   // Ende
          ],
        },
      },
    ],
    thresholds: {
      http_req_duration: ['p(95)<2000'], // 95% unter 2s (degradiert)
      http_req_failed: ['rate<0.15'],    // Fehlerrate unter 15%
    },
  },

  // Spike Test - Plötzliche Lastspitzen
  spike: {
    name: 'Spike Test',
    description: 'Plötzliche Lastspitzen simulieren',
    duration: '8m',
    users: {
      start: 5,
      max: 40,
      rampUp: '30s',
      rampDown: '30s',
    },
    scenarios: [
      {
        name: 'traffic_spike',
        weight: 100,
        executor: 'ramping-vus',
        options: {
          stages: [
            { duration: '2m', target: 5 },   // Basis-Last
            { duration: '30s', target: 40 }, // Plötzlicher Anstieg
            { duration: '2m', target: 40 },  // Spitze halten
            { duration: '30s', target: 5 },  // Schneller Abfall
            { duration: '2m', target: 5 },   // Basis-Last
            { duration: '1m', target: 0 },   // Ende
          ],
        },
      },
    ],
    thresholds: {
      http_req_duration: ['p(95)<1500'], // 95% unter 1.5s
      http_req_failed: ['rate<0.1'],     // Fehlerrate unter 10%
    },
  },

  // Volume Test - Große Datenmengen
  volume: {
    name: 'Volume Test',
    description: 'Große Datenmengen verarbeiten (20 Stopps pro Playlist)',
    duration: '12m',
    users: {
      start: 3,
      max: 15,
      rampUp: '2m',
      rampDown: '1m',
    },
    scenarios: [
      {
        name: 'large_playlists',
        weight: 60,
        executor: 'ramping-vus',
        options: {
          stages: [
            { duration: '2m', target: 10 },
            { duration: '8m', target: 15 },
            { duration: '2m', target: 0 },
          ],
        },
        env: {
          STOPS_PER_PLAYLIST: '20',
          COMPLEX_ROUTES: 'true',
        },
      },
      {
        name: 'route_optimization_heavy',
        weight: 40,
        executor: 'ramping-vus',
        options: {
          stages: [
            { duration: '2m', target: 5 },
            { duration: '8m', target: 8 },
            { duration: '2m', target: 0 },
          ],
        },
        env: {
          STOPS_PER_PLAYLIST: '20',
          TRAFFIC_DATA: 'true',
        },
      },
    ],
    thresholds: {
      http_req_duration: ['p(95)<800'], // 95% unter 800ms
      http_req_failed: ['rate<0.05'],   // Fehlerrate unter 5%
      'http_req_duration{name:route_optimization}': ['p(95)<300'], // Route-Opt. unter 300ms
    },
  },

  // Endurance Test - Langzeittest
  endurance: {
    name: 'Endurance Test',
    description: 'Langzeit-Stabilität prüfen (1 Stunde)',
    duration: '60m',
    users: {
      start: 8,
      max: 15,
      rampUp: '5m',
      rampDown: '5m',
    },
    scenarios: [
      {
        name: 'sustained_load',
        weight: 100,
        executor: 'ramping-vus',
        options: {
          stages: [
            { duration: '5m', target: 12 },  // Aufbau
            { duration: '50m', target: 15 }, // Dauerbetrieb
            { duration: '5m', target: 0 },   // Abbau
          ],
        },
      },
    ],
    thresholds: {
      http_req_duration: ['p(95)<600'], // 95% unter 600ms
      http_req_failed: ['rate<0.02'],   // Fehlerrate unter 2%
      'http_reqs{expected_response:true}': ['rate>0.98'], // Erfolgsrate > 98%
    },
  },
};

// Realistische Benutzer-Verhaltensmuster
export const userBehaviorPatterns = {
  // Außendienstmitarbeiter - Morgens
  fieldWorkerMorning: {
    description: 'Außendienstmitarbeiter startet Tag',
    actions: [
      { action: 'login', weight: 100, thinkTime: '2-5s' },
      { action: 'viewDashboard', weight: 100, thinkTime: '3-8s' },
      { action: 'viewTodayPlaylist', weight: 100, thinkTime: '5-10s' },
      { action: 'calculateRoute', weight: 100, thinkTime: '10-15s' },
      { action: 'startRoute', weight: 80, thinkTime: '5-10s' },
    ],
  },

  // Außendienstmitarbeiter - Während der Arbeit
  fieldWorkerActive: {
    description: 'Außendienstmitarbeiter während Touren',
    actions: [
      { action: 'markStopComplete', weight: 100, thinkTime: '1-3s' },
      { action: 'updateStopStatus', weight: 80, thinkTime: '2-5s' },
      { action: 'addStopNotes', weight: 60, thinkTime: '10-30s' },
      { action: 'recalculateRoute', weight: 40, thinkTime: '5-10s' },
      { action: 'viewNextStop', weight: 100, thinkTime: '2-5s' },
    ],
  },

  // Administrator - Playlist-Management
  adminPlaylistManagement: {
    description: 'Administrator verwaltet Playlists',
    actions: [
      { action: 'login', weight: 100, thinkTime: '2-5s' },
      { action: 'viewAllPlaylists', weight: 100, thinkTime: '3-8s' },
      { action: 'createPlaylist', weight: 70, thinkTime: '30-60s' },
      { action: 'editPlaylist', weight: 80, thinkTime: '20-40s' },
      { action: 'addStops', weight: 90, thinkTime: '5-15s' },
      { action: 'optimizeRoute', weight: 85, thinkTime: '10-20s' },
      { action: 'assignPlaylist', weight: 75, thinkTime: '5-10s' },
    ],
  },

  // Supervisor - Überwachung
  supervisorMonitoring: {
    description: 'Supervisor überwacht Fortschritt',
    actions: [
      { action: 'login', weight: 100, thinkTime: '2-5s' },
      { action: 'viewDashboard', weight: 100, thinkTime: '5-10s' },
      { action: 'viewTeamProgress', weight: 100, thinkTime: '10-20s' },
      { action: 'viewIndividualProgress', weight: 80, thinkTime: '5-15s' },
      { action: 'generateReports', weight: 60, thinkTime: '15-30s' },
      { action: 'adjustAssignments', weight: 40, thinkTime: '20-40s' },
    ],
  },
};

// API-Endpunkt-Gewichtungen basierend auf realer Nutzung
export const apiEndpointWeights = {
  // Authentifizierung (häufig am Morgen)
  'POST /api/auth/login': 15,
  'GET /api/auth/status': 25,
  'POST /api/auth/logout': 8,

  // Playlists (Kern-Funktionalität)
  'GET /api/playlists': 30,
  'GET /api/playlists/{id}': 25,
  'POST /api/playlists': 12,
  'PUT /api/playlists/{id}': 20,
  'DELETE /api/playlists/{id}': 3,

  // Stopps (sehr häufig während Arbeit)
  'GET /api/playlists/{id}/stops': 35,
  'PUT /api/stops/{id}/status': 40,
  'POST /api/stops/{id}/notes': 20,

  // Routing (kritisch für Performance)
  'POST /api/route/calculate': 25,
  'GET /api/route/traffic': 15,
  'POST /api/route/optimize': 18,

  // Geocoding (Cache-abhängig)
  'POST /api/geocode': 20,
  'GET /api/geocode/reverse': 10,

  // Internationalisierung
  'POST /api/i18n/switch': 5,
  'GET /api/i18n/translations': 8,

  // Dashboard und Berichte
  'GET /api/dashboard/stats': 20,
  'GET /api/reports/progress': 15,
  'GET /api/reports/performance': 8,
};

// Performance-Budgets für Load-Tests
export const loadTestBudgets = {
  // Response-Zeit-Budgets (in Millisekunden)
  responseTime: {
    login: 100,           // Login-Prozess
    dashboard: 200,       // Dashboard-Laden
    playlistLoad: 150,    // Playlist-Übersicht
    routeCalculation: 300, // Route-Berechnung
    stopUpdate: 100,      // Stopp-Status-Update
    geocoding: 200,       // Adress-Geocoding
  },

  // Durchsatz-Budgets (Requests pro Sekunde)
  throughput: {
    login: 10,           // 10 gleichzeitige Logins/s
    apiCalls: 50,        // 50 API-Calls/s
    routeCalc: 5,        // 5 Route-Berechnungen/s
  },

  // Fehlerrate-Budgets (Prozent)
  errorRate: {
    total: 5,            // Gesamt-Fehlerrate unter 5%
    critical: 1,         // Kritische Pfade unter 1%
    timeout: 2,          // Timeout-Rate unter 2%
  },

  // Ressourcen-Budgets
  resources: {
    cpuUsage: 80,        // CPU-Nutzung unter 80%
    memoryUsage: 85,     // RAM-Nutzung unter 85%
    diskIO: 70,          // Disk-IO unter 70%
  },
};

export default loadTestConfigs;