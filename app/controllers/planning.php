<?php
declare(strict_types=1);

/* ---------- Planificare: lista evenimentelor următoare ---------- */

function planning_index(): void {
    require_planner();
    $weeks = max(4, min(26, (int)($_GET['saptamani'] ?? 8)));
    $from = date('Y-m-d');
    $to = date('Y-m-d', strtotime("+$weeks weeks"));
    ensure_events($from, date('Y-m-t', strtotime($to)));
    $events = rows("SELECT * FROM events WHERE event_date BETWEEN ? AND ? AND status <> 'cancelled' ORDER BY event_date, start_time", [$from, $to]);
    $stats = event_stats(array_column($events, 'id'));
    $byMonth = [];
    foreach ($events as $e) $byMonth[substr($e['event_date'], 0, 7)][] = $e;
    render('planning/index', ['nav' => 'planificare', 'byMonth' => $byMonth, 'stats' => $stats, 'weeks' => $weeks, 'pastor' => is_pastor()]);
}

/** Pentru fiecare eveniment: locuri, ocupate, așteaptă, refuzuri, neanunțați. */
function event_stats(array $ids): array {
    $out = [];
    foreach ($ids as $id) $out[(int)$id] = ['total' => 0, 'filled' => 0, 'pending' => 0, 'declined' => 0, 'accepted' => 0, 'unnotified' => 0];
    if (!$ids) return $out;
    $in = implode(',', array_map('intval', $ids));
    foreach (rows("SELECT s.event_id, s.user_id, s.response, s.notified_at FROM event_slots s WHERE s.event_id IN ($in)") as $s) {
        $o = &$out[(int)$s['event_id']];
        $o['total']++;
        if ($s['user_id'] && $s['response'] !== 'declined') $o['filled']++;
        if ($s['response'] === 'declined') $o['declined']++;
        if ($s['user_id'] && $s['response'] === 'accepted') $o['accepted']++;
        if ($s['user_id'] && $s['response'] === null) { $o['pending']++; if (!$s['notified_at']) $o['unnotified']++; }
        unset($o);
    }
    return $out;
}

/* ---------- Planificare: luna, tabel poziții × evenimente ---------- */

function planning_month(): void {
    require_role('editor');
    $ym = (string)($_GET['l'] ?? '');
    if (!valid_date($ym . '-01')) $ym = date('Y-m');
    [$a, $b] = month_bounds($ym);
    ensure_events($a, $b);
    $events = rows("SELECT * FROM events WHERE event_date BETWEEN ? AND ? AND status <> 'cancelled' ORDER BY event_date, start_time", [$a, $b]);
    // Filtru: toate / duminică / joi. Alegerea se ține minte pe acest dispozitiv.
    $zi = (string)($_GET['zi'] ?? ($_COOKIE['b1_zi'] ?? 'toate'));
    if (!in_array($zi, ['toate', 'duminica', 'joi'], true)) $zi = 'toate';
    if (isset($_GET['zi'])) setcookie('b1_zi', $zi, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true]);
    if ($zi !== 'toate') {
        $wd = $zi === 'duminica' ? 0 : 4;
        $events = array_values(array_filter($events, fn($e) => (int)date('w', strtotime($e['event_date'])) === $wd));
    }
    $cells = [];
    $posUsed = [];
    foreach ($events as $e) {
        foreach (slot_labels(event_slots((int)$e['id'])) as $s) {
            $key = $s['position_id'];
            $posUsed[$key] = max($posUsed[$key] ?? 0, 0);
            $cells[$e['id']][$key][] = $s;
            $posUsed[$key] = max($posUsed[$key], count($cells[$e['id']][$key]));
        }
    }
    $load = workload($ym);
    render('planning/month', [
        'nav' => 'planificare', 'ym' => $ym, 'zi' => $zi, 'events' => $events, 'cells' => $cells, 'posUsed' => $posUsed,
        'tree' => position_tree(true), 'load' => $load,
    ]);
}

/* ---------- Eveniment nou (special) ---------- */

