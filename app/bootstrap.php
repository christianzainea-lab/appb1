<?php
declare(strict_types=1);

define('APP_DIR', __DIR__);
define('PUBLIC_DIR', dirname(__DIR__));
define('STORAGE_DIR', PUBLIC_DIR . '/storage');

date_default_timezone_set('Europe/Bucharest');
mb_internal_encoding('UTF-8');

require APP_DIR . '/lib/core.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/mail.php';
require APP_DIR . '/lib/domain.php';
require APP_DIR . '/lib/songs.php';
require APP_DIR . '/lib/planning.php';
require APP_DIR . '/lib/activity.php';
require APP_DIR . '/lib/order.php';
require APP_DIR . '/lib/import.php';
ensure_storage();

$configFile = APP_DIR . '/config.php';
if (!is_file($configFile)) {
    header('Location: ' . url('install.php'));
    exit;
}
$CONFIG = require $configFile;

if (!cfg('debug')) {
    ini_set('display_errors', '0');
}
set_exception_handler(function (Throwable $e) {
    log_error('app', get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (cfg('debug')) { echo '<pre>' . e((string)$e) . '</pre>'; return; }
    echo '<!doctype html><meta charset="utf-8"><title>Eroare</title><body style="font-family:system-ui;padding:40px;color:#14171F"><h1>Ceva n-a mers</h1><p>Încearcă din nou peste câteva momente. Dacă problema rămâne, anunță administratorul.</p></body>';
});

// Aplică automat migrațiile noi după ce se încarcă o versiune nouă a aplicației.
if (migrations_pending(db())) {
    migrate(db());
}
