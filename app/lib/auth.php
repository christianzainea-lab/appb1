<?php
declare(strict_types=1);

const REMEMBER_COOKIE = 'b1_remember';
const REMEMBER_DAYS = 60;

function start_session(): void {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $dir = STORAGE_DIR . '/sessions';
    if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
    ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 7));
    session_name('b1_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => base_path() ?: '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function current_user(bool $refresh = false): ?array {
    static $user = false;
    if ($refresh) $user = false;
    if ($user !== false) return $user;
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $user = row('SELECT * FROM users WHERE id = ?', [$_SESSION['uid']]);
        if (!$user || in_array($user['status'], ['rejected', 'disabled'], true)) {
            $user = null;
            unset($_SESSION['uid']);
        }
    } elseif (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $user = login_from_cookie();
    }
    if ($user && (!$user['last_seen_at'] || strtotime($user['last_seen_at']) < time() - 600)) {
        q('UPDATE users SET last_seen_at = ? WHERE id = ?', [now(), $user['id']]);
    }
    return $user;
}

function login_user(array $u, bool $remember = true): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    if ($remember) {
        $selector = bin2hex(random_bytes(12));
        $validator = random_token(32);
        q('INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
            [$u['id'], $selector, hash('sha256', $validator), date('Y-m-d H:i:s', time() + 86400 * REMEMBER_DAYS), now()]);
        set_remember_cookie($selector . ':' . $validator, time() + 86400 * REMEMBER_DAYS);
    }
}

function set_remember_cookie(string $value, int $expires): void {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(REMEMBER_COOKIE, $value, ['expires' => $expires, 'path' => base_path() ?: '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
}

function login_from_cookie(): ?array {
    $parts = explode(':', (string)$_COOKIE[REMEMBER_COOKIE], 2);
    if (count($parts) !== 2) return null;
    [$selector, $validator] = $parts;
    $t = row('SELECT * FROM remember_tokens WHERE selector = ?', [$selector]);
    if (!$t || strtotime($t['expires_at']) < time() || !hash_equals($t['validator_hash'], hash('sha256', $validator))) {
        set_remember_cookie('', time() - 3600);
        return null;
    }
    $u = row('SELECT * FROM users WHERE id = ?', [$t['user_id']]);
    if (!$u || in_array($u['status'], ['rejected', 'disabled'], true)) return null;
    // token nou la fiecare folosire
    q('DELETE FROM remember_tokens WHERE id = ?', [$t['id']]);
    login_user($u, true);
    return $u;
}

function logout_user(): void {
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $selector = explode(':', (string)$_COOKIE[REMEMBER_COOKIE])[0];
        q('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
        set_remember_cookie('', time() - 3600);
    }
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Cere un utilizator activ (sau doar logat, pentru paginile de așteptare). */
function require_user(bool $allowPending = false): array {
    $u = current_user();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login');
    }
    if ($u['status'] === 'unverified') redirect('confirmare');
    if ($u['status'] === 'pending' && !$allowPending) redirect('asteptare');
    return $u;
}

function require_role(string $role): array {
    $u = require_user();
    if (role_rank($u['role']) < role_rank($role)) forbidden();
    return $u;
}

/** Editor, admin sau pastor: pot deschide planificarea (pastorul doar ordinea, notițele și WhatsApp). */
function can_plan(): bool {
    $u = current_user();
    return $u && $u['status'] === 'active' && ($u['role'] === 'pastor' || role_rank($u['role']) >= 2);
}
function is_pastor(): bool {
    $u = current_user();
    return $u && $u['role'] === 'pastor';
}
function require_planner(): array {
    $u = require_user();
    if (!can_plan()) forbidden();
    return $u;
}

function can(string $role): bool {
    $u = current_user();
    return $u && $u['status'] === 'active' && role_rank($u['role']) >= role_rank($role);
}

/* ---------- tokenuri pe email ---------- */

function issue_user_token(int $userId, string $purpose, int $hours): string {
    q('UPDATE user_tokens SET used_at = ? WHERE user_id = ? AND purpose = ? AND used_at IS NULL', [now(), $userId, $purpose]);
    $token = random_token(32);
    q('INSERT INTO user_tokens (user_id, purpose, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
        [$userId, $purpose, hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600 * $hours), now()]);
    return $token;
}

function find_user_token(string $token, string $purpose): ?array {
    $t = row('SELECT * FROM user_tokens WHERE token_hash = ? AND purpose = ?', [hash('sha256', $token), $purpose]);
    if (!$t || $t['used_at'] || strtotime($t['expires_at']) < time()) return null;
    return $t;
}

function send_verification(array $u): bool {
    $token = issue_user_token((int)$u['id'], 'verify', 72);
    $link = full_url('confirma/' . $token);
    return send_mail($u['email'], 'Confirmă adresa de email',
        "Salut, " . first_name($u['name']) . "!\n\nConfirmă adresa de email ca să-ți activezi contul în " . app_name() . ":\n\n$link\n\nLinkul este valabil 3 zile. Dacă nu tu ai creat contul, ignoră acest mesaj.",
        ['Confirmă emailul', $link]);
}

function send_password_reset(array $u): bool {
    $token = issue_user_token((int)$u['id'], 'reset', 2);
    $link = full_url('parola-noua/' . $token);
    return send_mail($u['email'], 'Setează o parolă nouă',
        "Salut, " . first_name($u['name']) . "!\n\nAm primit o cerere de parolă nouă pentru contul tău. Deschide linkul de mai jos ca să alegi parola:\n\n$link\n\nLinkul este valabil 2 ore. Dacă nu tu ai cerut, ignoră mesajul; parola rămâne aceeași.",
        ['Alege parola nouă', $link]);
}

/* ---------- limitare încercări ---------- */

function too_many_attempts(string $email): bool {
    $since = date('Y-m-d H:i:s', time() - 900);
    q('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    $byIp = (int)val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', [client_ip(), $since]);
    $byEmail = (int)val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at > ?', [$email, $since]);
    return $byIp >= 20 || $byEmail >= 8;
}
function record_attempt(string $email): void {
    q('INSERT INTO login_attempts (ip, email, created_at) VALUES (?, ?, ?)', [client_ip(), $email, now()]);
}

function valid_password(string $p): ?string {
    if (mb_strlen($p) < 8) return 'Parola trebuie să aibă cel puțin 8 caractere.';
    if (mb_strlen($p) > 200) return 'Parola este prea lungă.';
    return null;
}

function normalize_phone(string $p): string {
    $p = preg_replace('/[^\d+]/', '', $p) ?? '';
    if (str_starts_with($p, '00')) $p = '+' . substr($p, 2);
    if (preg_match('/^07\d{8}$/', $p)) $p = '+4' . $p;
    return $p;
}
