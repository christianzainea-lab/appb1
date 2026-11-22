<?php
declare(strict_types=1);

function page_home(): void {
    $u = require_user();
    $mine = my_upcoming((int)$u['id']);
    $past = rows("SELECT s.id AS slot_id, p.name AS position, e.* FROM event_slots s JOIN events e ON e.id = s.event_id JOIN positions p ON p.id = s.position_id
        WHERE s.user_id = ? AND e.status = 'published' AND e.event_date < ? AND e.event_date >= ? AND (s.response IS NULL OR s.response <> 'declined') ORDER BY e.event_date DESC LIMIT 5", [$u['id'], date('Y-m-d'), date('Y-m-d', strtotime('-60 days'))]);
    $drafts = can('editor') ? (int)val("SELECT COUNT(*) FROM events WHERE status = 'draft' AND event_date BETWEEN ? AND ?", [date('Y-m-d'), date('Y-m-d', strtotime('+6 weeks'))]) : 0;
    render('pages/home', ['u' => $u, 'nav' => 'program', 'mine' => $mine, 'past' => $past, 'drafts' => $drafts]);
}

function page_edit_hub(): void {
    require_role('editor');
    redirect('planificare');
}

function page_menu(): void {
    require_user(true);
    render('pages/menu', ['nav' => 'meniu']);
}

function page_profile(): void {
    $u = require_user(true);
    $errors = [];
    if (is_post()) {
        csrf_check();
        $action = post('action');
        if ($action === 'save') {
            $name = post('name');
            $phoneRaw = post('phone');
            $phone = normalize_phone($phoneRaw);
            if (mb_strlen($name) < 3) $errors['name'] = 'Scrie numele complet.';
            if (($phoneRaw !== '' && $phone === '') || ($phone !== '' && !preg_match('/^\+?\d{8,15}$/', $phone))) $errors['phone'] = 'Numărul de telefon nu pare corect.';
            if (!$errors) {
                $posBefore = user_position_labels((int)$u['id']);
                $changes = [];
                if ($name !== $u['name']) $changes[] = 'nume: ' . $u['name'] . ' → ' . $name;
                if (($phone ?: null) !== $u['phone']) $changes[] = 'a schimbat telefonul';
                q('UPDATE users SET name = ?, phone = ?, notify_push = ?, notify_reminder = ?, updated_at = ? WHERE id = ?',
                    [$name, $phone ?: null, empty($_POST['notify_push']) ? 0 : 1, empty($_POST['notify_reminder']) ? 0 : 1, now(), $u['id']]);
                save_user_positions((int)$u['id'], (array)($_POST['positions'] ?? []), (array)($_POST['parts'] ?? []));
                if ($changes) log_activity('user', (int)$u['id'], $name, 'profile', 'Profil: ' . implode('; ', $changes) . '.');
                log_positions_change((int)$u['id'], $name, $posBefore);
                flash('Profilul a fost salvat.');
                redirect('profil');
            }
        } elseif ($action === 'password') {
            $cur = (string)($_POST['current'] ?? '');
            $p1 = (string)($_POST['password'] ?? '');
            $p2 = (string)($_POST['password2'] ?? '');
            if ($u['password_hash'] && !password_verify($cur, $u['password_hash'])) $errors['current'] = 'Parola actuală nu este corectă.';
            elseif ($pe = valid_password($p1)) $errors['password'] = $pe;
            elseif ($p1 !== $p2) $errors['password2'] = 'Cele două parole nu sunt la fel.';
            if (!$errors) {
                q('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($p1, PASSWORD_DEFAULT), now(), $u['id']]);
                q('DELETE FROM remember_tokens WHERE user_id = ?', [$u['id']]);
                login_user($u, true);
                log_activity('user', (int)$u['id'], $u['name'], 'password', 'Și-a schimbat parola.');
                flash('Parola a fost schimbată.');
                redirect('profil');
            }
            $_GET['parola'] = '1';
        } elseif ($action === 'avatar') {
            $res = save_avatar((int)$u['id'], $_FILES['photo'] ?? null);
            if ($res !== true) flash($res, 'error');
            else flash('Poza a fost schimbată.');
            redirect('profil');
        } elseif ($action === 'calendar_renew') {
            calendar_token($u, true);
            log_activity('user', (int)$u['id'], $u['name'], 'calendar', 'A generat un link nou pentru calendar (cel vechi nu mai merge).');
            flash('Am făcut un link nou. Cel vechi nu mai funcționează – adaugă-l din nou în calendar.');
            redirect('profil#calendar');
        } elseif ($action === 'avatar_remove') {
            if ($u['avatar']) @unlink(STORAGE_DIR . '/avatars/' . $u['avatar']);
            q('UPDATE users SET avatar = NULL WHERE id = ?', [$u['id']]);
            redirect('profil');
        }
        $u = current_user(true);
    }
    render('pages/profile', [
        'u' => $u, 'nav' => 'profil', 'errors' => $errors, 'tree' => position_tree(),
        'mine' => user_position_ids((int)$u['id']), 'myParts' => user_part_ids((int)$u['id']),
        'showPassword' => !empty($_GET['parola']),
        'calUrl' => $u['status'] === 'active' ? full_url('calendar/' . calendar_token($u) . '.ics') : null,
    ]);
}

/** Calendarul personal, fără autentificare: linkul secret e cheia. */
function calendar_feed(string $t): void {
    $tok = preg_replace('/\.ics$/', '', $t);
    $u = (strlen($tok) === 40 && ctype_xdigit($tok)) ? row("SELECT * FROM users WHERE calendar_token = ? AND status = 'active'", [$tok]) : null;
    if (!$u) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'Calendarul nu există. Ia un link nou din Profil.'; return; }
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="b1-program.ics"');
    header('Cache-Control: private, max-age=900');
    header('X-Robots-Tag: noindex');
    echo calendar_ics($u);
}

/** @return true|string */
function save_avatar(int $uid, ?array $file) {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return 'Alege o poză.';
    if ($file['error'] !== UPLOAD_ERR_OK) return 'Poza nu s-a încărcat. Încearcă una mai mică.';
    if ($file['size'] > 8 * 1024 * 1024) return 'Poza este prea mare (maximum 8 MB).';
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    if (!$info || !isset($types[$info[2]]) || !function_exists($types[$info[2]])) return 'Folosește o poză JPG, PNG sau WEBP.';
    $src = @$types[$info[2]]($file['tmp_name']);
    if (!$src) return 'Poza nu a putut fi citită.';
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 0] ?? 0;
        if ($rot) $src = imagerotate($src, $rot, 0);
    }
    $w = imagesx($src); $h = imagesy($src); $s = min($w, $h);
    $dst = imagecreatetruecolor(256, 256);
    imagecopyresampled($dst, $src, 0, 0, (int)(($w - $s) / 2), (int)(($h - $s) / 2), 256, 256, $s, $s);
    $name = 'u' . $uid . '_' . bin2hex(random_bytes(6)) . '.jpg';
    imagejpeg($dst, STORAGE_DIR . '/avatars/' . $name, 85);
    $old = val('SELECT avatar FROM users WHERE id = ?', [$uid]);
    if ($old) @unlink(STORAGE_DIR . '/avatars/' . $old);
    q('UPDATE users SET avatar = ?, updated_at = ? WHERE id = ?', [$name, now(), $uid]);
    return true;
}

