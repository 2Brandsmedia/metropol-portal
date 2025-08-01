<?php

return [
    // Adımlar
    'steps' => [
        'language' => 'Dil Seçimi',
        'requirements' => 'Sistem Gereksinimleri',
        'database' => 'Veritabanı',
        'admin' => 'Yönetici',
        'config' => 'Yapılandırma',
        'install' => 'Kurulum'
    ],
    
    // Butonlar
    'next' => 'İleri',
    'back' => 'Geri',
    'test_connection' => 'Bağlantıyı Test Et',
    'install_now' => 'Şimdi Kur',
    'go_to_app' => 'Uygulamaya Git',
    
    // Adım 1 - Dil
    'welcome' => 'Metropol Portal Kurulumuna Hoş Geldiniz',
    'select_language' => 'Lütfen tercih ettiğiniz dili seçin:',
    
    // Adım 2 - Gereksinimler
    'checking_requirements' => 'Sistem gereksinimleri kontrol ediliyor...',
    'all_requirements_met' => 'Tüm sistem gereksinimleri karşılandı!',
    'requirements_not_met' => 'Bazı sistem gereksinimleri karşılanmadı.',
    'please_fix_errors' => 'Lütfen devam etmeden önce hataları düzeltin.',
    
    // Adım 3 - Veritabanı
    'database_configuration' => 'Veritabanı Yapılandırması',
    'database_host' => 'Veritabanı Sunucusu',
    'database_port' => 'Port',
    'database_name' => 'Veritabanı Adı',
    'database_user' => 'Kullanıcı Adı',
    'database_pass' => 'Şifre',
    'database_help' => 'Bu bilgileri hosting sağlayıcınızdan alabilirsiniz.',
    
    // Adım 4 - Yönetici
    'create_admin' => 'Yönetici Hesabı Oluştur',
    'admin_username' => 'Kullanıcı Adı',
    'admin_email' => 'E-posta Adresi',
    'admin_password' => 'Şifre',
    'admin_password_confirm' => 'Şifreyi Onayla',
    'password_requirements' => 'En az 8 karakter, büyük/küçük harf ve rakam',
    
    // Adım 5 - Yapılandırma
    'basic_configuration' => 'Temel Yapılandırma',
    'site_name' => 'Site Adı',
    'timezone' => 'Zaman Dilimi',
    'api_configuration' => 'API Yapılandırması (isteğe bağlı)',
    'google_maps_key' => 'Google Maps API Anahtarı',
    'ors_api_key' => 'OpenRouteService API Anahtarı',
    'api_keys_optional' => 'API anahtarları daha sonra yapılandırılabilir.',
    
    // Adım 6 - Kurulum
    'ready_to_install' => 'Kuruluma Hazır',
    'installation_summary' => 'Kurulum Özeti',
    'installing' => 'Kuruluyor...',
    'installation_complete' => 'Kurulum Tamamlandı!',
    'installation_success_message' => 'Metropol Portal başarıyla kuruldu.',
    'important_security' => 'Önemli: Güvenlik nedeniyle kurulum devre dışı bırakıldı.',
    
    // Hatalar
    'error_database_connection' => 'Veritabanı bağlantısı başarısız',
    'error_database_exists' => 'Veritabanı mevcut değil',
    'error_file_permissions' => 'Yazma izni yok',
    'error_php_version' => 'PHP sürümü çok eski',
    'error_missing_extension' => 'PHP eklentisi eksik',
    
    // Başarı
    'success_connection' => 'Bağlantı başarılı!',
    'success_requirements' => 'Tüm gereksinimler karşılandı',
    'success_installation' => 'Kurulum tamamlandı'
];