<?php
declare(strict_types=1);
// Ordinea serviciului, mesajul WhatsApp și calendarul personal.

const ITEM_KINDS = ['item' => 'Element', 'song' => 'Cântare'];

/** Elementele din ordinea unui eveniment, cu datele cântării (dacă e aleasă). */
function event_items(int $eid): array {
    $items = rows('SELECT i.*, s.title AS song_title, s.source, s.source_no, s.song_key AS default_key, s.chords_file
        FROM event_items i LEFT JOIN songs s ON s.id = i.song_id WHERE i.event_id = ? ORDER BY i.sort, i.id', [$eid]);
    // înregistrarea de ascultat: cea „Principală”, altfel prima (MP3 înaintea YouTube, ca pe pagina cântării)
    $ids = array_values(array_unique(array_filter(array_map(fn($i) => (int)$i['song_id'], $items))));
    $media = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (rows("SELECT * FROM song_media WHERE song_id IN ($in) AND (kind = 'audio' OR (kind = 'youtube' AND youtube_id IS NOT NULL)) ORDER BY is_main DESC, CASE kind WHEN 'audio' THEN 0 ELSE 1 END, sort, id", $ids) as $m) {
            $media[(int)$m['song_id']][] = $m;
        }
    }
    foreach ($items as &$it) {
        $list = $media[(int)$it['song_id']] ?? [];
        $it['media'] = $list[0] ?? null;
        $it['more'] = max(0, count($list) - 1);
    }
    return $items;
}

function copy_template_items(int $tid, int $eid): void {
    foreach (rows('SELECT * FROM template_items WHERE template_id = ? ORDER BY sort, id', [$tid]) as $i => $it) {
        q('INSERT INTO event_items (event_id, sort, kind, title, uid) VALUES (?, ?, ?, ?, ?)', [$eid, $i, $it['kind'], $it['title'], new_item_uid()]);
    }
}

/** „Titlu (CR 4)” pentru o cântare. */
function item_song_ref(array $it): string {
    if (empty($it['song_title'])) return '';
    return $it['song_title'] . (!empty($it['source']) ? ' (' . $it['source'] . (!empty($it['source_no']) ? ' ' . $it['source_no'] : '') . ')' : '');
}
function item_key(array $it): string {
    return (string)($it['song_key'] ?: ($it['default_key'] ?? ''));
}

