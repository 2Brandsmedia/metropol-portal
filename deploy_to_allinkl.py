#!/usr/bin/env python3

import ftplib
import os
from pathlib import Path
import time

print("===============================================")
print("Metropol Portal - Automatisches Deployment")
print("===============================================\n")

# FTP-Konfiguration
FTP_CONFIG = {
    'host': 'w019e3c7.kasserver.com',
    'user': 'w019e3c7',
    'password': '2Brands2025!',
    'root': '/w019e3c7/firmenpro.de'
}

# MySQL-Konfiguration
DB_CONFIG = {
    'host': 'localhost',
    'database': 'd0446399',
    'user': 'd0446399',
    'password': '2Brands2025!'
}

# Projekt-Pfade
LOCAL_ROOT = Path(__file__).parent
REMOTE_ROOT = FTP_CONFIG['root']

# Verzeichnisse zum Erstellen
DIRECTORIES = ['cache', 'logs', 'uploads', 'temp', 'backups']

# Verzeichnisse zum Hochladen
UPLOAD_DIRS = ['src', 'public', 'config', 'database', 'templates', 'lang', 'routes', 'scripts']

# Dateien die NICHT hochgeladen werden
EXCLUDE_PATTERNS = {
    '.git', '.gitignore', '.github', '.DS_Store', 'node_modules',
    'tests', 'docs', '*.log', '*.md', 'deploy_to_allinkl.py',
    'deploy-to-allinkl.php', 'tsconfig.json', 'package.json',
    'package-lock.json', 'composer.lock', 'phpunit.xml',
    'phpcs.xml', 'phpstan.neon', '__pycache__', '*.pyc'
}

def should_exclude(filename):
    """Prüft ob Datei ausgeschlossen werden soll"""
    for pattern in EXCLUDE_PATTERNS:
        if pattern.startswith('*.'):
            if filename.endswith(pattern[1:]):
                return True
        elif filename == pattern:
            return True
    return False

def upload_file(ftp, local_path, remote_path):
    """Einzelne Datei hochladen"""
    try:
        with open(local_path, 'rb') as file:
            ftp.storbinary(f'STOR {remote_path}', file)
        print(f"✓ Uploaded: {remote_path}")
    except Exception as e:
        print(f"✗ Failed: {remote_path} - {str(e)}")

def create_directory(ftp, path):
    """Verzeichnis erstellen wenn nicht vorhanden"""
    try:
        ftp.mkd(path)
        print(f"✓ Created directory: {path}")
    except ftplib.error_perm as e:
        if "File exists" not in str(e):
            # Versuche parent directory zu erstellen
            parent = os.path.dirname(path)
            if parent and parent != '/':
                create_directory(ftp, parent)
                try:
                    ftp.mkd(path)
                    print(f"✓ Created directory: {path}")
                except:
                    pass

def upload_directory(ftp, local_dir, remote_dir):
    """Verzeichnis rekursiv hochladen"""
    create_directory(ftp, remote_dir)
    
    for item in os.listdir(local_dir):
        if should_exclude(item):
            continue
            
        local_path = os.path.join(local_dir, item)
        remote_path = f"{remote_dir}/{item}"
        
        if os.path.isdir(local_path):
            upload_directory(ftp, local_path, remote_path)
        else:
            upload_file(ftp, local_path, remote_path)

