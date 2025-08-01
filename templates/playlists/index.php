<?php
/**
 * Playlist-Übersicht
 * 
 * @author 2Brands Media GmbH
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t('playlist.title')) ?> - <?= htmlspecialchars($t('app.name')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/js/i18n.js"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <?php include __DIR__ . '/../layouts/header.php'; ?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 sm:px-0">
            <!-- Header mit Button -->
            <div class="sm:flex sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($t('playlist.title')) ?></h1>
                    <p class="mt-1 text-sm text-gray-600">
                        <?= htmlspecialchars($t('playlist.description')) ?>
                    </p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <a
                        href="/playlists/create"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <?= htmlspecialchars($t('playlist.create')) ?>
                    </a>
                </div>
            </div>

            <!-- Playlists Grid -->
            <div class="mt-8" x-data="playlistsIndex()">
                <!-- Filter -->
                <div class="mb-6">
                    <label for="date-filter" class="block text-sm font-medium text-gray-700">
                        <?= htmlspecialchars($t('playlist.filter_by_date')) ?>
                    </label>
                    <input
                        type="date"
                        id="date-filter"
                        x-model="filterDate"
                        @change="loadPlaylists"
                        class="mt-1 block w-full sm:w-64 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                </div>

                <!-- Loading State -->
                <div x-show="loading" class="text-center py-12">
                    <svg class="inline-block animate-spin h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <!-- Playlists Grid -->
                <div x-show="!loading" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <template x-for="playlist in playlists" :key="playlist.id">
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-medium text-gray-900" x-text="playlist.name"></h3>
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="getStatusClass(playlist.status)"
                                        x-text="getStatusText(playlist.status)"
                                    ></span>
                                </div>
                                <div class="mt-2 text-sm text-gray-600">
                                    <p x-text="formatDate(playlist.date)"></p>
                                </div>
                                <div class="mt-4 flex items-center text-sm text-gray-500">
                                    <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span x-text="`${playlist.total_stops} <?= htmlspecialchars($t('playlist.stops')) ?>`"></span>
                                    <span class="mx-2">·</span>
                                    <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span x-text="formatDuration(playlist.estimated_duration)"></span>
                                </div>
                                <div class="mt-4">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-500"><?= htmlspecialchars($t('playlist.progress')) ?></span>
                                        <span class="font-medium text-gray-900" x-text="`${playlist.completed_stops}/${playlist.total_stops}`"></span>
                                    </div>
                                    <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            class="bg-indigo-600 h-2 rounded-full"
                                            :style="`width: ${playlist.total_stops > 0 ? (playlist.completed_stops / playlist.total_stops * 100) : 0}%`"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-5 py-3">
                                <div class="flex space-x-3">
                                    <a
                                        :href="`/playlists/${playlist.id}/edit`"
                                        class="flex-1 text-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    >
                                        <?= htmlspecialchars($t('actions.edit')) ?>
                                    </a>
                                    <button
                                        @click="deletePlaylist(playlist.id)"
                                        class="flex-1 text-center px-3 py-2 border border-transparent rounded-md text-sm font-medium text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                    >
                                        <?= htmlspecialchars($t('actions.delete')) ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Keine Playlists -->
                <div x-show="!loading && playlists.length === 0" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900"><?= htmlspecialchars($t('playlist.no_playlists')) ?></h3>
                    <p class="mt-1 text-sm text-gray-500"><?= htmlspecialchars($t('playlist.no_playlists_desc')) ?></p>
                    <div class="mt-6">
                        <a
                            href="/playlists/create"
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <?= htmlspecialchars($t('playlist.create')) ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    function playlistsIndex() {
        return {
            playlists: [],
            loading: true,
            filterDate: '',

            init() {
                this.loadPlaylists();
            },

            async loadPlaylists() {
                this.loading = true;
                
                try {
                    let url = '/api/playlists';
                    if (this.filterDate) {
                        url += `?date=${this.filterDate}`;
                    }
                    
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (response.ok && data.success) {
                        this.playlists = data.data;
                    } else {
                        console.error('Fehler beim Laden der Playlists:', data.message);
                    }
                } catch (error) {
                    console.error('Netzwerkfehler:', error);
                } finally {
                    this.loading = false;
                }
            },

            async deletePlaylist(id) {
                if (!confirm('<?= htmlspecialchars($t('playlist.confirm_delete')) ?>')) {
                    return;
                }

                try {
                    const response = await fetch(`/api/playlists/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-Token': '<?= htmlspecialchars($auth->getCsrfToken()) ?>'
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (response.ok && data.success) {
                        this.playlists = this.playlists.filter(p => p.id !== id);
                    } else {
                        alert(data.message || '<?= htmlspecialchars($t('messages.error.general')) ?>');
                    }
                } catch (error) {
                    alert('<?= htmlspecialchars($t('messages.error.network')) ?>');
                }
            },

            formatDate(dateString) {
                const date = new Date(dateString);
                return new Intl.DateTimeFormat('<?= htmlspecialchars($lang) ?>', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                }).format(date);
            },

            formatDuration(minutes) {
                const hours = Math.floor(minutes / 60);
                const mins = minutes % 60;
                
                if (hours > 0) {
                    return `${hours} <?= htmlspecialchars($t('time.hours_short')) ?> ${mins} <?= htmlspecialchars($t('time.minutes_short')) ?>`;
                }
                return `${mins} <?= htmlspecialchars($t('time.minutes_short')) ?>`;
            },

            getStatusClass(status) {
                const classes = {
                    'draft': 'bg-gray-100 text-gray-800',
                    'active': 'bg-blue-100 text-blue-800',
                    'completed': 'bg-green-100 text-green-800'
                };
                return classes[status] || classes['draft'];
            },

            getStatusText(status) {
                const texts = {
                    'draft': '<?= htmlspecialchars($t('playlist.status.draft')) ?>',
                    'active': '<?= htmlspecialchars($t('playlist.status.active')) ?>',
                    'completed': '<?= htmlspecialchars($t('playlist.status.completed')) ?>'
                };
                return texts[status] || status;
            }
        }
    }
    </script>
</body>
</html>