function event_new(): void {
    $me = require_role('editor');
    $errors = [];
    $tpls = rows('SELECT * FROM templates WHERE active = 1 ORDER BY sort, id');
    if (is_post()) {
        csrf_check();
        $name = post('name');
        $date = post('date');
        $time = post('time');
        $reh = post('rehearsal');
        $tid = (int)post('template_id');
        if (mb_strlen($name) < 2) $errors['name'] = 'Scrie numele evenimentului.';
        if (!valid_date($date)) $errors['date'] = 'Alege data.';
        if (!valid_time($time)) $errors['time'] = 'Alege ora.';
        if ($reh !== '' && !valid_time($reh)) $errors['rehearsal'] = 'Ora repetiției nu e corectă.';
        if (!$errors) {
            $t = $tid ? row('SELECT * FROM templates WHERE id = ?', [$tid]) : null;
            $eid = event_create_from_template($t, $date, (int)$me['id'], $name, $time, $reh ?: null);
            if ($t) q('UPDATE events SET template_id = NULL WHERE id = ?', [$eid]); // evenimentul special nu blochează generarea automată
            log_activity('event', $eid, $name . ' ' . $date, 'created', 'A creat evenimentul special pentru ' . ro_date($date, true) . ', ora ' . $time . ($t ? ', pornind de la șablonul „' . $t['name'] . '”' : '') . '.');
            flash('Evenimentul a fost creat ca ciornă.');
            redirect('planificare/eveniment/' . $eid);
        }
    }
    render('planning/new', ['nav' => 'planificare', 'tpls' => $tpls, 'errors' => $errors]);
}

/* ---------- Eveniment: echipa ---------- */

function event_edit(string $id): void {
    $me = require_planner();
    $pastor = is_pastor();
    $e = row("SELECT * FROM events WHERE id = ? AND status <> 'cancelled'", [(int)$id]);
    if (!$e) not_found();
    $eid = (int)$e['id'];
    $ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    if (is_post()) {
        csrf_check();
        // pastorul: doar ordinea serviciului și notițele
        if ($pastor && !in_array(post('action'), ['items', 'notes', 'file_add', 'file_delete'], true)) forbidden();
        $back = event_action($e, $me, post('action'));
        if ($ajax) {
            header('Content-Type: application/json');
            $msgs = flash();
            echo json_encode(['ok' => !array_filter($msgs, fn($m) => $m[0] === 'error'), 'messages' => array_column($msgs, 1)]);
            return;
        }
        redirect($back ?? 'planificare/eveniment/' . $eid);
    }
    $ym = substr($e['event_date'], 0, 7);
    [$a, $b] = month_bounds($ym);
    $slots = slot_labels(event_slots($eid));
    $load = workload($ym);
    $monthEvents = rows("SELECT id, name, event_date, start_time, status FROM events WHERE event_date BETWEEN ? AND ? AND status <> 'cancelled' ORDER BY event_date, start_time", [$a, $b]);
    $stats = event_stats(array_column($monthEvents, 'id'));

    $pick = null;
    $cands = [];
    $pickId = (int)($_GET['alege'] ?? 0);
    foreach ($slots as $s) if ((int)$s['id'] === $pickId) $pick = $s;
    if ($pick && $pastor) $pick = null;
    if ($pick) $cands = candidates($e, $pick, $slots, $load);
    if ($ajax && $pick) { // fereastra de alegere din tabelul lunii
        render_partial('planning/_picker', ['e' => $e, 'pick' => $pick, 'cands' => $cands, 'me' => $me, 'popup' => true]);
        return;
    }

    $usedPos = array_unique(array_map(fn($s) => (int)$s['position_id'], $slots));
    $allPos = [];
    foreach (position_tree(true) as $g) foreach ($g['positions'] as $p) $allPos[] = $p + ['group' => $g['name']];
    render('planning/event', [
        'nav' => 'planificare', 'e' => $e, 'slots' => $slots, 'load' => $load, 'me' => $me,
        'monthEvents' => $monthEvents, 'stats' => $stats, 'pick' => $pick, 'cands' => $cands, 'allPos' => $allPos, 'usedPos' => $usedPos,
        'tpl' => $e['template_id'] ? row('SELECT name FROM templates WHERE id = ?', [$e['template_id']]) : null,
        'blocked' => blocked_on($e['event_date']),
        'history' => $pastor ? [] : activity_for('event', $eid),
        'items' => event_items($eid), 'files' => event_files($eid), 'songChoices' => $pastor ? [] : song_choices(), 'waText' => whatsapp_text($e), 'pastor' => $pastor,
    ]);
}

