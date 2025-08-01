#!/bin/bash
# Script zum Kopieren der funktionierenden test.php

# Verbindung zum Server und Kopieren
sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no ssh-w019e3c7@w019e3c7.kasserver.com << 'EOF'
cd /www/htdocs/w019e3c7/firmenpro.de/

# test.php kopieren und Inhalt anpassen
if [ -f test.php ]; then
    echo "Kopiere test.php zu test-copy.php"
    cp test.php test-copy.php
    
    # Inhalt von test-copy.php anpassen
    echo '<?php echo "Test-Copy funktioniert!"; ?>' > test-copy.php
    
    # Berechtigungen prüfen
    echo "Berechtigungen von test.php:"
    ls -la test.php
    
    echo "Berechtigungen von test-copy.php:"
    ls -la test-copy.php
    
    # Erweiterte Attribute prüfen
    echo "Erweiterte Attribute von test.php:"
    lsattr test.php 2>/dev/null || echo "lsattr nicht verfügbar"
else
    echo "test.php nicht gefunden!"
fi
EOF