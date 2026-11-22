<?php
declare(strict_types=1);

function songs_index(): void {
    require_user();
    $songs = songs_all();
    $sort = (string)($_GET['sort'] ?? 'az');
    $coll = class_exists('Collator') ? new Collator('ro_RO') : null;
    $cmpTitle = fn($a, $b) => $coll ? $coll->compare($a['title'], $b['title']) : strcmp($a['title'], $b['title']);
    usort($songs, match ($sort) {
        'vechi' => fn($a, $b) => strcmp((string)$a['last_used'], (string)$b['last_used']) ?: $cmpTitle($a, $b),
        'des' => fn($a, $b) => ($b['total'] <=> $a['total']) ?: $cmpTitle($a, $b),
        'recent' => fn($a, $b) => strcmp((string)$b['last_used'], (string)$a['last_used']) ?: $cmpTitle($a, $b),
        'noi' => fn($a, $b) => ($b['id'] <=> $a['id']),
        default => $cmpTitle,
    });
    $stats = [
        'all' => count($songs),
        'noRec' => count(array_filter($songs, fn($s) => !$s['media'])),
        'noChords' => count(array_filter($songs, fn($s) => !$s['chords_file'])),
    ];
    render('songs/index', ['nav' => 'cantari', 'songs' => $songs, 'labels' => labels_all(), 'sort' => $sort, 'stats' => $stats, 'isEditor' => can('editor')]);
}

function song_show(string $id): void {
    require_user();
    $s = row('SELECT * FROM songs WHERE id = ?', [(int)$id]);
    if (!$s) not_found();
    $media = rows('SELECT * FROM song_media WHERE song_id = ? ORDER BY kind, is_main DESC, sort, id', [$s['id']]);
    $labels = rows('SELECT l.* FROM song_labels sl JOIN labels l ON l.id = sl.label_id WHERE sl.song_id = ? ORDER BY l.kind, l.sort', [$s['id']]);
    $uses = rows('SELECT * FROM song_uses WHERE song_id = ? AND used_on <= ? ORDER BY used_on DESC, slot', [$s['id'], date('Y-m-d')]);
    $upcoming = rows("SELECT u.*, e.name AS event_name FROM song_uses u JOIN events e ON e.id = u.event_id WHERE u.song_id = ? AND u.used_on > ? AND e.status " . (can('editor') ? "<> 'cancelled'" : "= 'published'") . ' ORDER BY u.used_on', [$s['id'], date('Y-m-d')]);
    // venit din ordinea unui serviciu: navigare între cântările acelui serviciu
    $svc = null;
    $din = (int)($_GET['din'] ?? 0);
    if ($din) {
        $ev = row("SELECT * FROM events WHERE id = ? AND status " . (can_plan() ? "<> 'cancelled'" : "= 'published'"), [$din]);
        if ($ev) {
            $list = array_values(array_filter(event_items($din), fn($i) => $i['kind'] === 'song' && $i['song_id']));
            $idx = (int)($_GET['nr'] ?? 0) - 1;
            if (!isset($list[$idx]) || (int)$list[$idx]['song_id'] !== (int)$s['id']) {
                $idx = -1;
                foreach ($list as $k => $i) if ((int)$i['song_id'] === (int)$s['id']) { $idx = $k; break; }
            }
            if ($idx >= 0) $svc = ['e' => $ev, 'list' => $list, 'idx' => $idx];
        }
    }
    render('songs/show', ['nav' => $svc ? 'program' : 'cantari', 's' => $s, 'svc' => $svc, 'media' => $media, 'labels' => $labels, 'uses' => $uses, 'upcoming' => $upcoming, 'isEditor' => can('editor'),
        'history' => can('editor') ? activity_for('song', (int)$s['id']) : []]);
}

function song_new(): void {
    $me = require_role('editor');
    song_form(null, $me);
}

function song_edit(string $id): void {
    $me = require_role('editor');
    $s = row('SELECT * FROM songs WHERE id = ?', [(int)$id]);
    if (!$s) not_found();
    song_form($s, $me);
}

