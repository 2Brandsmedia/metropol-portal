// K6 Stress Test Konfiguration - Metropol Portal
// System-Grenzen ermitteln (bis 50 Benutzer)
// Entwickelt von 2Brands Media GmbH

export let options = {
  stages: [
    { duration: '2m', target: 10 },  // Normale Last als Basis
    { duration: '3m', target: 25 },  // Erhöhte Last
    { duration: '5m', target: 40 },  // Hohe Last
    { duration: '3m', target: 50 },  // Maximale Last
    { duration: '2m', target: 50 },  // Maximale Last halten
    { duration: '3m', target: 25 },  // Rückgang
    { duration: '2m', target: 0 },   // Vollständiger Rückgang
  ],
  
  thresholds: {
    // Erweiterte Toleranzen für Stress-Bedingungen
    http_req_duration: ['p(95)<2000'],       // 95% unter 2s (degradiert)
    http_req_failed: ['rate<0.15'],          // Fehlerrate unter 15%
    
    // Kritische Endpunkte müssen weiterhin funktionieren
    'http_req_duration{name:login}': ['p(95)<500'],     // Login maximal 500ms
    'http_req_duration{name:health_check}': ['p(95)<200'], // Health-Check schnell
    
    // System-Stabilität
    http_reqs: ['rate>10'],                  // Mindestens 10 RPS auch unter Stress
    'http_reqs{expected_response:true}': ['rate>0.8'], // 80% Erfolgsrate minimal
    
    // Stress-spezifische Metriken
    'group_duration{group:::High Load Scenario}': ['p(95)<10000'],
    'http_req_connecting': ['p(95)<1000'],   // Verbindungsaufbau unter Stress
    'http_req_tls_handshaking': ['p(95)<1000'], // TLS-Handshake unter Stress
  },
  
  // Aggressivere Browser-Simulation
  userAgent: 'MetropolPortal-StressTest/1.0 (2Brands Media GmbH)',
  
  // Erweiterte Timeouts für Stress-Bedingungen
  setupTimeout: '60s',
  teardownTimeout: '60s',
  
  // HTTP-Konfiguration für Stress-Test
  batch: 15,                    // Mehr batch requests
  batchPerHost: 8,             // Mehr gleichzeitige Requests
  httpDebug: 'failed',         // Nur Fehler loggen
  insecureSkipTLSVerify: true,
  noConnectionReuse: false,    // Connection Reuse für Effizienz
  noVUConnectionReuse: false,
  
  // Erweiterte Datensammlung
  summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)', 'p(99.9)'],
  
  // Detailed Timing
  summaryTimeUnit: 'ms',
  
  // Stress-Test spezifische Konfiguration
  discardResponseBodies: false, // Responses behalten für Analyse
  
  // Resource-Monitoring
  systemTags: [
    'check',
    'error',
    'error_code',
    'expected_response',
    'group',
    'method',
    'name',
    'proto',
    'scenario',
    'status',
    'subproto',
    'tls_version',
    'url',
  ],
};