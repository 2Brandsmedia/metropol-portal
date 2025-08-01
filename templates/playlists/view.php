<?php
/**
 * Playlist-Detailansicht mit Routenberechnung
 * 
 * @author 2Brands Media GmbH
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($playlist['name']) ?> - <?= htmlspecialchars($t('app.name')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/js/i18n.js"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="/js/routes.js"></script>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <?php include __DIR__ . '/../layouts/header.php'; ?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8" x-data="playlistDetail()">
        <div class="px-4 sm:px-0 h-full">
            <!-- Breadcrumb -->
            <nav class="mb-4" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-sm">
                    <li>
                        <a href="/dashboard" class="text-gray-500 hover:text-gray-700">Dashboard</a>
                    </li>
                    <li>
                        <span class="text-gray-400">/</span>
                    </li>
                    <li>
                        <a href="/playlists" class="text-gray-500 hover:text-gray-700"><?= htmlspecialchars($t('nav.playlists')) ?></a>
                    </li>
                    <li>
                        <span class="text-gray-400">/</span>
                    </li>
                    <li class="text-gray-900 font-medium"><?= htmlspecialchars($playlist['name']) ?></li>
                </ol>
            </nav>

            <!-- Header -->
            <div class="sm:flex sm:items-center sm:justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($playlist['name']) ?></h1>
                    <p class="mt-1 text-sm text-gray-600">
                        <?= htmlspecialchars($playlist['date']) ?> · 
                        <?= $playlist['total_stops'] ?> <?= htmlspecialchars($t('playlist.stops')) ?>
                    </p>
                </div>
                <div class="mt-4 sm:mt-0 flex space-x-3">
                    <a href="/playlists/<?= $playlist['id'] ?>/edit" class="btn btn-secondary">
                        <?= htmlspecialchars($t('actions.edit')) ?>
                    </a>
                </div>
            </div>

            <!-- View Toggle -->
            <div class="mb-4 flex justify-between items-center">
                <div class="flex bg-gray-100 rounded-lg p-1">
                    <button 
                        @click="viewMode = 'split'"
                        :class="viewMode === 'split' ? 'bg-white shadow' : ''"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-all">
                        <?= htmlspecialchars($t('playlist.view_modes.split')) ?>
                    </button>
                    <button 
                        @click="viewMode = 'list'"
                        :class="viewMode === 'list' ? 'bg-white shadow' : ''"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-all">
                        <?= htmlspecialchars($t('playlist.view_modes.list_only')) ?>
                    </button>
                    <button 
                        @click="viewMode = 'map'"
                        :class="viewMode === 'map' ? 'bg-white shadow' : ''"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-all">
                        <?= htmlspecialchars($t('playlist.view_modes.map_only')) ?>
                    </button>
                </div>
                
                <!-- Route Actions -->
                <div class="flex space-x-2">
                    <button id="calculate-route" class="btn btn-primary">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        <?= htmlspecialchars($t('routes.calculate')) ?>
                    </button>
                    <button id="refresh-traffic" class="btn btn-secondary" style="display: none;">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <?= htmlspecialchars($t('routes.refresh_traffic')) ?>
                    </button>
                    <button id="optimize-route" class="btn btn-secondary" disabled>
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                        </svg>
                        <?= htmlspecialchars($t('routes.optimize')) ?>
                    </button>
                </div>
            </div>

            <!-- Route Stats -->
            <div id="route-stats" class="route-stats-container mb-4"></div>

            <!-- Split View Container -->
            <div class="flex flex-col lg:flex-row gap-4 h-[calc(100vh-300px)]">
                <!-- Stopps Liste -->
                <div 
                    :class="{
                        'w-full lg:w-1/2': viewMode === 'split',
                        'w-full': viewMode === 'list',
                        'hidden': viewMode === 'map'
                    }"
                    class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-4 py-5 sm:p-6 h-full overflow-y-auto">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 sticky top-0 bg-white pb-2">
                            <?= htmlspecialchars($t('playlist.stops')) ?>
                        </h3>
                        
                        <div class="space-y-4">
                            <?php foreach ($playlist['stops'] as $index => $stop): ?>
                                <div 
                                    class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 cursor-pointer transition-all"
                                    :class="{'ring-2 ring-indigo-500 bg-indigo-50': activeStop === <?= $index ?>}"
                                    @click="selectStop(<?= $index ?>)"
                                    data-stop-index="<?= $index ?>">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-indigo-100 text-indigo-800 font-medium">
                                                <?= $index + 1 ?>
                                            </span>
                                        </div>
                                        <div class="ml-4 flex-grow">
                                            <h4 class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($stop['address']) ?>
                                            </h4>
                                            <div class="mt-1 text-sm text-gray-500">
                                                <?= htmlspecialchars($t('playlist.work_time')) ?>: <?= $stop['work_duration'] ?> <?= htmlspecialchars($t('time.minutes_short')) ?>
                                                <?php if ($stop['travel_duration']): ?>
                                                    · <?= htmlspecialchars($t('playlist.travel_duration')) ?>: <?= $stop['travel_duration'] ?> <?= htmlspecialchars($t('time.minutes_short')) ?>
                                                <?php endif; ?>
                                                <?php if ($stop['distance']): ?>
                                                    · <?= round($stop['distance'] / 1000, 1) ?> km
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($stop['notes']): ?>
                                                <p class="mt-1 text-sm text-gray-600">
                                                    <?= htmlspecialchars($stop['notes']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-4 flex-shrink-0">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                <?= $stop['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                                    ($stop['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800' : 
                                                    'bg-gray-100 text-gray-800') ?>">
                                                <?= htmlspecialchars($t('playlist.status.' . $stop['status'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Map Container -->
                <div 
                    :class="{
                        'w-full lg:w-1/2': viewMode === 'split',
                        'w-full': viewMode === 'map',
                        'hidden': viewMode === 'list'
                    }"
                    class="bg-white shadow rounded-lg overflow-hidden">
                    <div id="route-map" class="h-full"></div>
                </div>
            </div>
        </div>
    </main>

    <script>
    function playlistDetail() {
        return {
            playlistId: <?= $playlist['id'] ?>,
            viewMode: 'split',
            activeStop: null,
            routeData: null,
            map: null,
            stops: <?= json_encode($playlist['stops']) ?>,
            
            init() {
                this.initializeRouteHandlers();
            },
            
            initializeRouteHandlers() {
                const self = this;
                
                // Route berechnen
                document.getElementById('calculate-route').addEventListener('click', async function() {
                    try {
                        window.Routes.showLoading('calculate-route');
                        self.routeData = await window.Routes.calculateRoute(self.playlistId);
                        window.Routes.displayStats('route-stats', self.routeData);
                        
                        // Karte initialisieren wenn noch nicht vorhanden
                        if (!self.map) {
                            self.map = window.Routes.initMap('route-map', { 
                                enableTrafficControl: true,
                                onMarkerClick: (index) => self.selectStop(index)
                            });
                        }
                        
                        // Route mit nummerierten Markern anzeigen
                        window.Routes.displayRouteWithNumbers(self.routeData, self.stops);
                        
                        // Buttons aktualisieren
                        document.getElementById('optimize-route').disabled = false;
                        document.getElementById('refresh-traffic').style.display = 'inline-flex';
                    } catch (error) {
                        window.Routes.showError(error.message);
                    } finally {
                        window.Routes.hideLoading('calculate-route');
                    }
                });
                
                // Traffic aktualisieren
                document.getElementById('refresh-traffic').addEventListener('click', async function() {
                    try {
                        window.Routes.showLoading('refresh-traffic');
                        const trafficData = await window.Routes.refreshTrafficData(self.playlistId);
                        
                        // Stats aktualisieren
                        if (self.routeData && trafficData) {
                            self.routeData.total_duration_in_traffic_min = trafficData.current_duration_min;
                            self.routeData.traffic_delay_min = trafficData.traffic_delay_min;
                            self.routeData.traffic_severity = trafficData.traffic_severity;
                            
                            window.Routes.displayStats('route-stats', self.routeData);
                            window.Routes.showSuccess('<?= htmlspecialchars($t('routes.traffic_updated')) ?>');
                        }
                    } catch (error) {
                        window.Routes.showError('<?= htmlspecialchars($t('routes.traffic_update_failed')) ?>');
                    } finally {
                        window.Routes.hideLoading('refresh-traffic');
                    }
                });
                
                // Route optimieren
                document.getElementById('optimize-route').addEventListener('click', async function() {
                    try {
                        window.Routes.showLoading('optimize-route');
                        self.routeData = await window.Routes.optimizeRoute(self.playlistId);
                        window.Routes.displayStats('route-stats', self.routeData);
                        window.Routes.displayRouteWithNumbers(self.routeData, self.stops);
                        window.Routes.showSuccess('<?= htmlspecialchars($t('routes.route_optimized')) ?>');
                    } catch (error) {
                        window.Routes.showError(error.message);
                    } finally {
                        window.Routes.hideLoading('optimize-route');
                    }
                });
            },
            
            selectStop(index) {
                this.activeStop = index;
                
                // Zu Marker auf Karte zoomen
                if (this.map && window.Routes.markers && window.Routes.markers[index]) {
                    const marker = window.Routes.markers[index];
                    this.map.setView(marker.getLatLng(), 16);
                    marker.openPopup();
                }
                
                // Scroll zu Element in Liste
                const element = document.querySelector(`[data-stop-index="${index}"]`);
                if (element) {
                    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        }
    }
    </script>

    <style>
    .btn {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.15s ease;
        cursor: pointer;
        border: 1px solid transparent;
    }
    
    .btn-primary {
        background-color: #4F46E5;
        color: white;
    }
    
    .btn-primary:hover {
        background-color: #4338CA;
    }
    
    .btn-secondary {
        background-color: white;
        color: #374151;
        border-color: #D1D5DB;
    }
    
    .btn-secondary:hover {
        background-color: #F9FAFB;
    }
    
    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .btn svg {
            width: 16px;
            height: 16px;
        }
        
        /* Stack buttons on mobile */
        #route-actions {
            flex-direction: column;
            width: 100%;
        }
        
        #route-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
    
    /* Route Stats Responsive */
    .route-stats-container {
        margin-bottom: 1rem;
    }
    
    /* Mobile View Toggle */
    @media (max-width: 1024px) {
        .h-\[calc\(100vh-300px\)\] {
            height: auto;
            min-height: 500px;
        }
    }
    </style>
</body>
</html>