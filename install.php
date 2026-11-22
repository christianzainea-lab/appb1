<?php
declare(strict_types=1);
/**
 * Pagina de instalare a aplicației pentru echipa de voluntari.
 * Se folosește o singură dată: creează tabelele (goale), datele de pornire, numele bisericii și contul de administrator,
 * apoi scrie app/config.php. După instalare pagina se blochează singură.
 */
define('APP_DIR', __DIR__ . '/app');
define('PUBLIC_DIR', __DIR__);
define('STORAGE_DIR', __DIR__ . '/storage');
date_default_timezone_set('Europe/Bucharest');
mb_internal_encoding('UTF-8');
require APP_DIR . '/lib/core.php';
ensure_storage();

$CONFIG = [];
$configFile = APP_DIR . '/config.php';
$installed = is_file($configFile);

$checks = [
    'PHP 8.1 sau mai nou (acum ' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'Extensia pdo_mysql' => extension_loaded('pdo_mysql'),
    'Extensia gd (poze de profil)' => extension_loaded('gd'),
    'Extensia openssl (email securizat)' => extension_loaded('openssl'),
    'Extensia mbstring' => extension_loaded('mbstring'),
    'Dosarul app/ permite scrierea' => is_writable(APP_DIR),
    'Dosarul storage/ permite scrierea' => is_writable(STORAGE_DIR) && is_writable(STORAGE_DIR . '/sessions') && is_writable(STORAGE_DIR . '/avatars'),
];
$checksOk = !in_array(false, $checks, true);
// recomandat, dar nu obligatoriu: fără zip, importul de cântări merge doar din CSV
$optional = ['Extensia zip (import din Excel .xlsx)' => class_exists('ZipArchive')];

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$guessUrl = ($https ? 'https://' : 'http://') . $host . base_path();
$guessDomain = preg_replace('/^(www|echipa|voluntari|app|test)\./', '', explode(':', $host)[0]);

$v = [
    'app_url' => $guessUrl,
    'app_name' => DEFAULT_APP_NAME, 'church_name' => '',
    'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '',
    'smtp_host' => 'mail.' . $guessDomain, 'smtp_port' => '465', 'smtp_secure' => 'ssl', 'smtp_user' => 'noreply@' . $guessDomain, 'mail_from' => 'noreply@' . $guessDomain,
    'admin_name' => '', 'admin_email' => '',
];
$errors = [];
$done = false;