def main():
    # Phase 1: FTP-Verbindung und Upload
    print("Phase 1: FTP-Upload")
    print("-" * 40)
    
    try:
        # FTP-Verbindung
        ftp = ftplib.FTP(FTP_CONFIG['host'])
        ftp.login(FTP_CONFIG['user'], FTP_CONFIG['password'])
        print(f"✓ Connected to {FTP_CONFIG['host']}")
        
        # Basis-Verzeichnisse erstellen
        print("\nCreating base directories...")
        # Erst sicherstellen dass Root existiert
        create_directory(ftp, REMOTE_ROOT)
        
        # Dann Unterverzeichnisse
        for directory in DIRECTORIES:
            create_directory(ftp, f"{REMOTE_ROOT}/{directory}")
        
        # Projekt-Dateien hochladen
        print("\nUploading project files...")
        for directory in UPLOAD_DIRS:
            local_dir = LOCAL_ROOT / directory
            if local_dir.exists():
                print(f"\nUploading /{directory}...")
                upload_directory(ftp, str(local_dir), f"{REMOTE_ROOT}/{directory}")
        
        # composer.json hochladen
        composer_file = LOCAL_ROOT / 'composer.json'
        if composer_file.exists():
            upload_file(ftp, str(composer_file), f"{REMOTE_ROOT}/composer.json")
        
        # Berechtigungen setzen (soweit möglich via FTP)
        try:
            for directory in DIRECTORIES:
                ftp.voidcmd(f"SITE CHMOD 755 {REMOTE_ROOT}/{directory}")
        except:
            print("Note: Could not set permissions via FTP")
        
        ftp.quit()
        print("\n✓ FTP upload completed!")
        
    except Exception as e:
        print(f"\n✗ FTP Error: {str(e)}")
        return
    
    # Phase 2: Datenbank-Setup
    print("\n\nPhase 2: Database Setup")
    print("-" * 40)
    
    sql_file = LOCAL_ROOT / 'database' / 'migrations' / 'create_all_tables.sql'
    if not sql_file.exists():
        print("✗ SQL file not found!")
        return
    
    try:
        # MySQL-Verbindung über SSH-Tunnel wäre nötig
        # Da wir keinen direkten Zugriff haben, erstellen wir ein PHP-Setup-Skript
        setup_script = """<?php
// Temporäres Setup-Skript - NACH AUSFÜHRUNG LÖSCHEN!
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = [
    'host' => 'localhost',
    'name' => 'd0446399',
    'user' => 'd0446399',
    'password' => '2Brands2025!'
];

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['name']};charset=utf8mb4",
        $config['user'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // SQL-Datei ausführen
    $sql = file_get_contents(__DIR__ . '/database/migrations/create_all_tables.sql');
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    // Admin-Passwort setzen
    $adminPassword = password_hash('Admin2025!', PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->exec("UPDATE users SET password = '$adminPassword' WHERE email = 'admin@firmenpro.de'");
    
    echo "✓ Database setup completed!<br>";
    echo "✓ Admin user: admin@firmenpro.de<br>";
    echo "✓ Password: Admin2025!<br>";
    echo "<br><strong>WICHTIG: Löschen Sie diese Datei sofort!</strong>";
    
} catch (Exception $e) {
    echo "✗ Database Error: " . $e->getMessage();
}
?>"""
        
        # Setup-Skript erstellen
        setup_file = LOCAL_ROOT / 'public' / 'SETUP_DATABASE.php'
        with open(setup_file, 'w') as f:
            f.write(setup_script)
        
        # Setup-Skript hochladen
        print("Uploading database setup script...")
        ftp = ftplib.FTP(FTP_CONFIG['host'])
        ftp.login(FTP_CONFIG['user'], FTP_CONFIG['password'])
        upload_file(ftp, str(setup_file), f"{REMOTE_ROOT}/public/SETUP_DATABASE.php")
        ftp.quit()
        
        # Lokale Setup-Datei löschen
        os.remove(setup_file)
        
        print("\n✓ Database setup script uploaded!")
        print("\nWICHTIG: Öffnen Sie jetzt:")
        print("https://firmenpro.de/SETUP_DATABASE.php")
        print("\nund LÖSCHEN Sie die Datei danach sofort!")
        
    except Exception as e:
        print(f"\n✗ Database Error: {str(e)}")
    
    # Phase 3: Abschlussbericht
    print("\n\nPhase 3: Deployment Summary")
    print("-" * 40)
    print("✓ Files uploaded to All-Inkl server")
    print("✓ Directory structure created")
    print("✓ Database setup script ready")
    print("\nNext steps:")
    print("1. Open https://firmenpro.de/SETUP_DATABASE.php")
    print("2. DELETE the setup file immediately after!")
    print("3. Login with admin@firmenpro.de / Admin2025!")
    print("4. Change admin password")
    print("5. Configure cron jobs in All-Inkl KAS")
    print("\nDeployment preparation completed!")

if __name__ == "__main__":
    main()