function page_avatar(string $file): void {
    require_user(true);
    $path = STORAGE_DIR . '/avatars/' . basename($file);
    if (!preg_match('/^u\d+_[a-f0-9]+\.jpg$/', $file) || !is_file($path)) not_found();
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=86400');
    readfile($path);
}

/** Ghidul în imagini: doar pentru conturile active. Prima vizită închide fereastra de bun venit. */
function page_guide(): void {
    $u = require_user();
    if ($u['status'] !== 'active') redirect('asteptare');
    if (is_post()) {
        csrf_check();
        q('UPDATE users SET guide_seen_at = ? WHERE id = ? AND guide_seen_at IS NULL', [now(), $u['id']]);
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') { header('Content-Type: application/json'); echo '{"ok":true}'; return; }
        $back = post('back');
        redirect(preg_match('#^[a-z0-9/_-]*$#', $back) ? $back : '');
    }
    if (empty($u['guide_seen_at'])) q('UPDATE users SET guide_seen_at = ? WHERE id = ?', [now(), $u['id']]);
    $role = $u['role'] === 'pastor' ? 'pastor' : (role_rank($u['role']) >= 2 ? 'editor' : 'voluntar');
    render('pages/guide', ['nav' => 'ghid', 'u' => $u, 'start' => $role]);
}
