<?php
declare(strict_types=1);

const APP_VERSION = '1.5.0';
const DEFAULT_APP_NAME = 'Echipa de voluntari';

function cfg(string $key, $default = null) {
    global $CONFIG;
    return $CONFIG[$key] ?? $default;
}

/** Uneltele de test (conturi false) sunt oprite în pachet; pornesc doar dacă config.php cere explicit (testele locale). */
function test_tools_enabled(): bool {
    return cfg('test_tools') === true;
}

/** Numele aplicației (Setări → Biserică). */
function app_name(): string {
    return (string)(setting('app_name') ?: DEFAULT_APP_NAME);
}

/* ---------- logo (Setări → Biserică) ---------- */

function brand_dir(): string { return STORAGE_DIR . '/brand'; }
function brand_file(string $name): ?string {
    $f = brand_dir() . '/' . $name;
    return is_file($f) ? $f : null;
}
function logo_url(): string {
    $f = brand_file('logo.png');
    return $f ? url('logo') . '?v=' . filemtime($f) : asset('logo.png');
}
function favicon_url(bool $dark = false): string {
    $f = brand_file('favicon.png');
    if ($f) return url('logo/favicon') . '?v=' . filemtime($f);
    return asset($dark ? 'favicon-dark.png' : 'favicon.png');
}

/** Dată reală în format AAAA-LL-ZZ (refuză 30 februarie). */
function valid_date(string $d): bool {
    return (bool)preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}
/** Oră reală HH:MM (00:00–23:59). */
function valid_time(string $t): bool {
    return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
}

function now(): string { return date('Y-m-d H:i:s'); }

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = db_connect(cfg('db'));
    }
    return $pdo;
}

function db_connect(array $c): PDO {
    if (($c['driver'] ?? 'mysql') === 'sqlite') { // doar pentru teste locale
        $pdo = new PDO('sqlite:' . $c['path']);
    } else {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int)($c['port'] ?? 3306), $c['name']);
        $pdo = new PDO($dsn, $c['user'], $c['pass']);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function row(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r === false ? null : $r; }
function rows(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function val(string $sql, array $params = []) { $v = q($sql, $params)->fetchColumn(); return $v === false ? null : $v; }

function setting(string $k, ?string $default = null): ?string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (rows('SELECT k, v FROM settings') as $r) $cache[$r['k']] = $r['v'];
    }
    return $cache[$k] ?? $default;
}
function setting_set(string $k, string $v): void {
    if (val('SELECT COUNT(*) FROM settings WHERE k = ?', [$k])) q('UPDATE settings SET v = ? WHERE k = ?', [$v, $k]);
    else q('INSERT INTO settings (k, v) VALUES (?, ?)', [$k, $v]);
}

/* ---------- migrații ---------- */

function sql_for_driver(string $sql, PDO $pdo): string {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') return $sql;
    $sql = preg_replace('/\)\s*ENGINE=[^;]*;/', ');', $sql);
    $sql = str_replace(['INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'], 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/,\s*UNIQUE KEY \w+ \(([^)]+)\)/', ', UNIQUE ($1)', $sql);
    $sql = preg_replace('/,\s*KEY \w+ \([^)]+\)/', '', $sql);
    return $sql;
}

function run_sql_file(PDO $pdo, string $file): void {
    $sql = sql_for_driver((string)file_get_contents($file), $pdo);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            // dacă o migrație a fost întreruptă la jumătate, pașii deja făcuți se sar la reluare
            $code = (int)($e->errorInfo[1] ?? 0);
            $msg = $e->getMessage();
            if (in_array($code, [1050, 1060, 1061], true) || stripos($msg, 'already exists') !== false || stripos($msg, 'duplicate column') !== false) {
                log_error('migrate', basename($file) . ': sărit (deja făcut) – ' . $msg);
                continue;
            }
            throw $e;
        }
    }
}

function migrate(PDO $pdo): array {
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(120) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL)');
    $files = glob(APP_DIR . '/migrations/*.{sql,php}', GLOB_BRACE) ?: [];
    sort($files);
    $done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $applied = [];
    if (!array_diff(array_map('basename', $files), $done)) return $applied;
    // o singură cerere rulează migrațiile; celelalte așteaptă și apoi văd că sunt gata
    $lock = @fopen((defined('STORAGE_DIR') ? STORAGE_DIR : sys_get_temp_dir()) . '/migrate.lock', 'c');
    if ($lock) flock($lock, LOCK_EX);
    $done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($files as $f) {
        $name = basename($f);
        if (in_array($name, $done, true)) continue;
        if (time() - (int)@filemtime($f) < 20) break; // fișierul abia a fost încărcat (poate încă se copiază): se rulează la cererea următoare
        if (str_ends_with($name, '.sql')) {
            run_sql_file($pdo, $f);
        } else {
            (static function (PDO $pdo, string $f) { require $f; })($pdo, $f);
        }
        $pdo->prepare('INSERT INTO schema_migrations (name, applied_at) VALUES (?, ?)')->execute([$name, now()]);
        $applied[] = $name;
    }
    if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
    return $applied;
}

