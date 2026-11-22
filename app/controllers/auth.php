<?php
declare(strict_types=1);

function after_login_redirect(array $u): never {
    if ($u['status'] === 'unverified') redirect('confirmare');
    if ($u['status'] === 'pending') redirect('asteptare');
    $to = $_SESSION['after_login'] ?? '';
    unset($_SESSION['after_login']);
    if (is_string($to) && $to !== '' && str_starts_with($to, '/') && !str_starts_with($to, '//')) {
        header('Location: ' . $to);
        exit;
    }
    redirect('');
}

function auth_login(): void {
    if ($u = current_user()) after_login_redirect($u);
    $error = null;
    $email = '';
    if (is_post()) {
        csrf_check();
        $email = mb_strtolower(post('email'));
        $password = (string)($_POST['password'] ?? '');
        if (too_many_attempts($email)) {
            $error = 'Prea multe încercări. Așteaptă 15 minute și încearcă din nou.';
        } else {
            $u = row('SELECT * FROM users WHERE email = ?', [$email]);
            if ($u && $u['password_hash'] && password_verify($password, $u['password_hash'])) {
                if ($u['status'] === 'rejected' || $u['status'] === 'disabled') {
                    $error = 'Contul acesta nu este activ. Vorbește cu un administrator.';
                } else {
                    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
                    }
                    login_user($u, !empty($_POST['remember']));
                    after_login_redirect($u);
                }
            } else {
                record_attempt($email);
                $error = ($u && !$u['password_hash'])
                    ? 'Contul acesta nu are încă parolă. Folosește „Am uitat parola” ca să-ți setezi una.'
                    : 'Email sau parolă greșită.';
            }
        }
    }
    render('auth/login', ['error' => $error, 'email' => $email], 'auth');
}

function auth_logout(): void {
    if (is_post()) {
        csrf_check();
        logout_user();
    }
    redirect('login');
}