/** Salvează ordinea trimisă din formular. Întoarce un rezumat scurt pentru istoric (sau '' dacă nu s-a schimbat nimic). */
function save_event_items(array $e, array $post, bool $pastor = false): string {
    $eid = (int)$e['id'];
    $before = event_items($eid);
    $ids = (array)($post['it_id'] ?? []);
    $byId = [];
    foreach ($before as $b) $byId[(int)$b['id']] = $b;
    $usedUid = [];
    // fiecare element își păstrează cheia (uid), ca fișierele atașate să rămână la el
    $uidFor = function (int $i) use ($ids, $byId, &$usedUid): string {
        $id = (int)($ids[$i] ?? 0);
        $u = (string)($byId[$id]['uid'] ?? '');
        if ($u === '' || isset($usedUid[$u])) $u = new_item_uid();
        $usedUid[$u] = true;
        return $u;
    };
    // pastorul doar mută cântările: nu le poate scoate, schimba sau adăuga
    $locked = [];
    if ($pastor) foreach ($before as $b) if ($b['kind'] === 'song') $locked[(int)$b['id']] = $b;
    $kinds = (array)($post['it_kind'] ?? []);
    $titles = (array)($post['it_title'] ?? []);
    $songs = (array)($post['it_song'] ?? []);
    $keys = (array)($post['it_key'] ?? []);
    $notes = (array)($post['it_note'] ?? []);
    $new = [];
    foreach ($kinds as $i => $k) {
        $kind = $k === 'song' ? 'song' : 'item';
        if ($pastor && $kind === 'song') {
            $lid = (int)($ids[$i] ?? 0);
            if (!isset($locked[$lid])) continue;
            $b = $locked[$lid];
            unset($locked[$lid]);
            $new[] = ['kind' => 'song', 'title' => $b['title'], 'song_id' => $b['song_id'] ?: null, 'song_key' => $b['song_key'] ?: null, 'note' => $b['note'] ?: null, 'uid' => $uidFor($i)];
            continue;
        }
        $title = mb_substr(trim((string)($titles[$i] ?? '')), 0, 120);
        $sid = (int)($songs[$i] ?? 0);
        if ($sid && !val('SELECT COUNT(*) FROM songs WHERE id = ?', [$sid])) $sid = 0;
        if ($kind === 'item') $sid = 0;
        if ($title === '') $title = $kind === 'song' ? 'Cântare' : '';
        if ($title === '') continue;
        $key = mb_substr(trim((string)($keys[$i] ?? '')), 0, SONG_KEY_MAX);
        $note = mb_substr(trim((string)($notes[$i] ?? '')), 0, 190);
        $new[] = ['kind' => $kind, 'title' => $title, 'song_id' => $sid ?: null, 'song_key' => ($sid && $key !== '') ? $key : null, 'note' => $note !== '' ? $note : null, 'uid' => $uidFor($i)];
    }
    foreach ($locked as $b) $new[] = ['kind' => 'song', 'title' => $b['title'], 'song_id' => $b['song_id'] ?: null, 'song_key' => $b['song_key'] ?: null, 'note' => $b['note'] ?: null, 'uid' => $b['uid'] ?: new_item_uid()];
    $sig = fn($l) => array_map(fn($x) => [$x['kind'], $x['title'], $x['song_id'] ? (int)$x['song_id'] : null, $x['song_key'] ?: null, $x['note'] ?: null], $l);
    if ($sig($before) === $sig($new)) return '';
    q('DELETE FROM event_items WHERE event_id = ?', [$eid]);
    foreach ($new as $i => $x) {
        q('INSERT INTO event_items (event_id, sort, kind, title, song_id, song_key, note, uid) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$eid, $i, $x['kind'], $x['title'], $x['song_id'], $x['song_key'], $x['note'], $x['uid']]);
    }
    // fișierele elementelor scoase din ordine se șterg odată cu ele
    $keep = array_column($new, 'uid');
    $gone = [];
    foreach (rows('SELECT * FROM event_files WHERE event_id = ?', [$eid]) as $f) {
        if (!in_array($f['item_uid'], $keep, true)) { delete_event_file($f); $gone[] = $f['name']; }
    }
    sync_song_uses($e);
    // rezumat: ce cântări s-au adăugat / scos
    $names = fn($l) => array_values(array_filter(array_map(fn($x) => $x['song_id'] ? (string)val('SELECT title FROM songs WHERE id = ?', [$x['song_id']]) : '', $l)));
    $b = $names($before); $a = $names($new);
    $parts = [];
    $added = array_values(array_diff($a, $b)); $removed = array_values(array_diff($b, $a));
    if ($added) $parts[] = 'a adăugat ' . implode(', ', array_map(fn($t) => '„' . $t . '”', $added));
    if ($removed) $parts[] = 'a scos ' . implode(', ', array_map(fn($t) => '„' . $t . '”', $removed));
    if ($gone) $parts[] = 'au fost șterse fișierele ' . implode(', ', array_map(fn($t) => '„' . $t . '”', $gone));
    return 'A modificat ordinea serviciului' . ($parts ? ': ' . implode('; ', $parts) : '') . '.';
}

/** Cântările din ordine apar la „Când s-a cântat” (doar după ce trece data). */
function sync_song_uses(array $e): void {
    $eid = (int)$e['id'];
    q('DELETE FROM song_uses WHERE event_id = ?', [$eid]);
    if ($e['status'] === 'cancelled') return;
    $slot = (int)substr($e['start_time'], 0, 2) >= 14 ? 'S' : 'D';
    $seen = [];
    foreach (rows('SELECT song_id FROM event_items WHERE event_id = ? AND song_id IS NOT NULL ORDER BY sort', [$eid]) as $r) {
        if (isset($seen[$r['song_id']])) continue;
        $seen[$r['song_id']] = true;
        q('INSERT INTO song_uses (song_id, used_on, slot, note, event_id) VALUES (?, ?, ?, ?, ?)', [$r['song_id'], $e['event_date'], $slot, mb_substr($e['name'], 0, 40), $eid]);
    }
}

/** Lista de cântări pentru căutarea din ordinea serviciului. */
function song_choices(): array {
    return rows('SELECT id, title, author, source, source_no, song_key, lyrics FROM songs ORDER BY title');
}

/* ---------- Mesajul WhatsApp ---------- */

function first_name_of(string $n): string { return preg_split('/\s+/u', trim($n))[0] ?? $n; }

function whatsapp_text(array $e): string {
    $tpl = setting('whatsapp_template', "*{eveniment} – {data}, {ora}*\nRepetiție: {repetitie}\n\n*Program*\n{ordinea}\n\n*Echipa*\n{echipa}\n\nÎnregistrări: {link}");
    $withKey = setting('whatsapp_include_key', '1') === '1';
    $withItems = setting('whatsapp_include_items', '1') === '1';
    $first = setting('whatsapp_first_names', '1') === '1';
    $items = event_items((int)$e['id']);
    $files = event_files((int)$e['id']);
    $order = []; $songs = []; $n = 0; $loose = [];
    // linkul secret descarcă direct fișierul, fără autentificare
    $fileLines = fn(array $it) => array_map(fn($f) => '   📎 ' . $f['name'] . ': ' . full_url('f/' . $f['token']), $files[$it['uid'] ?? ''] ?? []);
    foreach ($items as $it) {
        if ($it['kind'] === 'song') {
            $ref = item_song_ref($it);
            $key = $withKey ? item_key($it) : '';
            $line = ($ref !== '' ? $ref : $it['title'] . ' (de ales)') . ($key !== '' ? ' – ' . $key : '') . ($it['note'] ? ' · ' . $it['note'] : '');
            $order[] = (++$n) . '. 🎵 ' . $line;
            if ($ref !== '') $songs[] = '• ' . $ref . ($key !== '' ? ' – ' . $key : '');
        } elseif ($withItems) {
            // doar cântările primesc număr (continuu prin tot serviciul)
            $order[] = $it['title'] . ($it['note'] ? ' – ' . $it['note'] : '');
            array_push($order, ...$fileLines($it));
        } else {
            array_push($loose, ...$fileLines($it));
        }
    }
    $team = [];
    foreach (slot_labels(event_slots((int)$e['id'])) as $s) {
        if (!$s['user_id'] || $s['response'] === 'declined') continue;
        $team[] = $s['label'] . ': ' . ($first ? first_name_of($s['user_name']) : $s['user_name']);
    }
    $t = strtotime($e['event_date']);
    $vars = [
        '{eveniment}' => $e['name'],
        '{data}' => RO_DAYS[(int)date('w', $t)] . ', ' . date('j', $t) . ' ' . RO_MONTHS[(int)date('n', $t) - 1],
        '{ora}' => $e['start_time'],
        '{repetitie}' => (string)($e['rehearsal_time'] ?? ''),
        '{ordinea}' => implode("\n", $loose ? array_merge($order, ['', '📎 Fișiere:'], $loose) : $order),
        '{cantari}' => implode("\n", $songs),
        '{echipa}' => implode("\n", $team),
        '{notite}' => trim((string)($e['notes'] ?? '')),
        '{link}' => full_url('program/' . (int)$e['id']),
    ];
    $out = [];
    foreach (explode("\n", str_replace("\r\n", "\n", $tpl)) as $line) {
        preg_match_all('/\{[a-z]+\}/', $line, $m);
        $used = array_filter($m[0], fn($v) => isset($vars[$v]));
        if ($used && !array_filter($used, fn($v) => $vars[$v] !== '')) continue; // rând cu câmpuri goale
        $out[] = strtr($line, $vars);
    }
    $text = preg_replace("/\n{3,}/", "\n\n", implode("\n", $out));
    // un titlu (*Program*) rămas fără nimic sub el dispare
    $text = preg_replace("/(^|\n)\*[^*\n]+\*\n(?=\n|$)/u", '$1', $text);
    return trim(preg_replace("/\n{3,}/", "\n\n", $text));
}

/* ---------- Calendarul personal (.ics) ---------- */

function calendar_token(array $u, bool $renew = false): string {
    $tok = (string)($u['calendar_token'] ?? '');
    if ($renew || !preg_match('/^[a-f0-9]{40}$/', $tok)) {
        $tok = bin2hex(random_bytes(20));
        q('UPDATE users SET calendar_token = ? WHERE id = ?', [$tok, $u['id']]);
    }
    return $tok;
}

function ics_escape(string $s): string {
    return str_replace(["\\", ";", ",", "\r\n", "\n"], ["\\\\", "\\;", "\\,", "\\n", "\\n"], $s);
}
/** Liniile mai lungi de 75 de octeți se împart, cum cere formatul iCalendar. */
function ics_fold(string $line): string {
    $out = '';
    $max = 75; // primul rând 75 de octeți, următoarele 74 + spațiul de la început
    while (strlen($line) > $max) {
        $cut = $max;
        while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) $cut--; // nu tăiem în mijlocul unei litere
        $out .= substr($line, 0, $cut) . "\r\n ";
        $line = substr($line, $cut);
        $max = 74;
    }
    return $out . $line;
}
function ics_utc(string $date, string $time, int $addMin = 0): string {
    $ts = strtotime($date . ' ' . $time . ':00') + $addMin * 60;
    return gmdate('Ymd\THis\Z', $ts);
}

