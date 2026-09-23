<?php
// Copy this file to config.php on the server and fill in real values.
// config.php is gitignored and must never be committed.

define('DB_HOST', 'REPLACE_ME');
define('DB_PORT', 3306);
define('DB_NAME', 'REPLACE_ME');
define('DB_USER', 'REPLACE_ME');
define('DB_PASS', 'REPLACE_ME');

define('API_TOKEN', 'REPLACE_ME');

// Admin felület jelszava (hash). Generáld le helyben:
// php -r "echo password_hash('SAJAT_JELSZO', PASSWORD_DEFAULT);"
define('ADMIN_PASSWORD_HASH', 'REPLACE_ME');
