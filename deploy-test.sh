#!/bin/bash
# Schnelles Deploy nur für test.php

echo "Deploying test.php to All-Inkl..."

lftp -c "
  set ftp:ssl-allow no
  set ftp:passive-mode yes
  open ftp://w019e3c7:$FTP_PASS@w019e3c7.kasserver.com
  put -O /www/htdocs/w019e3c7/firmenpro.de/ test.php
  bye
"

echo "Done!"