<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require APP_DIR . '/controllers/auth.php';
require APP_DIR . '/controllers/pages.php';
require APP_DIR . '/controllers/people.php';
require APP_DIR . '/controllers/settings.php';
require APP_DIR . '/controllers/songs.php';
require APP_DIR . '/controllers/planning.php';
require APP_DIR . '/controllers/templates.php';
require APP_DIR . '/controllers/debuglog.php';

start_session();
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = base_path();
if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
$path = trim(rawurldecode($path), '/');
if ($path === 'index.php') $path = '';
if (isset($_GET['r']) && is_string($_GET['r'])) $path = trim($_GET['r'], '/'); // dacă lipsește mod_rewrite

$routes = [
    '' => 'page_home',
    'login' => 'auth_login',
    'logout' => 'auth_logout',
    'inscriere/{t}' => 'auth_register',
    'confirmare' => 'auth_verify_notice',
    'confirma/{t}' => 'auth_verify',
    'asteptare' => 'auth_pending',
    'parola-uitata' => 'auth_forgot',
    'parola-noua/{t}' => 'auth_reset',
    'profil' => 'page_profile',
    'poza/{t}' => 'page_avatar',
    'cantari' => 'songs_index',
    'cantari/noua' => 'song_new',
    'cantare/{t}' => 'song_show',
    'cantare/{t}/editeaza' => 'song_edit',
    'media/{t}' => 'media_serve',
    'acorduri/{t}' => 'chords_serve',
    'indisponibil' => 'blockouts_page',
    'planificare' => 'planning_index',
    'planificare/luna' => 'planning_month',
    'planificare/nou' => 'event_new',
    'planificare/eveniment/{t}' => 'event_edit',
    'program/{t}' => 'program_show',
    'fisier/{t}' => 'event_file_serve',
    'f/{t}' => 'event_file_public',
    'setari/fisiere' => 'settings_files',
    'calendar/{t}' => 'calendar_feed',
    'raspuns' => 'respond',
    'editare' => 'page_edit_hub',
    'meniu' => 'page_menu',
    'ghid' => 'page_guide',
    'debuglog' => 'debuglog',
    'setari' => 'settings_index',
    'setari/persoane' => 'people_index',
    'setari/pozitii' => 'settings_positions',
    'setari/whatsapp' => 'settings_whatsapp',
    'setari/sabloane' => 'settings_templates',
    'setari/sabloane/{t}' => 'template_edit',
    'setari/etichete' => 'settings_labels',
    'setari/tonuri' => 'settings_keys',
    'setari/biserica' => 'settings_church',
    'setari/carti' => 'settings_books',
    'cantari/import' => 'songs_import',
    'cantari/import/model.{t}' => 'songs_import_template',
    'logo' => 'brand_logo_serve',
    'logo/{t}' => 'brand_logo_serve',
];

foreach ($routes as $pattern => $handler) {
    $re = '#^' . preg_replace('#\{t\}#', '([A-Za-z0-9_\-\.]+)', $pattern) . '$#';
    if (preg_match($re, $path, $m)) {
        array_shift($m);
        $handler(...$m);
        exit;
    }
}
not_found();
