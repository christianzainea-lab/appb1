<?php
declare(strict_types=1);

const DEFAULT_WHATSAPP = "*{eveniment} – {data}, {ora}*\nRepetiție: {repetitie}\n\n*Program*\n{ordinea}\n\n*Echipa*\n{echipa}\n\nÎnregistrări: {link}";

function settings_index(): void {
    require_role('editor');
    redirect('setari/persoane');
}

function settings_whatsapp(): void {
    require_role('editor');
    if (is_post()) {
        csrf_check();
        $text = post('reset') ? DEFAULT_WHATSAPP : str_replace("\r\n", "\n", (string)($_POST['template'] ?? ''));
        if (trim($text) === '') $text = DEFAULT_WHATSAPP;
        setting_set('whatsapp_template', mb_substr($text, 0, 4000));
        if (!post('reset')) {
            foreach (['whatsapp_include_key', 'whatsapp_include_items', 'whatsapp_first_names'] as $k) setting_set($k, empty($_POST[$k]) ? '0' : '1');
        }
        log_activity('settings', null, 'Mesaj WhatsApp', 'whatsapp', post('reset') ? 'A revenit la textul implicit al mesajului WhatsApp.' : 'A modificat mesajul WhatsApp.');
        flash(post('reset') ? 'Am revenit la textul implicit.' : 'Mesajul a fost salvat.');
        redirect('setari/whatsapp');
    }
    $preview = row("SELECT * FROM events WHERE status <> 'cancelled' AND event_date >= ? ORDER BY event_date, start_time LIMIT 1", [date('Y-m-d')]);
    render('settings/whatsapp', ['nav' => 'setari', 'sub' => 'whatsapp', 'preview' => $preview]);
}

function template_when(array $t): string {
    if ($t['weekday'] === null || $t['rule'] === 'none') return 'Nu se repetă';
    $day = RO_DAYS[(int)$t['weekday']];
    return match ($t['rule']) {
        'first' => $day . ' · doar în prima săptămână din lună',
        'not_first' => $day . ' · în fiecare săptămână, fără prima din lună',
        default => $day . ' · în fiecare săptămână',
    };
}

function settings_church(): void {
    $me = require_role('editor');
    if (is_post()) {
        csrf_check();
        if (post('action') === 'logo') {
            $err = brand_logo_save($_FILES['logo'] ?? null);
            if ($err) flash($err, 'error');
            else { log_activity('settings', null, 'Biserică', 'church', 'A schimbat logoul.'); flash('Logoul a fost schimbat.'); }
            redirect('setari/biserica');
        }
        if (post('action') === 'logo_reset') {
            foreach (['logo.png', 'favicon.png'] as $f) if ($p = brand_file($f)) @unlink($p);
            log_activity('settings', null, 'Biserică', 'church', 'A revenit la logoul implicit.');
            flash('Am revenit la logoul implicit.');
            redirect('setari/biserica');
        }
        if (post('action') === 'test_mail') {
            $ok = send_mail($me['email'], 'Email de test', "Dacă citești acest mesaj, emailurile din aplicație funcționează.");
            flash($ok ? 'Am trimis un email de test la ' . $me['email'] . '. Verifică și dosarul Spam.' : 'Emailul nu a putut fi trimis. Detaliile sunt în storage/logs/mail.log.', $ok ? 'ok' : 'error');
        } else {
            $app = post('app_name');
            setting_set('app_name', $app !== '' ? mb_substr($app, 0, 60) : DEFAULT_APP_NAME);
            setting_set('church_name', mb_substr(post('church_name'), 0, 120));
            log_activity('settings', null, 'Biserică', 'church', 'Numele aplicației: „' . setting('app_name') . '” · biserica: „' . post('church_name') . '”.');
            flash('Setările au fost salvate.');
        }
        redirect('setari/biserica');
    }
    render('settings/church', ['nav' => 'setari', 'sub' => 'biserica', 'me' => $me]);
}

function settings_positions(): void {
    require_role('editor');
    if (is_post()) {
        csrf_check();
        positions_action(post('action'));
        redirect('setari/pozitii');
    }
    render('settings/positions', ['nav' => 'setari', 'sub' => 'pozitii', 'tree' => position_tree()]);
}

