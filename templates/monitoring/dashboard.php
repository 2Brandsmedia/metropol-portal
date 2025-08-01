<!DOCTYPE html>
<html lang="de" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Monitoring - Metropol Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        .metric-card { @apply bg-white rounded-lg shadow-sm border border-gray-200 p-6; }
        .metric-value { @apply text-2xl font-bold; }
        .metric-label { @apply text-sm text-gray-600 mt-1; }
        .status-excellent { @apply text-green-600 bg-green-50; }
        .status-good { @apply text-green-500 bg-green-50; }
        .status-warning { @apply text-yellow-600 bg-yellow-50; }
        .status-critical { @apply text-red-600 bg-red-50; }
        .alert-critical { @apply border-l-4 border-red-500 bg-red-50; }
        .alert-high { @apply border-l-4 border-orange-500 bg-orange-50; }
        .alert-medium { @apply border-l-4 border-yellow-500 bg-yellow-50; }
        .alert-low { @apply border-l-4 border-blue-500 bg-blue-50; }
    </style>
</head>
<body class="h-full">
    <div x-data="monitoringDashboard()" class="min-h-full">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center space-x-4">
                        <h1 class="text-2xl font-bold text-gray-900">System Monitoring</h1>
                        <div class="flex items-center space-x-2">
                            <div :class="systemHealth.healthy ? 'bg-green-500' : 'bg-red-500'" 
                                 class="w-3 h-3 rounded-full"></div>
                            <span class="text-sm font-medium" 
                                  :class="systemHealth.healthy ? 'text-green-700' : 'text-red-700'"
                                  x-text="systemHealth.healthy ? 'System Healthy' : 'System Issues'"></span>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button @click="refreshData()" 
                                :disabled="isLoading"
                                class="bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white px-4 py-2 rounded-md text-sm font-medium">
                            <span x-show="!isLoading">Refresh</span>
                            <span x-show="isLoading">Loading...</span>
                        </button>
                        <div class="text-sm text-gray-500">
                            Last updated: <span x-text="lastUpdated"></span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- System Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Requests -->
                <div class="metric-card">
                    <div class="metric-value text-blue-600" x-text="formatNumber(metrics.performance?.total_requests_last_hour || 0)"></div>
                    <div class="metric-label">Requests (letzte Stunde)</div>
                </div>

                <!-- Average Response Time -->
                <div class="metric-card">
                    <div class="flex items-center">
                        <div class="metric-value" 
                             :class="getResponseTimeColor(metrics.performance?.avg_response_times?.[0]?.avg_response_time)"
                             x-text="Math.round(metrics.performance?.avg_response_times?.[0]?.avg_response_time || 0) + 'ms'"></div>
                    </div>
                    <div class="metric-label">Ø Response Time</div>
                </div>

                <!-- Error Rate -->
                <div class="metric-card">
                    <div class="metric-value" 
                         :class="getErrorRateColor(metrics.errors?.total_errors_last_hour)"
                         x-text="metrics.errors?.total_errors_last_hour || 0"></div>
                    <div class="metric-label">Errors (letzte Stunde)</div>
                </div>

                <!-- Active Alerts -->
                <div class="metric-card">
                    <div class="metric-value" 
                         :class="getAlertColor(metrics.alerts?.total_active)"
                         x-text="metrics.alerts?.total_active || 0"></div>
                    <div class="metric-label">Active Alerts</div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Response Time Chart -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Response Time Trends</h3>
                    <canvas id="responseTimeChart" width="400" height="200"></canvas>
                </div>

                <!-- Performance Distribution -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Distribution</h3>
                    <div class="space-y-3" x-show="metrics.performance?.performance_grades">
                        <template x-for="grade in metrics.performance?.performance_grades || []" :key="grade.performance_grade">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-3 h-3 rounded-full" 
                                         :class="{
                                             'bg-green-500': grade.performance_grade === 'excellent',
                                             'bg-blue-500': grade.performance_grade === 'good', 
                                             'bg-yellow-500': grade.performance_grade === 'warning',
                                             'bg-red-500': grade.performance_grade === 'critical'
                                         }"></div>
                                    <span class="text-sm font-medium capitalize" x-text="grade.performance_grade"></span>
                                </div>
                                <span class="text-sm text-gray-600" x-text="grade.count"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- System Metrics -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Memory Usage -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" x-show="metrics.system?.memory">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Memory Usage</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium">Current</span>
                            <span class="text-sm font-bold" 
                                  :class="getSystemMetricColor(metrics.system.memory?.percentage)"
                                  x-text="Math.round(metrics.system.memory?.percentage || 0) + '%'"></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="h-2 rounded-full transition-all duration-300" 
                                 :class="getSystemMetricColor(metrics.system.memory?.percentage, true)"
                                 :style="`width: ${Math.min(metrics.system.memory?.percentage || 0, 100)}%`"></div>
                        </div>
                        <div class="text-xs text-gray-500" 
                             x-text="`${Math.round(metrics.system.memory?.value || 0)}MB used`"></div>
                    </div>
                </div>

                <!-- Disk Usage -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" x-show="metrics.system?.disk">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Disk Usage</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium">Current</span>
                            <span class="text-sm font-bold" 
                                  :class="getSystemMetricColor(metrics.system.disk?.percentage)"
                                  x-text="Math.round(metrics.system.disk?.percentage || 0) + '%'"></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="h-2 rounded-full transition-all duration-300" 
                                 :class="getSystemMetricColor(metrics.system.disk?.percentage, true)"
                                 :style="`width: ${Math.min(metrics.system.disk?.percentage || 0, 100)}%`"></div>
                        </div>
                        <div class="text-xs text-gray-500" 
                             x-text="`${Math.round(metrics.system.disk?.value || 0)}GB used`"></div>
                    </div>
                </div>

                <!-- Load Average -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" x-show="metrics.system?.load">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">System Load</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium">Load Average</span>
                            <span class="text-sm font-bold text-blue-600" 
                                  x-text="(metrics.system.load?.value || 0).toFixed(2)"></span>
                        </div>
                        <div class="text-xs text-gray-500">1-minute average</div>
                    </div>
                </div>
            </div>

            <!-- API Endpoints Performance -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">API Endpoints Performance</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Endpoint</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requests</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Response</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Max Response</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Success Rate</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="endpoint in metrics.api?.endpoint_stats || []" :key="endpoint.endpoint + endpoint.http_method">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" x-text="endpoint.endpoint"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                              :class="getMethodColor(endpoint.http_method)"
                                              x-text="endpoint.http_method"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatNumber(endpoint.total_requests)"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span :class="getResponseTimeColor(endpoint.avg_response_time)" 
                                              x-text="Math.round(endpoint.avg_response_time) + 'ms'"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="Math.round(endpoint.max_response_time) + 'ms'"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span :class="getSuccessRateColor(calculateSuccessRate(endpoint))"
                                              x-text="calculateSuccessRate(endpoint) + '%'"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Errors -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8" x-show="metrics.errors?.recent_unresolved?.length > 0">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Unresolved Errors</h3>
                </div>
                <div class="divide-y divide-gray-200">
                    <template x-for="error in metrics.errors?.recent_unresolved || []" :key="error.id">
                        <div class="px-6 py-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                              :class="getSeverityColor(error.severity)"
                                              x-text="error.severity.toUpperCase()"></span>
                                        <span class="text-sm font-medium text-gray-900" x-text="error.error_type"></span>
                                        <span class="text-sm text-gray-500" x-text="`(${error.error_count} occurrences)`"></span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-600" x-text="error.message"></p>
                                </div>
                                <div class="text-right text-xs text-gray-500">
                                    <div>First: <span x-text="formatDateTime(error.first_seen)"></span></div>
                                    <div>Last: <span x-text="formatDateTime(error.last_seen)"></span></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Active Alerts -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200" x-show="metrics.alerts?.active_alerts?.length > 0">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Active Alerts</h3>
                </div>
                <div class="divide-y divide-gray-200">
                    <template x-for="alert in metrics.alerts?.active_alerts || []" :key="alert.id">
                        <div class="px-6 py-4" :class="`alert-${alert.severity}`">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                              :class="getSeverityColor(alert.severity)"
                                              x-text="alert.severity.toUpperCase()"></span>
                                        <span class="text-sm font-medium text-gray-900" x-text="alert.alert_name"></span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-600" x-text="alert.message"></p>
                                </div>
                                <div class="text-right text-xs text-gray-500">
                                    <div x-text="formatDateTime(alert.created_at)"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </main>
    </div>

    <script>
        function monitoringDashboard() {
            return {
                metrics: {},
                systemHealth: { healthy: true },
                isLoading: false,
                lastUpdated: '',
                refreshInterval: null,

                init() {
                    this.refreshData();
                    this.startAutoRefresh();
                },

                async refreshData() {
                    this.isLoading = true;
                    try {
                        const response = await fetch('/api/monitoring/live-metrics');
                        this.metrics = await response.json();
                        
                        const healthResponse = await fetch('/api/monitoring/health');
                        this.systemHealth = await healthResponse.json();
                        
                        this.lastUpdated = new Date().toLocaleTimeString();
                        this.updateCharts();
                    } catch (error) {
                        console.error('Failed to refresh data:', error);
                    } finally {
                        this.isLoading = false;
                    }
                },

                startAutoRefresh() {
                    this.refreshInterval = setInterval(() => {
                        this.refreshData();
                    }, 30000); // 30 Sekunden
                },

                updateCharts() {
                    // Response Time Chart Update würde hier implementiert
                    // Chart.js Integration für historische Daten
                },

                formatNumber(num) {
                    return new Intl.NumberFormat().format(num);
                },

                formatDateTime(dateString) {
                    return new Date(dateString).toLocaleString();
                },

                getResponseTimeColor(responseTime) {
                    if (!responseTime) return 'text-gray-500';
                    if (responseTime <= 100) return 'text-green-600';
                    if (responseTime <= 200) return 'text-blue-600';
                    if (responseTime <= 500) return 'text-yellow-600';
                    return 'text-red-600';
                },

                getErrorRateColor(errorCount) {
                    if (!errorCount || errorCount === 0) return 'text-green-600';
                    if (errorCount <= 5) return 'text-yellow-600';
                    return 'text-red-600';
                },

                getAlertColor(alertCount) {
                    if (!alertCount || alertCount === 0) return 'text-green-600';
                    if (alertCount <= 3) return 'text-yellow-600';
                    return 'text-red-600';
                },

                getSystemMetricColor(percentage, isBackground = false) {
                    if (!percentage) return isBackground ? 'bg-gray-300' : 'text-gray-500';
                    if (percentage <= 70) return isBackground ? 'bg-green-500' : 'text-green-600';
                    if (percentage <= 85) return isBackground ? 'bg-yellow-500' : 'text-yellow-600';
                    return isBackground ? 'bg-red-500' : 'text-red-600';
                },

                getSeverityColor(severity) {
                    const colors = {
                        'emergency': 'bg-red-100 text-red-800',
                        'critical': 'bg-red-100 text-red-800',
                        'error': 'bg-red-50 text-red-700',
                        'warning': 'bg-yellow-100 text-yellow-800',
                        'notice': 'bg-blue-100 text-blue-800',
                        'info': 'bg-blue-50 text-blue-700',
                        'debug': 'bg-gray-100 text-gray-800'
                    };
                    return colors[severity] || 'bg-gray-100 text-gray-800';
                },

                getMethodColor(method) {
                    const colors = {
                        'GET': 'bg-green-100 text-green-800',
                        'POST': 'bg-blue-100 text-blue-800',
                        'PUT': 'bg-yellow-100 text-yellow-800',
                        'DELETE': 'bg-red-100 text-red-800',
                        'PATCH': 'bg-purple-100 text-purple-800'
                    };
                    return colors[method] || 'bg-gray-100 text-gray-800';
                },

                calculateSuccessRate(endpoint) {
                    if (!endpoint.total_requests) return 0;
                    return Math.round((endpoint.total_success / endpoint.total_requests) * 100);
                },

                getSuccessRateColor(rate) {
                    if (rate >= 99) return 'text-green-600';
                    if (rate >= 95) return 'text-blue-600';
                    if (rate >= 90) return 'text-yellow-600';
                    return 'text-red-600';
                }
            }
        }
    </script>
</body>
</html>