function auth_register(string $token): void {
    $inv = row('SELECT * FROM invites WHERE token = ?', [$token]);
    if (!invite_valid($inv)) {
        render('auth/message', ['title' => 'Linkul nu mai este valabil', 'text' => 'Invitația a expirat sau a fost dezactivată. Cere un link nou de la liderul echipei.', 'link' => ['login', 'Ai deja cont? Intră în cont']], 'auth');
        return;
    }
    if ($u = current_user()) after_login_redirect($u);

    $errors = [];
    $old = ['name' => $inv['name'] ?? '', 'email' => $inv['email'] ?? '', 'phone' => '', 'positions' => [], 'parts' => []];
    if (is_post()) {
        csrf_check();
        $old = [
            'name' => post('name'),
            'email' => mb_strtolower(post('email')),
            'phone' => normalize_phone(post('phone')),
            'positions' => array_map('intval', (array)($_POST['positions'] ?? [])),
            'parts' => array_map('intval', (array)($_POST['parts'] ?? [])),
        ];
        $password = (string)($_POST['password'] ?? '');
        if (mb_strlen($old['name']) < 3) $errors['name'] = 'Scrie numele complet.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Adresa de email nu pare corectă.';
        elseif (val('SELECT COUNT(*) FROM users WHERE email = ?', [$old['email']])) $errors['email'] = 'Există deja un cont cu acest email. Intră în cont sau folosește „Am uitat parola”.';
        if ((post('phone') !== '' && $old['phone'] === '') || ($old['phone'] !== '' && !preg_match('/^\+?\d{8,15}$/', $old['phone']))) $errors['phone'] = 'Numărul de telefon nu pare corect.';
        if ($pe = valid_password($password)) $errors['password'] = $pe;

        if (!$errors) {
            $role = in_array($inv['role'], ['voluntar', 'pastor', 'editor'], true) ? $inv['role'] : 'voluntar';
            q('INSERT INTO users (name, email, phone, password_hash, role, status, invite_id, calendar_token, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$old['name'], $old['email'], $old['phone'] ?: null, password_hash($password, PASSWORD_DEFAULT), $role, 'unverified', $inv['id'], random_token(24), now(), now()]);
            $uid = (int)db()->lastInsertId();
            q('UPDATE invites SET uses = uses + 1 WHERE id = ?', [$inv['id']]);
            save_user_positions($uid, $old['positions'], $old['parts']);
            $u = row('SELECT * FROM users WHERE id = ?', [$uid]);
            log_activity('user', $uid, $u['name'], 'joined', 'S-a înscris (' . $u['email'] . ') prin ' . ($inv['kind'] === 'personal' ? 'invitația personală' : 'linkul echipei') . '.', $u);
            login_user($u, true);
            if (!send_verification($u)) {
                flash('Nu am putut trimite emailul de confirmare acum. Încearcă „Trimite din nou” peste câteva minute.', 'error');
            }
            redirect('confirmare');
        }
    }
    render('auth/register', ['inv' => $inv, 'errors' => $errors, 'old' => $old, 'tree' => position_tree()], 'auth');
}

function auth_verify_notice(): void {
    $u = current_user();
    if (!$u) redirect('login');
    if ($u['status'] !== 'unverified') after_login_redirect($u);
    if (is_post()) {
        csrf_check();
        $last = $_SESSION['verify_sent_at'] ?? 0;
        if (time() - $last < 60) {
            flash('Am trimis deja un email acum câteva secunde. Verifică și dosarul Spam.', 'error');
        } elseif (send_verification($u)) {
            $_SESSION['verify_sent_at'] = time();
            flash('Am trimis din nou emailul de confirmare.');
        } else {
            flash('Nu am putut trimite emailul. Încearcă din nou mai târziu sau anunță liderul echipei.', 'error');
        }
        redirect('confirmare');
    }
    render('auth/verify', ['u' => $u], 'auth');
}

/** Emailul a fost dovedit: personal → activ direct; link de echipă → așteaptă aprobarea. */
function mark_email_verified(array $u): array {
    if ($u['email_verified_at']) return $u;
    $inv = $u['invite_id'] ? row('SELECT * FROM invites WHERE id = ?', [$u['invite_id']]) : null;
    $status = $u['status'];
    if ($status === 'unverified') $status = ($inv && $inv['kind'] === 'personal') ? 'active' : 'pending';
    q('UPDATE users SET email_verified_at = ?, status = ?, updated_at = ? WHERE id = ?', [now(), $status, now(), $u['id']]);
    if ($status === 'active' && $u['status'] === 'unverified') {
        q('UPDATE users SET approved_at = ?, approved_by = ? WHERE id = ?', [now(), $inv['created_by'] ?? null, $u['id']]);
    }
    $u = row('SELECT * FROM users WHERE id = ?', [$u['id']]);
    log_activity('user', (int)$u['id'], $u['name'], 'verified', 'A confirmat emailul' . ($status === 'active' ? ' · cont activat (invitație personală).' : ' · așteaptă aprobarea.'), $u);
    if ($status === 'pending') notify_admins_new_request($u);
    return $u;
}

function auth_verify(string $token): void {
    $t = find_user_token($token, 'verify');
    if (!$t) {
        $u = current_user();
        if ($u && $u['email_verified_at']) after_login_redirect($u);
        render('auth/message', ['title' => 'Linkul nu mai este valabil', 'text' => 'Linkul de confirmare a expirat sau a fost deja folosit. Intră în cont ca să primești unul nou.', 'link' => ['login', 'Intră în cont']], 'auth');
        return;
    }
    q('UPDATE user_tokens SET used_at = ? WHERE id = ?', [now(), $t['id']]);
    $u = row('SELECT * FROM users WHERE id = ?', [$t['user_id']]);
    if (!$u || in_array($u['status'], ['rejected', 'disabled'], true)) not_found();
    $u = mark_email_verified($u);
    login_user($u, true);
    current_user(true);
    if ($u['status'] === 'active') flash('Emailul este confirmat. Bine ai venit în echipă!');
    after_login_redirect($u);
}

function auth_pending(): void {
    if (isset($_GET['stare'])) { // verificare automată din pagină
        $u = current_user();
        header('Content-Type: application/json');
        echo json_encode(['status' => $u['status'] ?? 'none']);
        return;
    }
    $u = require_user(true);
    if ($u['status'] === 'active') redirect('');
    render('auth/pending', ['u' => $u], 'auth');
}

function auth_forgot(): void {
    $sent = false;
    $email = '';
    if (is_post()) {
        csrf_check();
        $email = mb_strtolower(post('email'));
        if (!too_many_attempts($email)) {
            record_attempt($email);
            $u = row('SELECT * FROM users WHERE email = ?', [$email]);
            if ($u && !in_array($u['status'], ['rejected', 'disabled'], true)) send_password_reset($u);
        }
        $sent = true;
    }
    render('auth/forgot', ['sent' => $sent, 'email' => $email], 'auth');
}

function auth_reset(string $token): void {
    $t = find_user_token($token, 'reset');
    if (!$t) {
        render('auth/message', ['title' => 'Linkul nu mai este valabil', 'text' => 'Linkul pentru parola nouă a expirat sau a fost deja folosit. Cere unul nou.', 'link' => ['parola-uitata', 'Cere un link nou']], 'auth');
        return;
    }
    $error = null;
    if (is_post()) {
        csrf_check();
        $p1 = (string)($_POST['password'] ?? '');
        $p2 = (string)($_POST['password2'] ?? '');
        if ($pe = valid_password($p1)) $error = $pe;
        elseif ($p1 !== $p2) $error = 'Cele două parole nu sunt la fel.';
        else {
            q('UPDATE user_tokens SET used_at = ? WHERE id = ?', [now(), $t['id']]);
            q('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($p1, PASSWORD_DEFAULT), now(), $t['user_id']]);
            q('DELETE FROM remember_tokens WHERE user_id = ?', [$t['user_id']]);
            $u = row('SELECT * FROM users WHERE id = ?', [$t['user_id']]);
            log_activity('user', (int)$u['id'], $u['name'], 'password', 'A setat o parolă nouă din linkul de resetare.', $u);
            $u = mark_email_verified($u);
            login_user($u, true);
            current_user(true);
            flash('Parola a fost schimbată.');
            after_login_redirect($u);
        }
    }
    render('auth/reset', ['error' => $error], 'auth');
}