/** Oamenii care pot fi aleși pe un loc. Ordinea: disponibili, fără conflicte, fără prea multe duminici la rând, cei mai puțin folosiți, cei care n-au slujit de mult. */
function candidates(array $e, array $slot, array $slots, array $load): array {
    $isLeader = (int)$slot['position_id'] === leader_position_id();
    // liderul: doar cine are eticheta „Lider”; celelalte poziții, doar cine le are în profil
    $people = $isLeader
        ? rows("SELECT u.id, u.name, u.avatar, u.is_test FROM users u WHERE u.status = 'active' AND u.is_leader = 1 ORDER BY u.name")
        : rows("SELECT u.id, u.name, u.avatar, u.is_test FROM users u JOIN user_positions up ON up.user_id = u.id WHERE up.position_id = ? AND u.status = 'active' ORDER BY u.name", [$slot['position_id']]);
    $blocked = blocked_on($e['event_date']);
    $isSunday = (int)date('w', strtotime($e['event_date'])) === 0;
    $sundays = $isSunday ? sundays_served($e['event_date']) : [];
    $sameDay = [];
    foreach (rows("SELECT s.user_id, e.name, e.start_time FROM event_slots s JOIN events e ON e.id = s.event_id WHERE e.event_date = ? AND e.id <> ? AND e.status <> 'cancelled' AND s.user_id IS NOT NULL AND (s.response IS NULL OR s.response <> 'declined')", [$e['event_date'], $e['id']]) as $r) {
        $sameDay[(int)$r['user_id']][] = $r['name'] . ' ' . $r['start_time'];
    }
    $inEvent = [];
    // „Lider” se poate cumula cu o singură altă poziție: lideratul nu blochează, iar pentru lider nu blochează nimic
    if (!$isLeader) foreach ($slots as $s) if ($s['user_id'] && (int)$s['id'] !== (int)$slot['id'] && $s['response'] !== 'declined' && empty($s['is_leader'])) $inEvent[(int)$s['user_id']] = $s['label'];
    $already = []; // deja la acest eveniment (pe alt loc): duminica e numărată o singură dată
    foreach ($slots as $s) if ($s['user_id'] && (int)$s['id'] !== (int)$slot['id'] && $s['response'] !== 'declined') $already[(int)$s['user_id']] = true;
    $last = [];
    foreach (rows("SELECT s.user_id, MAX(e.event_date) AS d FROM event_slots s JOIN events e ON e.id = s.event_id WHERE e.event_date < ? AND e.status <> 'cancelled' AND s.user_id IS NOT NULL AND (s.response IS NULL OR s.response <> 'declined') GROUP BY s.user_id", [$e['event_date']]) as $r) {
        $last[(int)$r['user_id']] = $r['d'];
    }
    $declinedHere = [];
    foreach ($slots as $s) if ($s['response'] === 'declined' && $s['user_id']) $declinedHere[(int)$s['user_id']] = true;

    $out = [];
    foreach ($people as $p) {
        $uid = (int)$p['id'];
        if (isset($inEvent[$uid])) continue; // nimeni nu poate fi pe două poziții la același eveniment
        $n = $load[$uid] ?? 0;
        $notes = [];
        $why = null;
        $warn = 0;
        if (isset($blocked[$uid])) {
            $bk = $blocked[$uid];
            $why = 'Indisponibil' . ($bk['start_date'] === $bk['end_date'] ? ' pe ' . ro_date($bk['start_date']) : ' ' . ro_date($bk['start_date']) . ' – ' . ro_date($bk['end_date'])) . ($bk['reason'] ? ' · ' . $bk['reason'] : '');
        } elseif (isset($declinedHere[$uid])) {
            $why = 'A spus că nu poate la acest eveniment';
        }
        $streak = 0;
        if ($isSunday) {
            $dates = $sundays[$uid] ?? [];
            if ((int)$slot['user_id'] === $uid || isset($already[$uid])) unset($dates[$e['event_date']]);
            $streak = sunday_streak($dates, $e['event_date']);
            if ($streak >= STREAK_WARN) { $notes[] = ['A ' . $streak . '-a duminică la rând', $streak >= STREAK_MAX ? 'bad' : 'warn']; $warn = max($warn, $streak >= STREAK_MAX ? 2 : 1); }
            $after = $n + (((int)$slot['user_id'] === $uid || isset($already[$uid])) ? 0 : 1);
            if ($after >= 3) { $notes[] = ['Ar fi a ' . $after . '-a duminică luna aceasta', $after >= 4 ? 'bad' : 'warn']; $warn = max($warn, $after >= 4 ? 2 : 1); }
        }
        if (isset($sameDay[$uid])) { $notes[] = ['Programat în aceeași zi: ' . implode(', ', $sameDay[$uid]), 'warn']; $warn = max($warn, 1); }
        $notes[] = [isset($last[$uid]) ? 'Ultima dată: ' . event_short_date($last[$uid]) . ' ' . RO_MONTHS_SHORT[(int)substr($last[$uid], 5, 2) - 1] : 'Nu a slujit încă', 'muted'];
        $out[] = $p + ['count' => $n, 'notes' => $notes, 'why' => $why, 'warn' => $warn, 'streak' => $streak,
            'last' => $last[$uid] ?? '', 'parts' => user_part_names($uid, (int)$slot['position_id']), 'current' => (int)$slot['user_id'] === $uid];
    }
    usort($out, fn($x, $y) => [(int)($x['why'] !== null), $x['warn'], $x['count'], $x['last'], $x['name']] <=> [(int)($y['why'] !== null), $y['warn'], $y['count'], $y['last'], $y['name']]);
    foreach ($out as $i => &$c) $c['rec'] = $i === 0 && $c['why'] === null && $c['warn'] === 0 && !$c['current'];
    return $out;
}

