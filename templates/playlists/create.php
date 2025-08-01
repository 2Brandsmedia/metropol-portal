<?php
/**
 * Playlist-Erstellungsformular
 * 
 * @author 2Brands Media GmbH
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t('playlist.create')) ?> - <?= htmlspecialchars($t('app.name')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/js/i18n.js"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="/js/geocoding.js"></script>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <?php include __DIR__ . '/../layouts/header.php'; ?>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumbs -->
        <?php
        echo $ui->renderBreadcrumbs([
            ['label' => $t('nav.dashboard'), 'url' => '/dashboard'],
            ['label' => $t('playlist.title'), 'url' => '/playlists'],
            ['label' => $t('playlist.create')]
        ]);
        ?>

        <div class="mt-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">
                <?= htmlspecialchars($t('playlist.create')) ?>
            </h1>

            <form x-data="playlistForm()" @submit.prevent="submitForm" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <!-- Grundinformationen -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">
                        <?= htmlspecialchars($t('playlist.basic_info')) ?>
                    </h2>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">
                                <?= htmlspecialchars($t('playlist.name')) ?>
                            </label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                x-model="formData.name"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                placeholder="<?= htmlspecialchars($t('playlist.name_placeholder')) ?>"
                            >
                        </div>

                        <!-- Datum -->
                        <div>
                            <label for="date" class="block text-sm font-medium text-gray-700">
                                <?= htmlspecialchars($t('playlist.date')) ?>
                            </label>
                            <input
                                type="date"
                                id="date"
                                name="date"
                                x-model="formData.date"
                                required
                                min="<?= date('Y-m-d') ?>"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                        </div>
                    </div>
                </div>

                <!-- Stopps -->
                <div class="bg-white shadow rounded-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-medium text-gray-900">
                            <?= htmlspecialchars($t('playlist.stops')) ?>
                            <span class="text-sm text-gray-500" x-text="`(${stops.length}/20)`"></span>
                        </h2>
                        <button
                            type="button"
                            @click="addStop"
                            :disabled="stops.length >= 20"
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="-ml-0.5 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <?= htmlspecialchars($t('playlist.add_stop')) ?>
                        </button>
                    </div>

                    <!-- Stopps-Liste -->
                    <div id="stops-list" class="space-y-3">
                        <template x-for="(stop, index) in stops" :key="stop.id">
                            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50" :data-stop-id="stop.id">
                                <div class="flex items-start space-x-3">
                                    <!-- Drag Handle -->
                                    <div class="flex-shrink-0 cursor-move handle">
                                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                        </svg>
                                    </div>

                                    <!-- Stopp-Nummer -->
                                    <div class="flex-shrink-0">
                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-indigo-100 text-indigo-800 text-sm font-medium" x-text="index + 1"></span>
                                    </div>

                                    <!-- Stopp-Details -->
                                    <div class="flex-grow space-y-3">
                                        <!-- Adresse -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">
                                                <?= htmlspecialchars($t('playlist.address')) ?>
                                            </label>
                                            <input
                                                type="text"
                                                x-model="stop.address"
                                                required
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                placeholder="<?= htmlspecialchars($t('playlist.address_placeholder')) ?>"
                                                data-geocoding="true"
                                                :data-latitude-field="'#lat_' + stop.id"
                                                :data-longitude-field="'#lng_' + stop.id"
                                                data-min-length="15"
                                                @change="geocodeAddress(stop, index)"
                                            >
                                            <input type="hidden" :id="'lat_' + stop.id" x-model="stop.latitude">
                                            <input type="hidden" :id="'lng_' + stop.id" x-model="stop.longitude">
                                            <div x-show="stop.geocoding" class="mt-1 text-sm text-gray-500">
                                                <svg class="inline-block h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Geokodierung...
                                            </div>
                                            <div x-show="stop.geocoded && stop.latitude" class="mt-1 text-sm text-green-600">
                                                ✓ Adresse erkannt
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <!-- Arbeitszeit -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">
                                                    <?= htmlspecialchars($t('playlist.work_time')) ?>
                                                </label>
                                                <div class="mt-1 relative rounded-md shadow-sm">
                                                    <input
                                                        type="number"
                                                        x-model.number="stop.work_duration"
                                                        min="5"
                                                        max="480"
                                                        required
                                                        class="block w-full pr-12 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                    >
                                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                        <span class="text-gray-500 sm:text-sm"><?= htmlspecialchars($t('time.minutes_short')) ?></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Notizen -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">
                                                    <?= htmlspecialchars($t('playlist.notes')) ?>
                                                </label>
                                                <input
                                                    type="text"
                                                    x-model="stop.notes"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                    placeholder="<?= htmlspecialchars($t('playlist.notes_placeholder')) ?>"
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Löschen -->
                                    <div class="flex-shrink-0">
                                        <button
                                            type="button"
                                            @click="removeStop(index)"
                                            class="text-red-600 hover:text-red-900"
                                        >
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Keine Stopps -->
                        <div x-show="stops.length === 0" class="text-center py-12 text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <p class="mt-2"><?= htmlspecialchars($t('playlist.no_stops')) ?></p>
                        </div>
                    </div>

                    <!-- Gesamtdauer -->
                    <div x-show="stops.length > 0" class="mt-4 pt-4 border-t border-gray-200">
                        <div class="flex justify-between text-sm">
                            <span class="font-medium text-gray-700"><?= htmlspecialchars($t('playlist.estimated_duration')) ?>:</span>
                            <span class="text-gray-900" x-text="formatDuration(totalDuration)"></span>
                        </div>
                    </div>
                </div>

                <!-- Formular-Aktionen -->
                <div class="flex justify-end space-x-3">
                    <a
                        href="/playlists"
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        <?= htmlspecialchars($t('actions.cancel')) ?>
                    </a>
                    <button
                        type="submit"
                        :disabled="loading || stops.length === 0"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span x-show="!loading"><?= htmlspecialchars($t('actions.create')) ?></span>
                        <span x-show="loading" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <?= htmlspecialchars($t('messages.saving')) ?>...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
    function playlistForm() {
        return {
            formData: {
                name: '',
                date: '<?= date('Y-m-d') ?>'
            },
            stops: [],
            loading: false,
            nextStopId: 1,
            sortable: null,

            init() {
                // Sortable.js initialisieren
                this.$nextTick(() => {
                    this.initSortable();
                });
            },

            initSortable() {
                const el = document.getElementById('stops-list');
                this.sortable = Sortable.create(el, {
                    handle: '.handle',
                    animation: 150,
                    onEnd: (evt) => {
                        // Alpine.js Array neu sortieren
                        const movedItem = this.stops.splice(evt.oldIndex, 1)[0];
                        this.stops.splice(evt.newIndex, 0, movedItem);
                    }
                });
            },

            addStop() {
                if (this.stops.length >= 20) return;
                
                this.stops.push({
                    id: this.nextStopId++,
                    address: '',
                    work_duration: 30,
                    notes: '',
                    latitude: null,
                    longitude: null,
                    geocoding: false,
                    geocoded: false
                });
            },
            
            async geocodeAddress(stop, index) {
                if (!stop.address || stop.address.length < 15) return;
                
                stop.geocoding = true;
                stop.geocoded = false;
                
                try {
                    const result = await window.Geocoding.geocode(stop.address);
                    if (result) {
                        stop.latitude = result.latitude;
                        stop.longitude = result.longitude;
                        stop.geocoded = true;
                    }
                } catch (error) {
                    console.error('Geocoding error:', error);
                } finally {
                    stop.geocoding = false;
                }
            },

            removeStop(index) {
                this.stops.splice(index, 1);
            },

            get totalDuration() {
                return this.stops.reduce((sum, stop) => sum + (stop.work_duration || 0), 0);
            },

            formatDuration(minutes) {
                const hours = Math.floor(minutes / 60);
                const mins = minutes % 60;
                
                if (hours > 0) {
                    return `${hours} <?= htmlspecialchars($t('time.hours_short')) ?> ${mins} <?= htmlspecialchars($t('time.minutes_short')) ?>`;
                }
                return `${mins} <?= htmlspecialchars($t('time.minutes_short')) ?>`;
            },

            async submitForm() {
                if (this.stops.length === 0) {
                    alert('<?= htmlspecialchars($t('playlist.validation.min_stops')) ?>');
                    return;
                }

                // Validierung
                for (let stop of this.stops) {
                    if (!stop.address.trim()) {
                        alert('<?= htmlspecialchars($t('playlist.validation.address_required')) ?>');
                        return;
                    }
                    if (stop.work_duration < 5 || stop.work_duration > 480) {
                        alert('<?= htmlspecialchars($t('playlist.validation.invalid_duration')) ?>');
                        return;
                    }
                }

                this.loading = true;

                try {
                    // Playlist erstellen
                    const playlistResponse = await fetch('/api/playlists', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': document.querySelector('[name="csrf_token"]').value
                        },
                        body: JSON.stringify(this.formData)
                    });

                    const playlistData = await playlistResponse.json();

                    if (!playlistResponse.ok || !playlistData.success) {
                        throw new Error(playlistData.message || 'Fehler beim Erstellen der Playlist');
                    }

                    const playlistId = playlistData.data.id;

                    // Stopps hinzufügen
                    for (let i = 0; i < this.stops.length; i++) {
                        const stop = this.stops[i];
                        const stopResponse = await fetch(`/api/playlists/${playlistId}/stops`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': document.querySelector('[name="csrf_token"]').value
                            },
                            body: JSON.stringify({
                                address: stop.address,
                                work_duration: stop.work_duration,
                                notes: stop.notes,
                                position: i + 1
                            })
                        });

                        const stopData = await stopResponse.json();
                        
                        if (!stopResponse.ok || !stopData.success) {
                            throw new Error(stopData.message || 'Fehler beim Hinzufügen eines Stopps');
                        }
                    }

                    // Erfolgreich - zur Übersicht
                    window.location.href = '/playlists';

                } catch (error) {
                    alert(error.message || '<?= htmlspecialchars($t('messages.error.general')) ?>');
                } finally {
                    this.loading = false;
                }
            }
        }
    }
    </script>
</body>
</html>