function song_form(?array $s, array $me): void {
    $errors = [];
    if (is_post()) {
        csrf_check();
        if (post('action') === 'delete' && $s) {
            if (empty($_POST['confirm_delete'])) { flash('Bifează confirmarea ca să ștergi cântarea.', 'error'); redirect('cantare/' . $s['id'] . '/editeaza'); }
            $snap = song_snapshot((int)$s['id']);
            $uses = (int)val('SELECT COUNT(*) FROM song_uses WHERE song_id = ?', [$s['id']]);
            song_delete((int)$s['id']);
            log_activity('song', (int)$s['id'], $s['title'], 'deleted', 'A șters cântarea' . (song_ref($s) ? ' ' . song_ref($s) : '') . ' (' . count($snap['media']) . ' înregistrări, ' . $uses . ' date în istoricul cântărilor).');
            flash('Cântarea „' . $s['title'] . '” a fost ștearsă.');
            redirect('cantari');
        }
        if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $errors['files'] = 'Fișierele sunt prea mari pentru server (maximum ' . upload_limit_mb() . ' MB odată).';
        }
        $overwrote = null;
        if ($s && post('loaded_at') !== '' && post('loaded_at') !== (string)$s['updated_at'] && empty($_POST['force_overwrite'])) {
            $by = row("SELECT user_name, created_at FROM activity_log WHERE area = 'song' AND object_id = ? ORDER BY id DESC LIMIT 1", [$s['id']]);
            $errors['conflict'] = ($by && $by['user_name'] ? $by['user_name'] : 'Altcineva') . ' a modificat această cântare' . ($by ? ' la ' . date('H:i', strtotime($by['created_at'])) : '') . ', după ce ai deschis pagina. Reîncarcă pagina ca să vezi modificările lor sau bifează „Salvează oricum” mai jos.';
            $conflictBy = $by['user_name'] ?? 'altcineva';
        } elseif ($s && post('loaded_at') !== '' && post('loaded_at') !== (string)$s['updated_at']) {
            $overwrote = (string)(val("SELECT user_name FROM activity_log WHERE area = 'song' AND object_id = ? ORDER BY id DESC LIMIT 1", [$s['id']]) ?: 'altcineva');
        }
        $title = post('title');
        if (mb_strlen($title) < 2) $errors['title'] = 'Scrie titlul cântării.';
        $bpm = post('bpm');
        if ($bpm !== '' && (!ctype_digit($bpm) || (int)$bpm < 30 || (int)$bpm > 250)) $errors['bpm'] = 'BPM între 30 și 250.';
        $key = post('song_key');
        // un ton vechi, care nu mai e în listă, se poate păstra neschimbat
        if ($key !== '' && !is_known_key($key) && !($s && $key === (string)$s['song_key'])) $errors['song_key'] = 'Ton necunoscut.';
        $source = post('source');
        // o carte veche, care nu mai e în listă, se poate păstra neschimbată
        if ($source !== '' && !is_known_book($source) && !($s && $source === (string)$s['source'])) $errors['source'] = 'Carte necunoscută. Adaug-o întâi în Setări → Cărți.';

        if (!$errors) {
            $fields = [
                $title, post('author') ?: null, mb_substr(post('source'), 0, 20) ?: null, mb_substr(post('source_no'), 0, 20) ?: null,
                $key ?: null, $bpm === '' ? null : (int)$bpm, trim((string)($_POST['notes'] ?? '')) ?: null, trim(str_replace("\r\n", "\n", (string)($_POST['lyrics'] ?? ''))) ?: null, now(),
            ];
            $before = $s ? song_snapshot((int)$s['id']) : null;
            if ($s) {
                q('UPDATE songs SET title = ?, author = ?, source = ?, source_no = ?, song_key = ?, bpm = ?, notes = ?, lyrics = ?, updated_at = ? WHERE id = ?', array_merge($fields, [$s['id']]));
                $sid = (int)$s['id'];
            } else {
                q('INSERT INTO songs (title, author, source, source_no, song_key, bpm, notes, lyrics, updated_at, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($fields, [$me['id'], now()]));
                $sid = (int)db()->lastInsertId();
            }
            $msgs = song_save_related($sid);
            $after = song_snapshot($sid);
            $what = song_diff($before, $after);
            if ($overwrote !== null) $what = 'A salvat peste modificările lui ' . $overwrote . '. ' . $what;
            if ($what !== '') log_activity('song', $sid, $after['title'], $s ? 'updated' : 'created', $what);
            flash(($s ? 'Modificările au fost salvate.' : 'Cântarea a fost adăugată.') . ($msgs ? ' ' . implode(' ', $msgs) : ''), $msgs ? 'error' : 'ok');
            redirect('cantare/' . $sid);
        }
    }
    $media = $s ? rows('SELECT * FROM song_media WHERE song_id = ? ORDER BY kind, is_main DESC, sort, id', [$s['id']]) : [];
    $songLabels = $s ? array_map('intval', q('SELECT label_id FROM song_labels WHERE song_id = ?', [$s['id']])->fetchAll(PDO::FETCH_COLUMN)) : [];
    $sources = array_column(books_all(), 'name');
    render('songs/form', [
        'nav' => 'cantari', 's' => $s, 'errors' => $errors, 'media' => $media, 'labels' => labels_all(),
        'songLabels' => is_post() ? array_map('intval', (array)($_POST['labels'] ?? [])) : $songLabels,
        'sources' => $sources, 'limit' => upload_limit_mb(), 'conflictBy' => $conflictBy ?? null,
    ]);
}

