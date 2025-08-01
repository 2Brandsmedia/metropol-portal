#!/bin/bash

# Metropol Portal - FTP Deployment Script
# Author: 2Brands Media GmbH

echo "==============================================="
echo "Metropol Portal - FTP Deployment"
echo "==============================================="
echo ""

# FTP Credentials
FTP_HOST="w019e3c7.kasserver.com"
FTP_USER="w019e3c7"
FTP_PASS="2Brands2025!"
FTP_ROOT="/w019e3c7/firmenpro.de"

# Local paths
LOCAL_ROOT="/Users/fuerte/Documents/Claude Projekte/Metropol Portal"

# Create FTP commands file
cat > ftp_commands.txt << EOF
open $FTP_HOST
user $FTP_USER $FTP_PASS
binary
passive

# Create base directory
mkdir $FTP_ROOT
cd $FTP_ROOT

# Create subdirectories
mkdir cache
mkdir logs
mkdir uploads
mkdir temp
mkdir backups
mkdir src
mkdir public
mkdir config
mkdir database
mkdir templates
mkdir lang
mkdir routes
mkdir scripts

# Create nested directories
mkdir src/Agents
mkdir src/Controllers
mkdir src/Core
mkdir src/Middleware
mkdir src/Services
mkdir src/Validators
mkdir public/js
mkdir public/assets
mkdir public/assets/css
mkdir public/assets/images
mkdir public/assets/js
mkdir database/migrations
mkdir database/seeds
mkdir templates/auth
mkdir templates/components
mkdir templates/layouts
mkdir templates/playlists
mkdir templates/monitoring
mkdir templates/i18n
mkdir templates/api-limits

# Upload config files
cd $FTP_ROOT/config
lcd "$LOCAL_ROOT/config"
put production.php
put config.example.php

# Upload public files
cd $FTP_ROOT/public
lcd "$LOCAL_ROOT/public"
put index.php
put .htaccess

cd $FTP_ROOT/public/js
lcd "$LOCAL_ROOT/public/js"
put i18n.js
put routes.js
put geocoding.js

# Upload language files
cd $FTP_ROOT/lang
lcd "$LOCAL_ROOT/lang"
put de.json
put en.json
put tr.json

# Upload routes
cd $FTP_ROOT/routes
lcd "$LOCAL_ROOT/routes"
put api.php
put web.php

# Upload database files
cd $FTP_ROOT/database/migrations
lcd "$LOCAL_ROOT/database/migrations"
put create_all_tables.sql

# Upload templates
cd $FTP_ROOT/templates
lcd "$LOCAL_ROOT/templates"
put dashboard.php

cd $FTP_ROOT/templates/auth
lcd "$LOCAL_ROOT/templates/auth"
put login.php

cd $FTP_ROOT/templates/playlists
lcd "$LOCAL_ROOT/templates/playlists"
put index.php
put create.php
put view.php

# Upload composer.json
cd $FTP_ROOT
lcd "$LOCAL_ROOT"
put composer.json

# Create setup script
cd $FTP_ROOT/public
lcd "$LOCAL_ROOT"
put SETUP_DATABASE.php

bye
EOF

echo "Starting FTP upload..."
echo "This may take several minutes..."
echo ""

# Execute FTP commands
ftp -n < ftp_commands.txt

# Clean up
rm ftp_commands.txt

echo ""
echo "==============================================="
echo "FTP Upload completed!"
echo "==============================================="
echo ""
echo "Next steps:"
echo "1. Upload PHP source files manually via FTP client"
echo "2. Open https://firmenpro.de/SETUP_DATABASE.php"
echo "3. Delete SETUP_DATABASE.php after running"
echo "4. Login with admin@firmenpro.de / Admin2025!"
echo ""