function migrations_pending(PDO $pdo): bool {
    try {
        $done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return true;
    }
    $files = array_map('basename', glob(APP_DIR . '/migrations/*.{sql,php}', GLOB_BRACE) ?: []);
    return (bool)array_diff($files, $done);
}

/* ---------- utilitare ---------- */

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function base_path(): string {
    static $b = null;
    if ($b === null) {
        $b = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    }
    return $b;
}
function url(string $path = ''): string { return base_path() . '/' . ltrim($path, '/'); }
function full_url(string $path = ''): string {
    $base = rtrim((string)cfg('app_url', ''), '/');
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $base = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path();
    }
    return $base . '/' . ltrim($path, '/');
}
function asset(string $path): string {
    $f = PUBLIC_DIR . '/assets/' . $path;
    return url('assets/' . $path) . (is_file($f) ? '?v=' . filemtime($f) : '');
}

function redirect(string $path): never {
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function flash(?string $msg = null, string $type = 'ok') {
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        render('auth/message', ['title' => 'Sesiune expirată', 'text' => 'Pagina a stat deschisă prea mult. Întoarce-te și încearcă din nou.'], 'auth');
        exit;
    }
}
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function post(string $k, string $default = ''): string { $v = $_POST[$k] ?? $default; return is_string($v) ? trim($v) : $default; }

function random_token(int $bytes = 24): string { return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '='); }

function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45); }

function initials(string $name): string {
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $s = '';
    foreach (array_slice($parts, 0, 2) as $p) $s .= mb_strtoupper(mb_substr($p, 0, 1));
    return $s ?: '?';
}
function first_name(string $name): string { return explode(' ', trim($name))[0] ?? $name; }

const RO_MONTHS = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
const RO_MONTHS_SHORT = ['ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'nov', 'dec'];
const RO_DAYS = ['Duminică', 'Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri', 'Sâmbătă'];

function ro_date(?string $dt, bool $withYear = false): string {
    if (!$dt) return '';
    $t = strtotime($dt);
    return date('j', $t) . ' ' . RO_MONTHS_SHORT[(int)date('n', $t) - 1] . ($withYear ? ' ' . date('Y', $t) : '');
}
function ro_month_year(?string $dt): string {
    if (!$dt) return '';
    $t = strtotime($dt);
    return RO_MONTHS[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
}
function ago(?string $dt): string {
    if (!$dt) return 'Niciodată';
    $t = strtotime($dt);
    $d = (int)floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', $t))) / 86400);
    if ($d <= 0) return 'Azi';
    if ($d === 1) return 'Ieri';
    if ($d < 7) return "Acum $d zile";
    if ($d < 14) return 'Săpt. trecută';
    return ro_date($dt, date('Y', $t) !== date('Y'));
}

function role_label(string $r): string {
    return ['admin' => 'Administrator', 'editor' => 'Editor', 'pastor' => 'Pastor', 'voluntar' => 'Voluntar'][$r] ?? $r;
}
/** Pastorul are, ca rang, drepturile unui voluntar; în plus poate lucra la ordinea serviciului (vezi can_plan). */
function role_rank(string $r): int { return ['voluntar' => 1, 'pastor' => 1, 'editor' => 2, 'admin' => 3][$r] ?? 0; }

function log_error(string $channel, string $msg): void {
    @file_put_contents(STORAGE_DIR . "/logs/$channel.log", '[' . date('c') . "] $msg\n", FILE_APPEND);
}

/* ---------- randare ---------- */

function render(string $view, array $data = [], string $layout = 'app'): void {
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_DIR . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require APP_DIR . '/views/layout_' . $layout . '.php';
}

function render_partial(string $view, array $data = []): void {
    extract($data, EXTR_SKIP);
    require APP_DIR . '/views/' . $view . '.php';
}

function not_found(): never {
    http_response_code(404);
    render('auth/message', ['title' => 'Pagina nu există', 'text' => 'Linkul nu mai este valabil sau a fost scris greșit.', 'link' => ['', 'Mergi la început']], current_user() ? 'app' : 'auth');
    exit;
}
function forbidden(): never {
    http_response_code(403);
    render('auth/message', ['title' => 'Nu ai acces aici', 'text' => 'Pagina aceasta este doar pentru editori sau administratori.', 'link' => ['', 'Mergi la început']]);
    exit;
}

/** Dosarele din storage/ nu se urcă la deploy (ca să nu fie suprascrise); pe un site nou le creăm aici. */
function ensure_storage(): void {
    foreach (['', '/logs', '/media', '/avatars', '/sessions', '/brand', '/import'] as $d) {
        if (!is_dir(STORAGE_DIR . $d)) @mkdir(STORAGE_DIR . $d, 0755, true);
    }
    $h = STORAGE_DIR . '/.htaccess';
    if (is_dir(STORAGE_DIR) && !is_file($h)) {
        @file_put_contents($h, "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    }
}