/** Etichete, variante YouTube, înregistrări și acorduri. Întoarce mesaje de eroare pentru fișiere. */
function song_save_related(int $sid): array {
    $msgs = [];
    // etichete existente + una nouă
    $labelIds = array_map('intval', (array)($_POST['labels'] ?? []));
    $newLabel = mb_substr(post('new_label'), 0, 60);
    if ($newLabel !== '') {
        $kind = post('new_label_kind') === 'tema' ? 'tema' : 'ocazie';
        $existing = val('SELECT id FROM labels WHERE LOWER(name) = LOWER(?) AND kind = ?', [$newLabel, $kind]);
        if (!$existing) {
            q('INSERT INTO labels (name, kind, sort) VALUES (?, ?, ?)', [$newLabel, $kind, (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM labels WHERE kind = ?', [$kind])]);
            $existing = db()->lastInsertId();
        }
        $labelIds[] = (int)$existing;
    }
    $valid = array_map('intval', q('SELECT id FROM labels')->fetchAll(PDO::FETCH_COLUMN));
    q('DELETE FROM song_labels WHERE song_id = ?', [$sid]);
    foreach (array_unique(array_intersect($labelIds, $valid)) as $lid) q('INSERT INTO song_labels (song_id, label_id) VALUES (?, ?)', [$sid, $lid]);

    // variante existente: nume, ștergere
    $existingMedia = rows('SELECT * FROM song_media WHERE song_id = ?', [$sid]);
    $titles = (array)($_POST['media_title'] ?? []);
    $remove = array_map('intval', (array)($_POST['media_remove'] ?? []));
    foreach ($existingMedia as $m) {
        $mid = (int)$m['id'];
        if (in_array($mid, $remove, true)) {
            if ($m['file']) @unlink(media_dir() . '/' . $m['file']);
            q('DELETE FROM song_media WHERE id = ?', [$mid]);
            continue;
        }
        if (isset($titles[$mid])) q('UPDATE song_media SET title = ? WHERE id = ?', [mb_substr(trim((string)$titles[$mid]), 0, 120) ?: null, $mid]);
    }
    // linkuri YouTube noi
    $urls = (array)($_POST['yt_url'] ?? []);
    $ytTitles = (array)($_POST['yt_title'] ?? []);
    $sort = (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM song_media WHERE song_id = ?', [$sid]);
    foreach ($urls as $i => $u) {
        $u = trim((string)$u);
        if ($u === '') continue;
        $yid = youtube_id_from($u);
        if (!$yid) { $msgs[] = 'Linkul „' . mb_substr($u, 0, 60) . '” nu pare de pe YouTube.'; continue; }
        q('INSERT INTO song_media (song_id, kind, title, youtube_id, url, sort, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$sid, 'youtube', mb_substr(trim((string)($ytTitles[$i] ?? '')), 0, 120) ?: null, $yid, mb_substr($u, 0, 400), $sort++, now()]);
    }
    // înregistrări noi
    $files = normalize_files($_FILES['audio'] ?? null);
    $durations = (array)($_POST['audio_duration'] ?? []);
    foreach ($files as $i => $f) {
        if ($f['error'] === UPLOAD_ERR_NO_FILE) continue;
        if ($f['error'] !== UPLOAD_ERR_OK) { $msgs[] = '„' . $f['name'] . '” nu s-a încărcat (prea mare?).'; continue; }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $mimes = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg'];
        $real = function_exists('mime_content_type') ? (string)@mime_content_type($f['tmp_name']) : '';
        if (!isset($mimes[$ext]) || ($real !== '' && !str_starts_with($real, 'audio/') && !str_starts_with($real, 'video/mp4') && $real !== 'application/octet-stream')) {
            $msgs[] = '„' . $f['name'] . '” nu este un fișier audio (MP3, M4A).';
            continue;
        }
        $name = 's' . $sid . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], media_dir() . '/' . $name)) { $msgs[] = '„' . $f['name'] . '” nu a putut fi salvat.'; continue; }
        $title = mb_substr(trim(pathinfo($f['name'], PATHINFO_FILENAME)), 0, 120);
        $dur = isset($durations[$i]) && is_numeric($durations[$i]) ? (int)round((float)$durations[$i]) : null;
        q('INSERT INTO song_media (song_id, kind, title, file, mime, size, duration, sort, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$sid, 'audio', $title, $name, $mimes[$ext], (int)$f['size'], $dur, $sort++, now()]);
    }
    // varianta principală
    $main = (int)post('main_media');
    $ids = array_map('intval', q('SELECT id FROM song_media WHERE song_id = ? ORDER BY sort, id', [$sid])->fetchAll(PDO::FETCH_COLUMN));
    if (!in_array($main, $ids, true)) {
        $current = (int)val('SELECT id FROM song_media WHERE song_id = ? AND is_main = 1', [$sid]);
        $firstAudio = (int)val("SELECT id FROM song_media WHERE song_id = ? AND kind = 'audio' ORDER BY sort, id", [$sid]);
        $main = $current ?: ($firstAudio ?: ($ids[0] ?? 0));
    }
    q('UPDATE song_media SET is_main = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE song_id = ?', [$main, $sid]);

    // acorduri
    if (!empty($_POST['chords_remove'])) {
        $old = val('SELECT chords_file FROM songs WHERE id = ?', [$sid]);
        if ($old) @unlink(media_dir() . '/' . $old);
        q('UPDATE songs SET chords_file = NULL, chords_name = NULL WHERE id = ?', [$sid]);
    }
    $c = $_FILES['chords'] ?? null;
    if ($c && $c['error'] !== UPLOAD_ERR_NO_FILE) {
        $ext = strtolower(pathinfo($c['name'], PATHINFO_EXTENSION));
        if ($c['error'] !== UPLOAD_ERR_OK) $msgs[] = 'Fișierul cu acorduri nu s-a încărcat.';
        elseif (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) $msgs[] = 'Acordurile trebuie să fie PDF, JPG sau PNG.';
        else {
            $name = 'c' . $sid . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            if (move_uploaded_file($c['tmp_name'], media_dir() . '/' . $name)) {
                $old = val('SELECT chords_file FROM songs WHERE id = ?', [$sid]);
                if ($old) @unlink(media_dir() . '/' . $old);
                q('UPDATE songs SET chords_file = ?, chords_name = ? WHERE id = ?', [$name, mb_substr($c['name'], 0, 190), $sid]);
            }
        }
    }
    return $msgs;
}

function normalize_files(?array $f): array {
    if (!$f || !is_array($f['name'] ?? null)) return [];
    $out = [];
    foreach ($f['name'] as $i => $n) {
        $out[$i] = ['name' => $n, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

function song_delete(int $sid): void {
    foreach (rows('SELECT file FROM song_media WHERE song_id = ? AND file IS NOT NULL', [$sid]) as $m) @unlink(media_dir() . '/' . $m['file']);
    $c = val('SELECT chords_file FROM songs WHERE id = ?', [$sid]);
    if ($c) @unlink(media_dir() . '/' . $c);
    foreach (['song_media', 'song_labels', 'song_uses'] as $t) q("DELETE FROM $t WHERE song_id = ?", [$sid]);
    q('UPDATE event_items SET song_id = NULL, song_key = NULL WHERE song_id = ?', [$sid]);
    q('DELETE FROM songs WHERE id = ?', [$sid]);
}

function media_serve(string $id): void {
    require_user();
    $m = row("SELECT * FROM song_media WHERE id = ? AND kind = 'audio'", [(int)$id]);
    if (!$m || !$m['file'] || !is_file(media_dir() . '/' . $m['file'])) not_found();
    $song = row('SELECT title FROM songs WHERE id = ?', [$m['song_id']]);
    $ext = pathinfo($m['file'], PATHINFO_EXTENSION);
    serve_file(media_dir() . '/' . $m['file'], $m['mime'] ?: 'audio/mpeg', ($song['title'] ?? 'inregistrare') . ($m['title'] ? ' - ' . $m['title'] : '') . '.' . $ext);
}

function chords_serve(string $id): void {
    require_user();
    $s = row('SELECT * FROM songs WHERE id = ?', [(int)$id]);
    if (!$s || !$s['chords_file'] || !is_file(media_dir() . '/' . $s['chords_file'])) not_found();
    $ext = strtolower(pathinfo($s['chords_file'], PATHINFO_EXTENSION));
    $mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'][$ext] ?? 'application/octet-stream';
    serve_file(media_dir() . '/' . $s['chords_file'], $mime, $s['title'] . ' - acorduri.' . $ext);
}

/* ---------- tonuri (Setări) ---------- */

function settings_keys(): void {
    require_role('editor');
    if (is_post()) {
        csrf_check();
        $id = (int)post('id');
        $name = trim(preg_replace('/\s+/u', ' ', post('name')));
        $old = (string)val('SELECT name FROM song_keys WHERE id = ?', [$id]);
        $taken = fn(string $n, int $except = 0) => is_standard_key($n)
            || (int)val('SELECT COUNT(*) FROM song_keys WHERE LOWER(name) = LOWER(?) AND id <> ?', [$n, $except]) > 0;
        $tooLong = mb_strlen($name) > SONG_KEY_MAX;
        switch (post('action')) {
            case 'add':
                if ($name === '') break;
                if ($tooLong) { flash('Tonul poate avea cel mult ' . SONG_KEY_MAX . ' de caractere.', 'error'); break; }
                if ($taken($name)) { flash('Tonul „' . $name . '” există deja.', 'error'); break; }
                q('INSERT INTO song_keys (name, sort, created_at) VALUES (?, ?, ?)', [$name, (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM song_keys') , now()]);
                flash('Tonul „' . $name . '” a fost adăugat.');
                break;
            case 'rename':
                if ($old === '' || $name === '' || $name === $old) break;
                if ($tooLong) { flash('Tonul poate avea cel mult ' . SONG_KEY_MAX . ' de caractere.', 'error'); break; }
                if ($taken($name, $id)) { flash('Tonul „' . $name . '” există deja.', 'error'); break; }
                q('UPDATE song_keys SET name = ? WHERE id = ?', [$name, $id]);
                // cântările care foloseau tonul îl primesc pe cel nou (fără să schimbe „ultima modificare”)
                $n = q('UPDATE songs SET song_key = ? WHERE song_key = ?', [$name, $old])->rowCount();
                flash('Tonul a fost redenumit' . ($n ? " (la $n cântări)" : '') . '.');
                break;
            case 'up':
            case 'down':
                reorder('song_keys', $id, post('action') === 'up' ? -1 : 1);
                break;
            case 'delete':
                if ($old === '') break;
                $n = q('UPDATE songs SET song_key = NULL WHERE song_key = ?', [$old])->rowCount();
                q('DELETE FROM song_keys WHERE id = ?', [$id]);
                flash('Tonul „' . $old . '” a fost șters' . ($n ? "; $n cântări au rămas fără ton" : '') . '.');
                break;
        }
        redirect('setari/tonuri');
    }
    $keys = rows('SELECT k.id, k.name, k.sort, (SELECT COUNT(*) FROM songs s WHERE s.song_key = k.name) AS songs FROM song_keys k ORDER BY k.sort, k.id');
    render('settings/keys', ['nav' => 'setari', 'sub' => 'tonuri', 'keys' => $keys]);
}

/* ---------- import din Excel / CSV ---------- */

function songs_import(): void {
    $me = require_role('editor');
    if (is_post()) {
        csrf_check();
        $tok = post('f');
        if (post('action') === 'cancel') { import_forget($tok); redirect('cantari/import'); }
        if (post('action') === 'apply') {
            $d = import_load($tok, (int)$me['id']);
            if (!$d) { flash('Previzualizarea a expirat. Încarcă fișierul din nou.', 'error'); redirect('cantari/import'); }
            [$plan] = import_plan($d['songs']);
            $update = post('mode') === 'update';
            [$added, $updated] = import_apply($plan, $update, $me);
            import_forget($tok);
            $skipped = count($plan) - $added - $updated;
            log_activity('settings', null, 'Import cântări', 'import', 'A importat din „' . $d['file'] . '”: ' . $added . ' cântări noi' . ($update ? ', ' . $updated . ' actualizate' : '') . ($skipped ? ", $skipped sărite" : '') . '.');
            flash('Import gata: ' . $added . ' cântări noi' . ($update ? ', ' . $updated . ' actualizate' : '') . ($skipped ? ", $skipped sărite" : '') . '.');
            redirect('cantari?sort=noi');
        }
        // încărcarea fișierului
        $f = $_FILES['file'] ?? null;
        $err = null;
        if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) $err = 'Fișierul e prea mare pentru server.';
        elseif (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) $err = 'Alege un fișier.';
        elseif ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) $err = 'Fișierul nu s-a putut încărca.';
        elseif ($f['size'] > IMPORT_MAX_MB * 1048576) $err = 'Fișierul e prea mare (maximum ' . IMPORT_MAX_MB . ' MB).';
        if (!$err) {
            [$rows, $err] = import_read_file($f['tmp_name'], (string)$f['name']);
            if (!$err) {
                [$songs, $found, $err] = import_parse($rows);
                if (!$err) redirect('cantari/import?f=' . import_store($songs, $found, mb_substr(basename((string)$f['name']), 0, 120), (int)$me['id']));
            }
        }
        flash($err, 'error');
        redirect('cantari/import');
    }
    $d = isset($_GET['f']) ? import_load((string)$_GET['f'], (int)$me['id']) : null;
    if (isset($_GET['f']) && !$d) { flash('Previzualizarea a expirat. Încarcă fișierul din nou.', 'error'); redirect('cantari/import'); }
    $plan = $newBooks = [];
    if ($d) [$plan, $newBooks] = import_plan($d['songs']);
    render('songs/import', ['nav' => 'cantari', 'd' => $d, 'tok' => (string)($_GET['f'] ?? ''), 'plan' => $plan, 'newBooks' => $newBooks, 'canXlsx' => class_exists('ZipArchive')]);
}

function songs_import_template(string $ext): void {
    require_role('editor');
    if ($ext === 'xlsx' && ($tmp = import_template_xlsx())) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="model-cantari.xlsx"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
    if ($ext !== 'csv') not_found();
    $csv = import_template_csv();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="model-cantari.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

/* ---------- cărți de cântări (Setări) ---------- */

function settings_books(): void {
    require_role('editor');
    if (is_post()) {
        csrf_check();
        $id = (int)post('id');
        $name = trim(preg_replace('/\s+/u', ' ', post('name')));
        $old = (string)val('SELECT name FROM song_books WHERE id = ?', [$id]);
        $taken = fn(string $n, int $except = 0) => (int)val('SELECT COUNT(*) FROM song_books WHERE LOWER(name) = LOWER(?) AND id <> ?', [$n, $except]) > 0;
        $tooLong = mb_strlen($name) > SONG_BOOK_MAX;
        switch (post('action')) {
            case 'add':
                if ($name === '') break;
                if ($tooLong) { flash('Numele cărții poate avea cel mult ' . SONG_BOOK_MAX . ' de caractere.', 'error'); break; }
                if ($taken($name)) { flash('Cartea „' . $name . '” există deja.', 'error'); break; }
                q('INSERT INTO song_books (name, sort, created_at) VALUES (?, ?, ?)', [$name, (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM song_books'), now()]);
                flash('Cartea „' . $name . '” a fost adăugată.');
                break;
            case 'rename':
                if ($old === '' || $name === '' || $name === $old) break;
                if ($tooLong) { flash('Numele cărții poate avea cel mult ' . SONG_BOOK_MAX . ' de caractere.', 'error'); break; }
                if ($taken($name, $id)) { flash('Cartea „' . $name . '” există deja.', 'error'); break; }
                q('UPDATE song_books SET name = ? WHERE id = ?', [$name, $id]);
                $n = q('UPDATE songs SET source = ? WHERE source = ?', [$name, $old])->rowCount();
                flash('Cartea a fost redenumită' . ($n ? " (la $n cântări)" : '') . '.');
                break;
            case 'up':
            case 'down':
                reorder('song_books', $id, post('action') === 'up' ? -1 : 1);
                break;
            case 'delete':
                if ($old === '') break;
                $n = q('UPDATE songs SET source = NULL WHERE source = ?', [$old])->rowCount();
                q('DELETE FROM song_books WHERE id = ?', [$id]);
                flash('Cartea „' . $old . '” a fost ștearsă' . ($n ? "; $n cântări au rămas fără carte (numărul rămâne)" : '') . '.');
                break;
        }
        redirect('setari/carti');
    }
    $books = rows('SELECT b.id, b.name, b.sort, (SELECT COUNT(*) FROM songs s WHERE s.source = b.name) AS songs FROM song_books b ORDER BY b.sort, b.id');
    $other = rows('SELECT source, COUNT(*) AS n FROM songs WHERE source IS NOT NULL AND source <> \'\' AND source NOT IN (SELECT name FROM song_books) GROUP BY source ORDER BY source');
    render('settings/books', ['nav' => 'setari', 'sub' => 'carti', 'books' => $books, 'other' => $other]);
}

/* ---------- etichete (Setări) ---------- */

function settings_labels(): void {
    require_role('editor');
    if (is_post()) {
        csrf_check();
        $id = (int)post('id');
        $name = mb_substr(post('name'), 0, 60);
        $old = (string)val('SELECT name FROM labels WHERE id = ?', [$id]);
        $L = fn(string $t) => log_activity('settings', null, 'Etichete cântări', 'labels', $t);
        switch (post('action')) {
            case 'add':
                $kind = post('kind') === 'tema' ? 'tema' : 'ocazie';
                if ($name !== '' && !val('SELECT COUNT(*) FROM labels WHERE LOWER(name) = LOWER(?) AND kind = ?', [$name, $kind])) {
                    q('INSERT INTO labels (name, kind, sort) VALUES (?, ?, ?)', [$name, $kind, (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM labels WHERE kind = ?', [$kind])]);
                    $L('Etichetă nouă (' . ($kind === 'tema' ? 'temă' : 'ocazie') . "): $name.");
                }
                break;
            case 'rename':
                if ($name !== '' && $name !== $old) { q('UPDATE labels SET name = ? WHERE id = ?', [$name, $id]); $L("Etichetă redenumită: $old → $name."); }
                break;
            case 'up':
            case 'down':
                $kind = (string)val('SELECT kind FROM labels WHERE id = ?', [$id]);
                reorder('labels', $id, post('action') === 'up' ? -1 : 1, "kind = " . db()->quote($kind));
                break;
            case 'delete':
                $n = (int)val('SELECT COUNT(*) FROM song_labels WHERE label_id = ?', [$id]);
                q('DELETE FROM song_labels WHERE label_id = ?', [$id]);
                q('DELETE FROM labels WHERE id = ?', [$id]);
                if ($old !== '') $L("Etichetă ștearsă: $old" . ($n ? " (era pe $n cântări)" : '') . '.');
                flash('Eticheta a fost ștearsă.');
                break;
        }
        redirect('setari/etichete');
    }
    render('settings/labels', ['nav' => 'setari', 'sub' => 'etichete', 'labels' => labels_all()]);
}
