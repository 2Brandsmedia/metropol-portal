// K6 Load Test Konfiguration - Metropol Portal
// Normale Betriebslast simulieren (10-20 gleichzeitige Benutzer)
// Entwickelt von 2Brands Media GmbH

export let options = {
  stages: [
    { duration: '2m', target: 5 },   // Aufwärmphase
    { duration: '2m', target: 15 },  // Anstieg auf normale Last
    { duration: '5m', target: 15 },  // Plateauphase bei normaler Last
    { duration: '2m', target: 20 },  // Anstieg auf Spitzenlast
    { duration: '3m', target: 20 },  // Spitzenlast halten
    { duration: '2m', target: 0 },   // Abklingen
  ],
  
  thresholds: {
    // Performance-Ziele aus CLAUDE.md
    http_req_duration: ['p(95)<500', 'p(99)<1000'], // 95% unter 500ms, 99% unter 1s
    http_req_failed: ['rate<0.05'],                  // Fehlerrate unter 5%
    
    // Kritische Endpunkte
    'http_req_duration{name:login}': ['p(95)<100'],           // Login unter 100ms
    'http_req_duration{name:route_calculation}': ['p(95)<300'], // Route unter 300ms
    'http_req_duration{name:playlist_load}': ['p(95)<200'],   // Playlist unter 200ms
    'http_req_duration{name:stop_update}': ['p(95)<100'],    // Stopp-Update unter 100ms
    
    // Durchsatz und Zuverlässigkeit
    http_reqs: ['rate>25'],                    // Mindestens 25 Requests/Sekunde
    'http_reqs{expected_response:true}': ['rate>0.95'], // 95% Erfolgsrate
    
    // Spezifische Geschäftslogik
    'group_duration{group:::Login Flow}': ['p(95)<2000'],
    'group_duration{group:::Playlist Management}': ['p(95)<3000'],
    'group_duration{group:::Route Calculation}': ['p(95)<5000'],
  },
  
  // Browser-ähnliches Verhalten
  userAgent: 'MetropolPortal-LoadTest/1.0 (2Brands Media GmbH)',
  
  // Erweiterte Konfiguration
  setupTimeout: '30s',
  teardownTimeout: '30s',
  
  // HTTP-Konfiguration für realistische Bedingungen
  batch: 10,                    // Batch-Requests für bessere Performance
  batchPerHost: 5,             // Max gleichzeitige Requests pro Host
  httpDebug: 'failed',         // Nur fehlgeschlagene Requests loggen
  insecureSkipTLSVerify: true,
  noConnectionReuse: false,
  noVUConnectionReuse: false,
  
  // Datensammlung
  summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
  
  // Cloud-Konfiguration (für K6 Cloud falls verwendet)
  ext: {
    loadimpact: {
      projectID: 3574469,
      name: 'Metropol Portal Load Test',
      distribution: {
        'amazon:de:frankfurt': { loadZone: 'amazon:de:frankfurt', percent: 100 },
      },
    },
  },
};