function positions_action(string $a): void {
    $id = (int)post('id');
    $name = mb_substr(post('name'), 0, 80);
    $L = fn(string $t) => log_activity('settings', null, 'Roluri și poziții', $a, $t);
    $posN = (string)val('SELECT name FROM positions WHERE id = ?', [$id]);
    $grpN = (string)val('SELECT name FROM position_groups WHERE id = ?', [$id]);
    switch ($a) {
        case 'group_add':
            if ($name === '') return;
            q('INSERT INTO position_groups (name, sort) VALUES (?, ?)', [$name, (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM position_groups')]);
            $L("Grup nou: $name.");
            return;
        case 'group_rename':
            if ($name !== '' && $name !== $grpN) { q('UPDATE position_groups SET name = ? WHERE id = ?', [$name, $id]); $L("Grup redenumit: $grpN → $name."); }
            return;
        case 'group_delete':
            if ((int)val('SELECT COUNT(*) FROM positions WHERE group_id = ?', [$id]) > 0) { flash('Mută sau șterge întâi pozițiile din grup.', 'error'); return; }
            q('DELETE FROM position_groups WHERE id = ?', [$id]);
            if ($grpN !== '') $L("Grup șters: $grpN.");
            return;
        case 'group_up':
        case 'group_down':
            reorder('position_groups', $id, $a === 'group_up' ? -1 : 1);
            return;
        case 'pos_add':
            $gid = (int)post('group_id');
            if ($name === '' || !val('SELECT COUNT(*) FROM position_groups WHERE id = ?', [$gid])) return;
            q('INSERT INTO positions (group_id, name, sort) VALUES (?, ?, ?)', [$gid, $name, (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM positions WHERE group_id = ?', [$gid])]);
            $L("Poziție nouă: $name.");
            return;
        case 'pos_rename':
            if ($name !== '' && $name !== $posN) { q('UPDATE positions SET name = ? WHERE id = ?', [$name, $id]); $L("Poziție redenumită: $posN → $name."); }
            return;
        case 'pos_move':
            $gid = (int)post('group_id');
            if (!val('SELECT COUNT(*) FROM position_groups WHERE id = ?', [$gid])) return;
            q('UPDATE positions SET group_id = ?, sort = ? WHERE id = ?', [$gid, (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM positions WHERE group_id = ?', [$gid]), $id]);
            $L("Poziția $posN mutată în grupul " . val('SELECT name FROM position_groups WHERE id = ?', [$gid]) . '.');
            return;
        case 'pos_up':
        case 'pos_down':
            $gid = (int)val('SELECT group_id FROM positions WHERE id = ?', [$id]);
            reorder('positions', $id, $a === 'pos_up' ? -1 : 1, 'group_id = ' . $gid);
            return;
        case 'pos_delete':
            $n = (int)val('SELECT COUNT(*) FROM user_positions WHERE position_id = ?', [$id]);
            if ($n > 0 && empty($_POST['confirm'])) return;
            $parts = q('SELECT id FROM position_parts WHERE position_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN);
            foreach ($parts as $pid) q('DELETE FROM user_parts WHERE part_id = ?', [$pid]);
            q('DELETE FROM position_parts WHERE position_id = ?', [$id]);
            q('DELETE FROM user_positions WHERE position_id = ?', [$id]);
            q('DELETE FROM template_positions WHERE position_id = ?', [$id]);
            q('DELETE FROM positions WHERE id = ?', [$id]);
            $L("Poziție ștearsă: $posN" . ($n ? " (o aveau $n persoane)" : '') . '.');
            flash('Poziția a fost ștearsă.');
            return;
        case 'part_add':
            if ($name === '' || !val('SELECT COUNT(*) FROM positions WHERE id = ?', [$id])) return;
            q('INSERT INTO position_parts (position_id, name, sort) VALUES (?, ?, ?)', [$id, $name, (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM position_parts WHERE position_id = ?', [$id])]);
            $L("Partidă nouă la $posN: $name.");
            return;
        case 'part_delete':
            $pp = row('SELECT pp.name, p.name AS pos FROM position_parts pp JOIN positions p ON p.id = pp.position_id WHERE pp.id = ?', [$id]);
            if ($pp) $L('Partidă ștearsă la ' . $pp['pos'] . ': ' . $pp['name'] . '.');
            q('DELETE FROM user_parts WHERE part_id = ?', [$id]);
            q('DELETE FROM position_parts WHERE id = ?', [$id]);
            return;
    }
}

/** Mută un rând sus/jos, renumerotând ordinea. */
function reorder(string $table, int $id, int $dir, string $scope = '1 = 1'): void {
    $ids = array_map('intval', q("SELECT id FROM $table WHERE $scope ORDER BY sort, id")->fetchAll(PDO::FETCH_COLUMN));
    $i = array_search($id, $ids, true);
    if ($i === false) return;
    $j = $i + $dir;
    if ($j < 0 || $j >= count($ids)) return;
    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    foreach ($ids as $k => $rid) q("UPDATE $table SET sort = ? WHERE id = ?", [$k, $rid]);
}

/** Salvează logoul încărcat: PNG până la 512 px și o iconiță de tab de 64 px. Întoarce mesajul de eroare sau null. */
function brand_logo_save(?array $f): ?string {
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return 'Alege o imagine.';
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) return 'Imaginea nu s-a putut încărca.';
    if ($f['size'] > 5 * 1048576) return 'Imaginea e prea mare (maximum 5 MB).';
    if (!function_exists('imagecreatefromstring')) return 'Serverul nu are extensia gd (necesară pentru logo).';
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) return 'Logoul trebuie să fie PNG, JPG sau WebP.';
    $src = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
    if (!$src) return 'Imaginea nu se poate citi.';
    [$w, $h] = [imagesx($src), imagesy($src)];
    $fit = function (int $box, bool $square) use ($src, $w, $h) {
        $k = min(1, $box / max($w, $h));
        $nw = max(1, (int)round($w * $k)); $nh = max(1, (int)round($h * $k));
        [$cw, $ch] = $square ? [$box, $box] : [$nw, $nh];
        $im = imagecreatetruecolor($cw, $ch);
        imagealphablending($im, false); imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);
        imagecopyresampled($im, $src, intdiv($cw - $nw, 2), intdiv($ch - $nh, 2), 0, 0, $nw, $nh, $w, $h);
        return $im;
    };
    if (!is_dir(brand_dir())) @mkdir(brand_dir(), 0755, true);
    $ok = imagepng($fit(512, false), brand_dir() . '/logo.png') && imagepng($fit(64, true), brand_dir() . '/favicon.png');
    return $ok ? null : 'Nu pot salva logoul în storage/brand.';
}

/** Logoul încărcat (public: apare și pe pagina de intrare). */
function brand_logo_serve(string $which = 'logo'): void {
    $f = brand_file($which === 'favicon' ? 'favicon.png' : 'logo.png');
    if (!$f) { header('Location: ' . asset($which === 'favicon' ? 'favicon.png' : 'logo.png')); exit; }
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    header('Content-Length: ' . filesize($f));
    readfile($f);
    exit;
}
