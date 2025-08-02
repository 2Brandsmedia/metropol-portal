# CGI-based PHP execution
Options +ExecCGI
AddHandler cgi-script .cgi

# Route PHP through CGI wrapper
RewriteEngine On
RewriteCond %{REQUEST_URI} \.php$
RewriteRule ^(.*)\.php$ /php.cgi?script=$1.php [L,QSA]

# Fallback to HTML
DirectoryIndex index.html