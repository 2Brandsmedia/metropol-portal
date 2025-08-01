<?php
/**
 * Globaler Header
 * 
 * @author 2Brands Media GmbH
 */

// Container holen (wenn nicht bereits vorhanden)
$container = $container ?? \App\Core\Application::getInstance()->getContainer();
$i18n = $container->get('i18n');
$auth = $container->get('auth');
$ui = $container->get('ui');

// Aktuelle Seite für Navigation-Highlighting
$currentPage = $_SERVER['REQUEST_URI'] ?? '/';
?>

<header class="bg-white shadow-sm" x-data="{ mobileMenuOpen: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo/Brand -->
            <div class="flex items-center">
                <a href="/" class="flex items-center">
                    <span class="text-2xl font-bold text-indigo-600">
                        <?= htmlspecialchars($i18n->t('app.name')) ?>
                    </span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <nav class="hidden md:flex items-center space-x-8">
                <?php if ($auth->check()): ?>
                    <a href="/dashboard" class="<?= strpos($currentPage, '/dashboard') === 0 ? 'text-indigo-600' : 'text-gray-700 hover:text-indigo-600' ?> font-medium">
                        <?= htmlspecialchars($i18n->t('nav.dashboard')) ?>
                    </a>
                    <a href="/playlists" class="<?= strpos($currentPage, '/playlists') === 0 ? 'text-indigo-600' : 'text-gray-700 hover:text-indigo-600' ?> font-medium">
                        <?= htmlspecialchars($i18n->t('nav.playlists')) ?>
                    </a>
                    <a href="/tasks" class="<?= strpos($currentPage, '/tasks') === 0 ? 'text-indigo-600' : 'text-gray-700 hover:text-indigo-600' ?> font-medium">
                        <?= htmlspecialchars($i18n->t('nav.tasks')) ?>
                    </a>
                    <?php if ($auth->isAdmin()): ?>
                        <a href="/admin" class="<?= strpos($currentPage, '/admin') === 0 ? 'text-indigo-600' : 'text-gray-700 hover:text-indigo-600' ?> font-medium">
                            <?= htmlspecialchars($i18n->t('nav.admin')) ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>

            <!-- Right Side: User Menu + Language Switcher -->
            <div class="hidden md:flex items-center space-x-4">
                <!-- Language Switcher -->
                <?php 
                $languageSwitcherOptions = [
                    'style' => 'buttons',
                    'position' => 'header',
                    'showNames' => true,
                    'showFlags' => false
                ];
                include __DIR__ . '/../components/language-switcher.php';
                ?>

                <!-- User Menu -->
                <?php if ($auth->check()): ?>
                    <div class="relative" x-data="{ userMenuOpen: false }">
                        <button @click="userMenuOpen = !userMenuOpen" class="flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <span class="sr-only"><?= htmlspecialchars($i18n->t('ui.user_menu')) ?></span>
                            <div class="h-8 w-8 rounded-full bg-indigo-600 flex items-center justify-center text-white font-semibold">
                                <?= strtoupper(substr($auth->user()['name'] ?? '', 0, 1)) ?>
                            </div>
                        </button>

                        <div x-show="userMenuOpen" 
                             @click.away="userMenuOpen = false"
                             x-transition
                             class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 z-50">
                            <div class="px-4 py-2 text-xs text-gray-500 border-b">
                                <?= htmlspecialchars($auth->user()['name'] ?? '') ?>
                            </div>
                            <a href="/profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <?= htmlspecialchars($i18n->t('nav.profile')) ?>
                            </a>
                            <a href="/settings" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <?= htmlspecialchars($i18n->t('nav.settings')) ?>
                            </a>
                            <hr class="my-1">
                            <form method="POST" action="/api/auth/logout" class="px-4 py-2">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($auth->getCsrfToken()) ?>">
                                <button type="submit" class="text-left w-full text-sm text-gray-700 hover:bg-gray-100">
                                    <?= htmlspecialchars($i18n->t('auth.logout')) ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/login" class="text-gray-700 hover:text-indigo-600 font-medium">
                        <?= htmlspecialchars($i18n->t('auth.login')) ?>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Button -->
            <div class="md:hidden">
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500">
                    <span class="sr-only"><?= htmlspecialchars($i18n->t('ui.menu')) ?></span>
                    <svg x-show="!mobileMenuOpen" class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="mobileMenuOpen" class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div x-show="mobileMenuOpen" x-transition class="md:hidden">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
            <?php if ($auth->check()): ?>
                <a href="/dashboard" class="<?= strpos($currentPage, '/dashboard') === 0 ? 'bg-indigo-50 border-indigo-500 text-indigo-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800' ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    <?= htmlspecialchars($i18n->t('nav.dashboard')) ?>
                </a>
                <a href="/playlists" class="<?= strpos($currentPage, '/playlists') === 0 ? 'bg-indigo-50 border-indigo-500 text-indigo-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800' ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    <?= htmlspecialchars($i18n->t('nav.playlists')) ?>
                </a>
                <a href="/tasks" class="<?= strpos($currentPage, '/tasks') === 0 ? 'bg-indigo-50 border-indigo-500 text-indigo-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800' ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    <?= htmlspecialchars($i18n->t('nav.tasks')) ?>
                </a>
                <?php if ($auth->isAdmin()): ?>
                    <a href="/admin" class="<?= strpos($currentPage, '/admin') === 0 ? 'bg-indigo-50 border-indigo-500 text-indigo-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800' ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                        <?= htmlspecialchars($i18n->t('nav.admin')) ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Mobile Language Switcher -->
        <div class="border-t border-gray-200 pt-4 pb-3">
            <div class="px-4">
                <div class="text-xs font-medium text-gray-500 mb-2">
                    <?= htmlspecialchars($i18n->t('ui.language')) ?>
                </div>
                <?php 
                $languageSwitcherOptions = [
                    'style' => 'buttons',
                    'position' => 'inline',
                    'showNames' => true,
                    'showFlags' => false,
                    'class' => 'w-full'
                ];
                include __DIR__ . '/../components/language-switcher.php';
                ?>
            </div>
        </div>

        <!-- Mobile User Section -->
        <?php if ($auth->check()): ?>
            <div class="border-t border-gray-200 pt-4 pb-3">
                <div class="flex items-center px-4">
                    <div class="flex-shrink-0">
                        <div class="h-10 w-10 rounded-full bg-indigo-600 flex items-center justify-center text-white font-semibold">
                            <?= strtoupper(substr($auth->user()['name'] ?? '', 0, 1)) ?>
                        </div>
                    </div>
                    <div class="ml-3">
                        <div class="text-base font-medium text-gray-800"><?= htmlspecialchars($auth->user()['name'] ?? '') ?></div>
                        <div class="text-sm font-medium text-gray-500"><?= htmlspecialchars($auth->user()['email'] ?? '') ?></div>
                    </div>
                </div>
                <div class="mt-3 space-y-1">
                    <a href="/profile" class="block px-4 py-2 text-base font-medium text-gray-500 hover:text-gray-800 hover:bg-gray-100">
                        <?= htmlspecialchars($i18n->t('nav.profile')) ?>
                    </a>
                    <a href="/settings" class="block px-4 py-2 text-base font-medium text-gray-500 hover:text-gray-800 hover:bg-gray-100">
                        <?= htmlspecialchars($i18n->t('nav.settings')) ?>
                    </a>
                    <form method="POST" action="/api/auth/logout">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($auth->getCsrfToken()) ?>">
                        <button type="submit" class="block w-full text-left px-4 py-2 text-base font-medium text-gray-500 hover:text-gray-800 hover:bg-gray-100">
                            <?= htmlspecialchars($i18n->t('auth.logout')) ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="border-t border-gray-200 pt-4 pb-3">
                <div class="px-4">
                    <a href="/login" class="block w-full px-4 py-2 text-center text-base font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-md">
                        <?= htmlspecialchars($i18n->t('auth.login')) ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</header>