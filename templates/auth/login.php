<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'de') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t('auth.login')) ?> - Metropol Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.css" />
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Logo/Header -->
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    <?= htmlspecialchars($t('app.name')) ?>
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    <?= htmlspecialchars($t('app.tagline')) ?>
                </p>
            </div>

            <!-- Login Form -->
            <form class="mt-8 space-y-6" id="loginForm" x-data="loginForm()">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <!-- Error Messages -->
                <div x-show="error" x-cloak class="rounded-md bg-red-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800" x-text="error"></h3>
                        </div>
                    </div>
                </div>

                <div class="rounded-md shadow-sm -space-y-px">
                    <!-- Email -->
                    <div>
                        <label for="email" class="sr-only"><?= htmlspecialchars($t('auth.email')) ?></label>
                        <input 
                            id="email" 
                            name="email" 
                            type="email" 
                            autocomplete="email" 
                            required 
                            x-model="formData.email"
                            class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                            placeholder="<?= htmlspecialchars($t('auth.email')) ?>"
                        >
                    </div>
                    
                    <!-- Password -->
                    <div>
                        <label for="password" class="sr-only"><?= htmlspecialchars($t('auth.password')) ?></label>
                        <input 
                            id="password" 
                            name="password" 
                            type="password" 
                            autocomplete="current-password" 
                            required 
                            x-model="formData.password"
                            class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                            placeholder="<?= htmlspecialchars($t('auth.password')) ?>"
                        >
                    </div>
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input 
                            id="remember-me" 
                            name="remember-me" 
                            type="checkbox" 
                            x-model="formData.remember_me"
                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                        >
                        <label for="remember-me" class="ml-2 block text-sm text-gray-900">
                            <?= htmlspecialchars($t('auth.remember_me')) ?>
                        </label>
                    </div>

                    <div class="text-sm">
                        <a href="/forgot-password" class="font-medium text-indigo-600 hover:text-indigo-500">
                            <?= htmlspecialchars($t('auth.forgot_password')) ?>
                        </a>
                    </div>
                </div>

                <!-- Submit Button -->
                <div>
                    <button 
                        type="submit" 
                        :disabled="loading"
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-indigo-500 group-hover:text-indigo-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <span x-show="!loading"><?= htmlspecialchars($t('auth.login_button')) ?></span>
                        <span x-show="loading" x-cloak>
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>

            <!-- Language Switcher -->
            <div class="text-center">
                <div class="inline-flex rounded-md shadow-sm" role="group">
                    <a href="?lang=de" class="px-4 py-2 text-sm font-medium <?= $lang === 'de' ? 'text-white bg-indigo-600' : 'text-gray-900 bg-white' ?> border border-gray-200 rounded-l-lg hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-2 focus:ring-blue-700 focus:text-blue-700">
                        DE
                    </a>
                    <a href="?lang=en" class="px-4 py-2 text-sm font-medium <?= $lang === 'en' ? 'text-white bg-indigo-600' : 'text-gray-900 bg-white' ?> border-t border-b border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-2 focus:ring-blue-700 focus:text-blue-700">
                        EN
                    </a>
                    <a href="?lang=tr" class="px-4 py-2 text-sm font-medium <?= $lang === 'tr' ? 'text-white bg-indigo-600' : 'text-gray-900 bg-white' ?> border border-gray-200 rounded-r-md hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-2 focus:ring-blue-700 focus:text-blue-700">
                        TR
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        function loginForm() {
            return {
                formData: {
                    email: '',
                    password: '',
                    remember_me: false
                },
                error: '',
                loading: false,

                async submitForm(event) {
                    event.preventDefault();
                    this.error = '';
                    this.loading = true;

                    try {
                        const response = await fetch('/api/auth/login', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': document.querySelector('[name="csrf_token"]').value
                            },
                            body: JSON.stringify(this.formData)
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            // Token im localStorage speichern
                            if (data.token) {
                                localStorage.setItem('auth_token', data.token);
                            }
                            
                            // Zur Dashboard-Seite weiterleiten
                            window.location.href = '/dashboard';
                        } else {
                            this.error = data.message || '<?= htmlspecialchars($t('auth.invalid_credentials')) ?>';
                        }
                    } catch (error) {
                        this.error = '<?= htmlspecialchars($t('messages.error.network')) ?>';
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }

        // Form Submit Event
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            this.__x.$data.submitForm(e);
        });
    </script>
</body>
</html>