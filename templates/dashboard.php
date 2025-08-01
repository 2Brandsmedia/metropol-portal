<?php
/**
 * Dashboard-Template
 * 
 * @author 2Brands Media GmbH
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t('nav.dashboard')) ?> - <?= htmlspecialchars($t('app.name')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/js/i18n.js"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <?php include __DIR__ . '/layouts/header.php'; ?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Welcome Section -->
        <div class="px-4 py-6 sm:px-0">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h1 class="text-2xl font-bold text-gray-900">
                        <?= htmlspecialchars($t('messages.welcome', ['name' => $user['name']])) ?>
                    </h1>
                    <p class="mt-1 text-sm text-gray-600">
                        <?= htmlspecialchars($t('dashboard.subtitle')) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="mt-8 px-4 sm:px-0">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Offene Aufgaben -->
                <div class="bg-white overflow-hidden shadow rounded-lg" x-data="{ count: 0 }">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        <?= htmlspecialchars($t('dashboard.stats.open_tasks')) ?>
                                    </dt>
                                    <dd class="mt-1 text-3xl font-semibold text-gray-900" x-text="count">-</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <a href="/tasks" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            <?= htmlspecialchars($t('actions.view_all')) ?>
                        </a>
                    </div>
                </div>

                <!-- Heutige Routen -->
                <div class="bg-white overflow-hidden shadow rounded-lg" x-data="{ count: 0 }">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        <?= htmlspecialchars($t('dashboard.stats.todays_routes')) ?>
                                    </dt>
                                    <dd class="mt-1 text-3xl font-semibold text-gray-900" x-text="count">-</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <a href="/routes" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            <?= htmlspecialchars($t('actions.view_all')) ?>
                        </a>
                    </div>
                </div>

                <!-- Erledigte Aufgaben -->
                <div class="bg-white overflow-hidden shadow rounded-lg" x-data="{ count: 0 }">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        <?= htmlspecialchars($t('dashboard.stats.completed_today')) ?>
                                    </dt>
                                    <dd class="mt-1 text-3xl font-semibold text-gray-900" x-text="count">-</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <span class="text-sm font-medium text-green-600">
                            <?= htmlspecialchars($t('dashboard.stats.on_track')) ?>
                        </span>
                    </div>
                </div>

                <!-- Durchschnittliche Zeit -->
                <div class="bg-white overflow-hidden shadow rounded-lg" x-data="{ time: '0:00' }">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        <?= htmlspecialchars($t('dashboard.stats.avg_time')) ?>
                                    </dt>
                                    <dd class="mt-1 text-3xl font-semibold text-gray-900" x-text="time">-</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <span class="text-sm font-medium text-gray-500">
                            <?= htmlspecialchars($t('dashboard.stats.per_task')) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-8 px-4 sm:px-0">
            <h2 class="text-lg font-medium text-gray-900 mb-4">
                <?= htmlspecialchars($t('dashboard.quick_actions')) ?>
            </h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Neue Aufgabe -->
                <a href="/tasks/create" class="relative rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm flex items-center space-x-3 hover:border-gray-400 focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                    <div class="flex-shrink-0">
                        <svg class="h-10 w-10 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="absolute inset-0" aria-hidden="true"></span>
                        <p class="text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($t('dashboard.actions.create_task')) ?>
                        </p>
                        <p class="text-sm text-gray-500">
                            <?= htmlspecialchars($t('dashboard.actions.create_task_desc')) ?>
                        </p>
                    </div>
                </a>

                <!-- Route planen -->
                <a href="/playlists/create" class="relative rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm flex items-center space-x-3 hover:border-gray-400 focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                    <div class="flex-shrink-0">
                        <svg class="h-10 w-10 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="absolute inset-0" aria-hidden="true"></span>
                        <p class="text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($t('dashboard.actions.plan_route')) ?>
                        </p>
                        <p class="text-sm text-gray-500">
                            <?= htmlspecialchars($t('dashboard.actions.plan_route_desc')) ?>
                        </p>
                    </div>
                </a>

                <!-- Berichte anzeigen -->
                <a href="/reports" class="relative rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm flex items-center space-x-3 hover:border-gray-400 focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                    <div class="flex-shrink-0">
                        <svg class="h-10 w-10 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="absolute inset-0" aria-hidden="true"></span>
                        <p class="text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($t('dashboard.actions.view_reports')) ?>
                        </p>
                        <p class="text-sm text-gray-500">
                            <?= htmlspecialchars($t('dashboard.actions.view_reports_desc')) ?>
                        </p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="mt-8 px-4 sm:px-0">
            <h2 class="text-lg font-medium text-gray-900 mb-4">
                <?= htmlspecialchars($t('dashboard.recent_activity')) ?>
            </h2>
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                <ul class="divide-y divide-gray-200" x-data="{ activities: [] }">
                    <template x-if="activities.length === 0">
                        <li class="px-6 py-4">
                            <p class="text-sm text-gray-500 text-center">
                                <?= htmlspecialchars($t('dashboard.no_recent_activity')) ?>
                            </p>
                        </li>
                    </template>
                    <template x-for="activity in activities" :key="activity.id">
                        <li class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900" x-text="activity.description"></p>
                                    <p class="text-sm text-gray-500" x-text="activity.time"></p>
                                </div>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </main>

    <script>
        // Dashboard-Daten laden
        document.addEventListener('alpine:init', () => {
            // Stats laden
            fetch('/api/dashboard/stats')
                .then(r => r.json())
                .then(data => {
                    // Alpine.js Komponenten aktualisieren
                    // TODO: Implementierung
                })
                .catch(console.error);
        });

        // i18n ready
        window.i18n.ready().then(() => {
            console.log('i18n loaded, current language:', window.i18n.getLanguage());
        });
    </script>
</body>
</html>