function calendar_ics(array $u): string {
    $from = date('Y-m-d', strtotime('-60 days'));
    $rows = rows("SELECT s.id AS slot_id, s.response, p.name AS position, e.* FROM event_slots s JOIN events e ON e.id = s.event_id JOIN positions p ON p.id = s.position_id
        WHERE s.user_id = ? AND e.status = 'published' AND e.event_date >= ? AND (s.response IS NULL OR s.response <> 'declined') ORDER BY e.event_date, e.start_time", [$u['id'], $from]);
    $byEvent = [];
    foreach ($rows as $r) { $byEvent[$r['id']]['e'] = $r; $byEvent[$r['id']]['pos'][] = $r['position']; $byEvent[$r['id']]['pending'][] = $r['response'] === null; }
    $host = parse_url(full_url(''), PHP_URL_HOST) ?: 'b1';
    $app = app_name();
    $L = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Echipa de voluntari//RO', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
        'X-WR-CALNAME:' . ics_escape($app . ' · ' . first_name_of($u['name'])), 'X-WR-TIMEZONE:Europe/Bucharest', 'REFRESH-INTERVAL;VALUE=DURATION:PT6H', 'X-PUBLISHED-TTL:PT6H'];
    foreach ($byEvent as $eid => $x) {
        $e = $x['e'];
        $start = ($e['rehearsal_time'] && $e['rehearsal_time'] < $e['start_time']) ? $e['rehearsal_time'] : $e['start_time'];
        $desc = 'Poziția: ' . implode(', ', array_unique($x['pos']));
        if ($e['rehearsal_time']) $desc .= "\nRepetiție: " . $e['rehearsal_time'] . ' · Început: ' . $e['start_time'];
        if (in_array(true, $x['pending'], true)) $desc .= "\nNeconfirmat – răspunde în aplicație.";
        $songs = array_filter(array_map('item_song_ref', array_filter(event_items((int)$eid), fn($i) => $i['kind'] === 'song')));
        if ($songs) $desc .= "\n\nCântări:\n" . implode("\n", $songs);
        if ($e['notes']) $desc .= "\n\n" . $e['notes'];
        $desc .= "\n\n" . full_url('program/' . (int)$eid);
        $L[] = 'BEGIN:VEVENT';
        $L[] = 'UID:b1-event-' . (int)$eid . '-user-' . (int)$u['id'] . '@' . $host;
        $L[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z', strtotime($e['updated_at']));
        $L[] = 'DTSTART:' . ics_utc($e['event_date'], $start);
        $L[] = 'DTEND:' . ics_utc($e['event_date'], $e['start_time'], 120);
        $L[] = 'SUMMARY:' . ics_escape($e['name'] . ' – ' . implode(', ', array_unique($x['pos'])));
        $L[] = 'DESCRIPTION:' . ics_escape($desc);
        $L[] = 'URL:' . full_url('program/' . (int)$eid);
        if (setting('church_name', '') !== '') $L[] = 'LOCATION:' . ics_escape(setting('church_name', ''));
        $L[] = 'END:VEVENT';
    }
    $L[] = 'END:VCALENDAR';
    return implode("\r\n", array_map('ics_fold', $L)) . "\r\n";
}

/* ---------- Fișiere atașate elementelor din ordine ---------- */

const EVENT_FILE_EXT = ['pdf' => 'application/pdf', 'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'key' => 'application/vnd.apple.keynote', 'odp' => 'application/vnd.oasis.opendocument.presentation'];
const EVENT_FILE_MAX_MB = 60;
/** Limita reală: 60 MB sau cât permite serverul, dacă e mai puțin. */
function event_file_max_mb(): int { return max(1, min(EVENT_FILE_MAX_MB, upload_limit_mb())); }

function new_item_uid(): string { return bin2hex(random_bytes(8)); }

/** Fișierele unui eveniment, grupate după elementul din ordine. */
function event_files(int $eid): array {
    $out = [];
    foreach (rows('SELECT * FROM event_files WHERE event_id = ? ORDER BY id', [$eid]) as $f) $out[$f['item_uid']][] = $f;
    return $out;
}
function delete_event_file(array $f): void {
    @unlink(media_dir() . '/' . $f['file']);
    q('DELETE FROM event_files WHERE id = ?', [$f['id']]);
}
function file_icon_label(string $name): string {
    return strtoupper(pathinfo($name, PATHINFO_EXTENSION)) ?: 'FIȘIER';
}
/** Salvează un fișier încărcat pentru un element. Întoarce [fișier, ''] sau [null, mesaj de eroare]. */
function store_event_file(int $eid, string $uid, array $f, int $by): array {
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) return [null, 'Fișierul e prea mare (maxim ' . event_file_max_mb() . ' MB).'];
    if ($f['error'] !== UPLOAD_ERR_OK) return [null, 'Fișierul nu s-a încărcat. Încearcă din nou.'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!isset(EVENT_FILE_EXT[$ext])) return [null, 'Poți atașa doar PowerPoint, PDF, Keynote sau ODP.'];
    if ($f['size'] > event_file_max_mb() * 1048576) return [null, 'Fișierul e prea mare (maxim ' . event_file_max_mb() . ' MB).'];
    $real = function_exists('mime_content_type') ? (string)@mime_content_type($f['tmp_name']) : '';
    if ($real !== '' && (str_starts_with($real, 'text/') || str_contains($real, 'php') || str_contains($real, 'executable') || str_contains($real, 'html'))) return [null, 'Fișierul nu pare a fi o prezentare sau un PDF.'];
    $name = 'f' . $eid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], media_dir() . '/' . $name)) return [null, 'Fișierul nu a putut fi salvat pe server.'];
    $orig = preg_replace('/[\x00-\x1F\/\\\\]+/u', '', (string)$f['name']);
    $orig = mb_substr($orig !== '' ? $orig : 'fisier.' . $ext, 0, 190);
    q('INSERT INTO event_files (event_id, item_uid, file, name, size, token, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$eid, $uid, $name, $orig, (int)$f['size'], bin2hex(random_bytes(20)), $by, now()]);
    return [row('SELECT * FROM event_files WHERE id = ?', [(int)db()->lastInsertId()]), ''];
}