function event_action(array $e, array $me, string $action): ?string {
    $eid = (int)$e['id'];
    $sid = (int)post('slot_id');
    $slot = $sid ? row('SELECT * FROM event_slots WHERE id = ? AND event_id = ?', [$sid, $eid]) : null;
    $touch = fn() => q('UPDATE events SET updated_at = ? WHERE id = ?', [now(), $eid]);
    $label = $e['name'] . ' ' . $e['event_date'];
    $posName = fn($pid) => (string)val('SELECT name FROM positions WHERE id = ?', [$pid]);
    switch ($action) {
        case 'assign':
            if (!$slot) return null;
            $uid = (int)post('user_id');
            $lead = (int)$slot['position_id'] === leader_position_id();
            $ok = $lead
                ? val("SELECT COUNT(*) FROM users WHERE id = ? AND status = 'active' AND is_leader = 1", [$uid])
                : val("SELECT COUNT(*) FROM users u JOIN user_positions up ON up.user_id = u.id WHERE u.id = ? AND up.position_id = ? AND u.status = 'active'", [$uid, $slot['position_id']]);
            if (!$ok) { flash('Persoana nu are această poziție în profil.', 'error'); return null; }
            $dup = $lead ? 0 : val("SELECT COUNT(*) FROM event_slots WHERE event_id = ? AND user_id = ? AND id <> ? AND position_id <> ? AND (response IS NULL OR response <> 'declined')", [$eid, $uid, $sid, leader_position_id()]);
            if ($dup) { flash('Persoana este deja pe altă poziție la acest eveniment.', 'error'); return null; }
            $self = $uid === (int)$me['id'];
            q('UPDATE event_slots SET user_id = ?, response = ?, response_note = NULL, responded_at = ?, assigned_by = ?, assigned_at = ?, notified_at = ? WHERE id = ?',
                [$uid, $self ? 'accepted' : null, $self ? now() : null, $me['id'], now(), $self ? now() : null, $sid]);
            $touch();
            return null;
        case 'unassign':
            if ($slot) q('UPDATE event_slots SET user_id = NULL, response = NULL, response_note = NULL, responded_at = NULL, notified_at = NULL WHERE id = ?', [$sid]);
            $touch();
            return null;
        case 'mark_accepted':
            if ($slot && $slot['user_id']) {
                q("UPDATE event_slots SET response = 'accepted', responded_at = ?, notified_at = COALESCE(notified_at, ?) WHERE id = ?", [now(), now(), $sid]);
                log_activity('response', $eid, $label, 'accepted', 'A marcat ca „Confirmat” pe ' . val('SELECT name FROM users WHERE id = ?', [$slot['user_id']]) . ' (' . $posName($slot['position_id']) . ').');
            }
            return null;
        case 'remove_slot':
            if ($slot) {
                q('DELETE FROM event_slots WHERE id = ?', [$sid]);
                log_activity('event', $eid, $label, 'slot_removed', 'A scos poziția ' . $posName($slot['position_id']) . ($slot['user_id'] ? ' (era ocupată de ' . val('SELECT name FROM users WHERE id = ?', [$slot['user_id']]) . ')' : '') . '.');
            }
            $touch();
            return null;
        case 'add_slot':
            $pid = (int)post('position_id');
            if (!val('SELECT COUNT(*) FROM positions WHERE id = ?', [$pid])) return null;
            q('INSERT INTO event_slots (event_id, position_id, sort) VALUES (?, ?, ?)', [$eid, $pid, (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM event_slots WHERE event_id = ?', [$eid])]);
            log_activity('event', $eid, $label, 'slot_added', 'A adăugat un loc: ' . $posName($pid) . '.');
            $touch();
            return null;
        case 'publish':
            q("UPDATE events SET status = 'published', published_at = COALESCE(published_at, ?), updated_at = ? WHERE id = ?", [now(), now(), $eid]);
            $who = [];
            $n = !empty($_POST['notify']) ? notify_assigned($eid, $who) : 0;
            notify_scheduler_summary($e, $me, $who, true);
            log_activity('event', $eid, $label, 'published', 'A publicat programul pentru echipă' . ($n ? " (email către $n " . ($n === 1 ? 'persoană' : 'persoane') . ')' : '') . '.');
            flash('Programul este vizibil pentru echipă.' . ($n ? " Am anunțat pe email $n " . ($n === 1 ? 'persoană.' : 'persoane.') : ''));
            return null;
        case 'notify':
            $who = [];
            $n = notify_assigned($eid, $who);
            notify_scheduler_summary($e, $me, $who, false);
            flash($n ? "Am anunțat pe email $n " . ($n === 1 ? 'persoană.' : 'persoane.') : 'Nu e nimeni nou de anunțat.');
            return null;
        case 'unpublish':
            q("UPDATE events SET status = 'draft', updated_at = ? WHERE id = ?", [now(), $eid]);
            log_activity('event', $eid, $label, 'unpublished', 'A mutat evenimentul înapoi în ciornă.');
            flash('Evenimentul este din nou ciornă și nu mai e vizibil pentru echipă.');
            return null;
        case 'notes':
            $notes = trim(str_replace("\r\n", "\n", (string)($_POST['notes'] ?? '')));
            if ($notes !== (string)($e['notes'] ?? '')) {
                q('UPDATE events SET notes = ?, updated_at = ? WHERE id = ?', [$notes !== '' ? $notes : null, now(), $eid]);
                log_activity('event', $eid, $label, 'updated', 'A modificat notițele pentru echipă.');
            }
            flash('Notițele au fost salvate.');
            return null;
        case 'file_add':
            $uid = (string)post('item_uid');
            $item = row("SELECT * FROM event_items WHERE event_id = ? AND uid = ? AND kind = 'item'", [$eid, $uid]);
            if (!$item) { flash('Elementul nu mai există. Reîncarcă pagina.', 'error'); return null; }
            [$f, $err] = store_event_file($eid, $uid, $_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE], (int)$me['id']);
            if (!$f) { flash($err, 'error'); return null; }
            log_activity('event', $eid, $label, 'file', 'A atașat „' . $f['name'] . '” (' . fmt_size((int)$f['size']) . ') la „' . $item['title'] . '”.');
            flash('Am atașat „' . $f['name'] . '”.');
            return 'planificare/eveniment/' . $eid . '#ordine';
        case 'file_delete':
            $f = row('SELECT * FROM event_files WHERE id = ? AND event_id = ?', [(int)post('file_id'), $eid]);
            if ($f) {
                $title = (string)val('SELECT title FROM event_items WHERE event_id = ? AND uid = ?', [$eid, $f['item_uid']]);
                delete_event_file($f);
                log_activity('event', $eid, $label, 'file', 'A șters fișierul „' . $f['name'] . '”' . ($title !== '' ? ' de la „' . $title . '”' : '') . '.');
                flash('Fișierul „' . $f['name'] . '” a fost șters.');
            }
            return 'planificare/eveniment/' . $eid . '#ordine';
        case 'items':
            $sum = save_event_items($e, $_POST, $me['role'] === 'pastor');
            if ($sum !== '') { $touch(); log_activity('event', $eid, $label, 'order', $sum); flash('Ordinea serviciului a fost salvată.'); }
            return 'planificare/eveniment/' . $eid . '#ordine';
        case 'details':
            $date = post('date');
            $time = post('time');
            $reh = post('rehearsal');
            if (mb_strlen(post('name')) < 2 || !valid_date($date) || !valid_time($time) || ($reh !== '' && !valid_time($reh))) {
                flash('Verifică numele, data și orele.', 'error');
                return null;
            }
            q('UPDATE events SET name = ?, event_date = ?, start_time = ?, rehearsal_time = ?, notes = ?, updated_at = ? WHERE id = ?',
                [mb_substr(post('name'), 0, 120), $date, $time, $reh ?: null, trim((string)($_POST['notes'] ?? '')) ?: null, now(), $eid]);
            $what = event_diff($e, row('SELECT * FROM events WHERE id = ?', [$eid]));
            if ($what !== '') log_activity('event', $eid, $label, 'updated', $what);
            sync_song_uses(row('SELECT * FROM events WHERE id = ?', [$eid]));
            flash('Detaliile au fost salvate.');
            return null;
        case 'cancel':
            if (empty($_POST['confirm'])) { flash('Bifează confirmarea ca să ștergi evenimentul.', 'error'); return null; }
            $filled = (int)val('SELECT COUNT(*) FROM event_slots WHERE event_id = ? AND user_id IS NOT NULL', [$eid]);
            log_activity('event', $eid, $label, 'deleted', 'A șters evenimentul „' . $e['name'] . '” din ' . ro_date($e['event_date'], true) . ' (' . ($e['status'] === 'published' ? 'publicat' : 'ciornă') . ', ' . ($filled === 1 ? '1 persoană programată' : "$filled persoane programate") . ').');
            q('DELETE FROM event_items WHERE event_id = ?', [$eid]);
            q('DELETE FROM song_uses WHERE event_id = ?', [$eid]);
            foreach (rows('SELECT * FROM event_files WHERE event_id = ?', [$eid]) as $f) delete_event_file($f);
            if ($e['template_id']) {
                q("UPDATE events SET status = 'cancelled', updated_at = ? WHERE id = ?", [now(), $eid]); // rămâne ca să nu fie recreat automat
                q('DELETE FROM event_slots WHERE event_id = ?', [$eid]);
            } else {
                q('DELETE FROM event_slots WHERE event_id = ?', [$eid]);
                q('DELETE FROM events WHERE id = ?', [$eid]);
            }
            flash('Evenimentul „' . $e['name'] . '” din ' . ro_date($e['event_date']) . ' a fost șters.');
            return 'planificare';
    }
    return null;
}

/* ---------- Voluntar: programul meu, răspuns, vizualizare eveniment ---------- */

function my_upcoming(int $uid): array {
    return rows("SELECT s.id AS slot_id, s.response, s.response_note, s.position_id, p.name AS position, e.*
        FROM event_slots s JOIN events e ON e.id = s.event_id JOIN positions p ON p.id = s.position_id
        WHERE s.user_id = ? AND e.status = 'published' AND e.event_date >= ? ORDER BY e.event_date, e.start_time", [$uid, date('Y-m-d')]);
}

function respond(): void {
    $me = require_user();
    if (!is_post()) redirect('');
    csrf_check();
    $sid = (int)post('slot_id');
    $s = row("SELECT s.*, e.event_date, e.status, e.name FROM event_slots s JOIN events e ON e.id = s.event_id WHERE s.id = ? AND s.user_id = ?", [$sid, $me['id']]);
    if (!$s || $s['status'] !== 'published') not_found();
    if (post('answer') === 'accept') {
        q("UPDATE event_slots SET response = 'accepted', response_note = NULL, responded_at = ? WHERE id = ?", [now(), $sid]);
        log_activity('response', (int)$s['event_id'], $s['name'] . ' ' . $s['event_date'], 'accepted', 'A acceptat (' . val('SELECT name FROM positions WHERE id = ?', [$s['position_id']]) . ').');
        flash('Mulțumim! Ai confirmat ' . $s['name'] . ' din ' . ro_date($s['event_date']) . '.');
    } elseif (post('answer') === 'decline') {
        q("UPDATE event_slots SET response = 'declined', response_note = ?, responded_at = ? WHERE id = ?", [mb_substr(post('note'), 0, 255) ?: null, now(), $sid]);
        log_activity('response', (int)$s['event_id'], $s['name'] . ' ' . $s['event_date'], 'declined', 'Nu poate (' . val('SELECT name FROM positions WHERE id = ?', [$s['position_id']]) . ')' . (post('note') ? ': „' . mb_substr(post('note'), 0, 255) . '”' : '') . '.');
        // anunțăm doar liderul serviciului (dacă există și nu e chiar persoana care renunță)
        $leader = row("SELECT u.* FROM event_slots s JOIN users u ON u.id = s.user_id WHERE s.event_id = ? AND s.position_id = ? AND u.status = 'active' AND (s.response IS NULL OR s.response <> 'declined') LIMIT 1", [$s['event_id'], leader_position_id()]);
        if ($leader && (int)$leader['id'] !== (int)$me['id']) {
            $link = full_url(role_rank($leader['role']) >= 2 ? 'planificare/eveniment/' . $s['event_id'] : 'program/' . $s['event_id']);
            send_mail($leader['email'], $me['name'] . ' nu poate ajunge · ' . $s['name'] . ' ' . ro_date($s['event_date']),
                $me['name'] . ' a spus că nu poate ajunge la ' . $s['name'] . ' (' . ro_date($s['event_date'], true) . ').' . (post('note') ? "\nMotiv: " . post('note') : '') . "\n\nAlege pe altcineva:\n\n$link",
                ['Deschide evenimentul', $link]);
            flash('Am anunțat liderul (' . $leader['name'] . ') că nu poți ajunge pe ' . ro_date($s['event_date']) . '.');
        } else {
            flash('Am notat că nu poți ajunge pe ' . ro_date($s['event_date']) . '.');
        }
    }
    $back = post('back');
    redirect(preg_match('#^program/\d+$#', $back) ? $back : '');
}

function program_show(string $id): void {
    $me = require_user();
    // editorii văd și ciornele, ca să verifice cum va arăta pagina pentru echipă
    $e = can_plan()
        ? row("SELECT * FROM events WHERE id = ? AND status <> 'cancelled'", [(int)$id])
        : row("SELECT * FROM events WHERE id = ? AND status = 'published'", [(int)$id]);
    if (!$e) not_found();
    $slots = slot_labels(event_slots((int)$e['id']));
    $mine = array_values(array_filter($slots, fn($s) => (int)$s['user_id'] === (int)$me['id']));
    render('planning/program', ['nav' => 'program', 'e' => $e, 'slots' => $slots, 'mine' => $mine, 'me' => $me, 'items' => event_items((int)$e['id']), 'files' => event_files((int)$e['id']), 'isEditor' => can_plan()]);
}

/* ---------- Indisponibil ---------- */

function blockouts_page(): void {
    $me = require_user();
    $errors = [];
    if (is_post()) {
        csrf_check();
        if (post('action') === 'unmark_day') {
            $d = post('date');
            if (!valid_date($d)) redirect('indisponibil');
            foreach (rows('SELECT * FROM blockouts WHERE user_id = ? AND start_date <= ? AND end_date >= ?', [$me['id'], $d, $d]) as $b) {
                $prev = date('Y-m-d', strtotime($d . ' -1 day'));
                $next = date('Y-m-d', strtotime($d . ' +1 day'));
                if ($b['start_date'] === $b['end_date']) q('DELETE FROM blockouts WHERE id = ?', [$b['id']]);
                elseif ($d === $b['start_date']) q('UPDATE blockouts SET start_date = ? WHERE id = ?', [$next, $b['id']]);
                elseif ($d === $b['end_date']) q('UPDATE blockouts SET end_date = ? WHERE id = ?', [$prev, $b['id']]);
                else {
                    q('UPDATE blockouts SET end_date = ? WHERE id = ?', [$prev, $b['id']]);
                    q('INSERT INTO blockouts (user_id, start_date, end_date, reason, created_at) VALUES (?, ?, ?, ?, ?)', [$me['id'], $next, $b['end_date'], $b['reason'], now()]);
                }
            }
            flash('Ziua de ' . ro_date($d) . ' nu mai e marcată.');
            redirect('indisponibil');
        }
        if (post('action') === 'delete') {
            q('DELETE FROM blockouts WHERE id = ? AND user_id = ?', [(int)post('id'), $me['id']]);
            flash('Perioada a fost ștearsă.');
            redirect('indisponibil');
        }
        $from = post('from');
        $to = post('to') ?: $from;
        if (!valid_date($from) || !valid_date($to)) $errors['from'] = 'Alege o dată corectă.';
        elseif ($to < $from) $errors['to'] = 'Data de sfârșit este înaintea celei de început.';
        elseif ($to < date('Y-m-d')) $errors['from'] = 'Perioada este deja în trecut.';
        if (!$errors) {
            q('INSERT INTO blockouts (user_id, start_date, end_date, reason, created_at) VALUES (?, ?, ?, ?, ?)', [$me['id'], $from, $to, mb_substr(post('reason'), 0, 160) ?: null, now()]);
            $clash = rows("SELECT e.name, e.event_date FROM event_slots s JOIN events e ON e.id = s.event_id WHERE s.user_id = ? AND e.event_date BETWEEN ? AND ? AND e.status <> 'cancelled' AND (s.response IS NULL OR s.response <> 'declined')", [$me['id'], $from, $to]);
            if ($clash) {
                flash('Atenție: ești deja programat în această perioadă (' . implode(', ', array_map(fn($c) => $c['name'] . ' ' . ro_date($c['event_date']), $clash)) . '). Spune „Nu pot ajunge” din Programul meu.', 'error');
            } else {
                flash('Perioada a fost adăugată. Editorii o vor vedea la planificare.');
            }
            redirect('indisponibil');
        }
    }
    $list = rows('SELECT * FROM blockouts WHERE user_id = ? AND end_date >= ? ORDER BY start_date', [$me['id'], date('Y-m-d')]);
    $mine = my_upcoming((int)$me['id']);
    render('planning/blockouts', ['nav' => 'indisponibil', 'list' => $list, 'errors' => $errors, 'mine' => $mine]);
}

/* ---------- fișierele atașate (predică, anunțuri…) ---------- */

function event_file_mime(array $f): string {
    return EVENT_FILE_EXT[strtolower(pathinfo($f['file'], PATHINFO_EXTENSION))] ?? 'application/octet-stream';
}

/** În aplicație: oricine din echipă care vede serviciul. PDF-ul se deschide în browser. */
function event_file_serve(string $id): void {
    require_user();
    $f = row('SELECT f.*, e.status FROM event_files f JOIN events e ON e.id = f.event_id WHERE f.id = ?', [(int)$id]);
    if (!$f || !is_file(media_dir() . '/' . $f['file'])) not_found();
    if ($f['status'] === 'cancelled' || ($f['status'] !== 'published' && !can_plan())) not_found();
    serve_file(media_dir() . '/' . $f['file'], event_file_mime($f), $f['name'], event_file_mime($f) === 'application/pdf');
}

/** Linkul secret din mesajul WhatsApp: fără autentificare, descarcă direct fișierul. */
function event_file_public(string $t): void {
    $f = (strlen($t) === 40 && ctype_xdigit($t)) ? row('SELECT * FROM event_files WHERE token = ?', [$t]) : null;
    if (!$f || !is_file(media_dir() . '/' . $f['file'])) {
        http_response_code(404); header('Content-Type: text/plain; charset=utf-8');
        echo 'Fișierul nu mai există. Cere un link nou de la cine a trimis mesajul.'; return;
    }
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer');
    serve_file(media_dir() . '/' . $f['file'], event_file_mime($f), $f['name'], false);
}

/** Setări → Fișiere la servicii: lista tuturor fișierelor, pentru curățenie manuală. */
function settings_files(): void {
    $me = require_role('editor');
    if (is_post()) {
        csrf_check();
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        if (post('action') === 'delete_one') $ids = [(int)post('id')];
        $n = 0; $bytes = 0;
        foreach ($ids as $id) {
            $f = row('SELECT f.*, e.name AS ev_name, e.event_date FROM event_files f LEFT JOIN events e ON e.id = f.event_id WHERE f.id = ?', [$id]);
            if (!$f) continue;
            delete_event_file($f);
            log_activity('event', (int)$f['event_id'], ($f['ev_name'] ?? 'Eveniment') . ' ' . ro_date((string)$f['event_date']), 'file', 'A șters fișierul „' . $f['name'] . '” (din Setări → Fișiere).');
            $n++; $bytes += (int)$f['size'];
        }
        flash($n ? ($n === 1 ? 'Am șters un fișier' : "Am șters $n fișiere") . ' (' . fmt_size($bytes) . ' eliberați).' : 'Nu ai ales niciun fișier.');
        redirect('setari/fisiere');
    }
    $files = rows("SELECT f.*, e.name AS ev_name, e.event_date, e.status, u.name AS by_name, i.title AS item_title
        FROM event_files f LEFT JOIN events e ON e.id = f.event_id LEFT JOIN users u ON u.id = f.uploaded_by
        LEFT JOIN event_items i ON i.event_id = f.event_id AND i.uid = f.item_uid ORDER BY e.event_date DESC, f.id DESC");
    render('settings/files', ['nav' => 'setari', 'sub' => 'fisiere', 'files' => $files, 'total' => array_sum(array_map(fn($f) => (int)$f['size'], $files))]);
}