if (!$installed && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? ''));
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $smtpPass = (string)($_POST['smtp_pass'] ?? '');
    $adminPass = (string)($_POST['admin_pass'] ?? '');

    if (!$checksOk) $errors[] = 'Rezolvă întâi cerințele marcate cu roșu.';
    if (!preg_match('#^https?://#', $v['app_url'])) $errors[] = 'Adresa aplicației trebuie să înceapă cu https://';
    if (mb_strlen($v['church_name']) < 2) $errors[] = 'Scrie numele bisericii.';
    if ($v['app_name'] === '') $v['app_name'] = DEFAULT_APP_NAME;
    if ($v['db_name'] === '' || $v['db_user'] === '') $errors[] = 'Completează numele bazei de date și utilizatorul.';
    if (mb_strlen($v['admin_name']) < 3) $errors[] = 'Scrie numele administratorului.';
    if (!filter_var($v['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Emailul administratorului nu pare corect.';
    if (mb_strlen($adminPass) < 8) $errors[] = 'Parola administratorului trebuie să aibă cel puțin 8 caractere.';
    if ($v['mail_from'] !== '' && !filter_var($v['mail_from'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresa de expeditor nu pare corectă.';

    $dbConf = ['driver' => 'mysql', 'host' => $v['db_host'], 'port' => (int)$v['db_port'], 'name' => $v['db_name'], 'user' => $v['db_user'], 'pass' => $dbPass];
    if (!$errors) {
        try {
            $pdo = db_connect($dbConf);
            $existing = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
            if ($existing && (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
                $errors[] = 'Baza de date are deja utilizatori. Folosește o bază de date goală (sau păstrează config.php vechi).';
            }
        } catch (Throwable $e) {
            $errors[] = 'Nu mă pot conecta la baza de date: ' . $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            $CONFIG = ['db' => $dbConf];
            migrate($pdo);
            if (migrations_pending($pdo)) throw new RuntimeException('Fișierele abia au fost copiate pe server. Așteaptă 30 de secunde și apasă din nou „Instalează”.');
            $now = now();
            $pdo->prepare('INSERT INTO users (name, email, password_hash, role, status, email_verified_at, calendar_token, approved_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$v['admin_name'], mb_strtolower($v['admin_email']), password_hash($adminPass, PASSWORD_DEFAULT), 'admin', 'active', $now, random_token(24), $now, $now, $now]);

            setting_set('app_name', mb_substr($v['app_name'], 0, 60));
            setting_set('church_name', mb_substr($v['church_name'], 0, 120));
            $config = [
                'app_url' => rtrim($v['app_url'], '/'),
                'debug' => false,
                'test_tools' => false,
                'db' => $dbConf,
                'mail_from' => $v['mail_from'],
                'smtp' => $v['smtp_host'] === '' ? null : [
                    'host' => $v['smtp_host'], 'port' => (int)$v['smtp_port'], 'secure' => $v['smtp_secure'] === 'tls' ? 'tls' : 'ssl',
                    'user' => $v['smtp_user'], 'pass' => $smtpPass,
                ],
                'google' => ['client_id' => '', 'client_secret' => ''],
                'installed_at' => date('c'),
            ];
            $php = "<?php\n// Generat de pagina de instalare. Conține parole: nu-l trimite nimănui.\nreturn " . var_export($config, true) . ";\n";
            if (file_put_contents($configFile, $php, LOCK_EX) === false) throw new RuntimeException('Nu pot scrie app/config.php.');
            @chmod($configFile, 0600);
            $done = true;
        } catch (Throwable $e) {
            $errors[] = 'Instalarea s-a oprit: ' . $e->getMessage();
        }
    }
}

function f(string $name, string $label, array $v, string $type = 'text', string $hint = '', bool $req = true): string {
    $val = $type === 'password' ? '' : e($v[$name] ?? '');
    return '<label class="field">' . e($label) . ($hint ? ' <span class="hint">' . e($hint) . '</span>' : '')
        . '<input type="' . $type . '" name="' . $name . '" value="' . $val . '"' . ($req ? ' required' : '') . ($type === 'password' ? ' autocomplete="new-password"' : '') . '></label>';
}
?><!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Instalare · Echipa de voluntari</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="auth"><main class="auth-inner" style="max-width:640px">
  <div class="auth-top"><img src="assets/logo.png" alt=""></div>
<?php if ($installed && !$done): ?>
  <h1>Aplicația este deja instalată</h1>
  <p class="muted">Pagina de instalare este blocată. Ca să reinstalezi, șterge fișierul <code>app/config.php</code> din cPanel și golește baza de date.</p>
  <a class="btn primary lg block" href="./">Deschide aplicația</a>
<?php elseif ($done): ?>
  <h1>Gata!</h1>
  <div class="alert ok">Tabelele, pozițiile, cele 4 șabloane și mesajul WhatsApp au fost create, iar contul de administrator este activ.</div>
  <p class="muted">Următorii pași, din aplicație: <strong>Setări → Biserică și logo</strong> (logoul), <strong>Setări → Cărți</strong> (cărțile de cântări), apoi <strong>Cântări → Importă</strong> și <strong>Setări → Persoane și invitații</strong>.</p>
  <p class="muted small">Pentru siguranță, poți șterge acum fișierul <code>install.php</code> din cPanel (oricum s-a blocat singur).</p>
  <a class="btn primary lg block" href="./login">Intră în cont</a>
<?php else: ?>
  <div class="stack" style="gap:6px">
    <h1>Instalare aplicație</h1>
    <p class="muted small">Completează o singură dată. Datele de acces se salvează doar pe server, în <code>app/config.php</code>.</p>
  </div>
  <section class="card stack" style="gap:6px">
    <h2>Cerințe</h2>
    <?php foreach ($checks as $label => $ok): ?>
      <span class="small" style="color:<?= $ok ? 'var(--ok)' : 'var(--danger)' ?>"><?= $ok ? '✓' : '✗' ?> <?= e($label) ?></span>
    <?php endforeach; ?>
    <?php foreach ($optional as $label => $ok): ?>
      <span class="small" style="color:<?= $ok ? 'var(--ok)' : 'var(--warn-text)' ?>"><?= $ok ? '✓' : '!' ?> <?= e($label) ?><?= $ok ? '' : ' – recomandat, nu obligatoriu' ?></span>
    <?php endforeach; ?>
  </section>
  <?php if ($errors): ?><div class="alert error" role="alert"><div><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div></div><?php endif; ?>
  <form method="post" class="stack" style="gap:16px" autocomplete="off">
    <section class="card stack">
      <h2>Biserica</h2>
      <?= f('church_name', 'Numele bisericii', $v, 'text', 'apare în emailuri și pe pagina de intrare') ?>
      <?= f('app_name', 'Numele aplicației', $v, 'text', 'în bara de sus și în emailuri') ?>
      <?= f('app_url', 'Adresa aplicației', $v, 'url', 'ex. https://voluntari.biserica-ta.ro') ?>
      <span class="xs muted">Logoul se încarcă după instalare, din Setări → Biserică și logo.</span>
    </section>
    <section class="card stack">
      <h2>Baza de date MySQL</h2>
      <p class="xs muted">Din cPanel › MySQL Databases (sau „Manage My Databases”). Baza de date trebuie să fie nouă și goală. De obicei numele au forma <code>cont_nume</code>.</p>
      <div class="grid-2"><?= f('db_host', 'Server', $v) ?><?= f('db_port', 'Port', $v, 'number') ?></div>
      <?= f('db_name', 'Numele bazei de date', $v) ?>
      <div class="grid-2"><?= f('db_user', 'Utilizator', $v) ?><?= f('db_pass', 'Parolă', $v, 'password', '', false) ?></div>
    </section>
    <section class="card stack">
      <h2>Email (noreply@)</h2>
      <p class="xs muted">Din cPanel › Email Accounts › Connect Devices. Lasă „Server SMTP” gol ca să folosești funcția de email a serverului.</p>
      <div class="grid-2"><?= f('smtp_host', 'Server SMTP', $v, 'text', '', false) ?><?= f('smtp_port', 'Port', $v, 'number', '465 = SSL', false) ?></div>
      <label class="field">Securitate<select name="smtp_secure"><option value="ssl"<?= $v['smtp_secure'] === 'ssl' ? ' selected' : '' ?>>SSL (port 465)</option><option value="tls"<?= $v['smtp_secure'] === 'tls' ? ' selected' : '' ?>>STARTTLS (port 587)</option></select></label>
      <div class="grid-2"><?= f('smtp_user', 'Utilizator', $v, 'text', '', false) ?><?= f('smtp_pass', 'Parola căsuței', $v, 'password', '', false) ?></div>
      <?= f('mail_from', 'Expeditor', $v, 'email') ?>
    </section>
    <section class="card stack">
      <h2>Primul administrator</h2>
      <?= f('admin_name', 'Nume complet', $v) ?>
      <?= f('admin_email', 'Email', $v, 'email') ?>
      <?= f('admin_pass', 'Parolă', $v, 'password', 'cel puțin 8 caractere') ?>
    </section>
    <button class="btn primary lg block" type="submit"<?= $checksOk ? '' : ' disabled' ?>>Instalează</button>
  </form>
<?php endif; ?>
</main></div>
</body>
</html>
