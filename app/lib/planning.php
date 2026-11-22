<?php
declare(strict_types=1);

const RULES = [
    'every' => 'În fiecare săptămână',
    'first' => 'Doar în prima săptămână din lună',
    'not_first' => 'În fiecare săptămână, fără prima din lună',
    'none' => 'Nu se repetă',
];
const LOAD_OK_MAX = 2;      // până la 2 duminici pe lună e normal
const STREAK_WARN = 3;      // a 3-a duminică la rând = atenție
const STREAK_MAX = 4;       // a 4-a la rând = prea mult

function template_matches(array $t, string $date): bool {
    if ($t['weekday'] === null || $t['rule'] === 'none' || !$t['active']) return false;
    $ts = strtotime($date);
    if ((int)date('w', $ts) !== (int)$t['weekday']) return false;
    $first = (int)date('j', $ts) <= 7;
    return match ($t['rule']) { 'first' => $first, 'not_first' => !$first, default => true };
}

/** Creează automat evenimentele din șabloane între două date (doar de azi înainte). */
function ensure_events(string $from, string $to): void {
    $from = max($from, date('Y-m-d'));
    if ($from > $to) return;
    $tpls = rows('SELECT * FROM templates WHERE active = 1 AND weekday IS NOT NULL AND rule <> ?', ['none']);
    if (!$tpls) return;
    $existing = [];
    foreach (rows('SELECT template_id, event_date FROM events WHERE template_id IS NOT NULL AND event_date BETWEEN ? AND ?', [$from, $to]) as $r) {
        $existing[$r['template_id'] . '|' . $r['event_date']] = true;
    }
    for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
        foreach ($tpls as $t) {
            if (!template_matches($t, $d) || isset($existing[$t['id'] . '|' . $d])) continue;
            $eid = event_create_from_template($t, $d, null);
            log_activity('event', $eid, $t['name'] . ' ' . $d, 'created', 'Creat automat din șablonul „' . $t['name'] . '”.', ['id' => null, 'name' => null]);
        }
    }
}

function event_create_from_template(?array $t, string $date, ?int $by, ?string $name = null, ?string $time = null, ?string $reh = null): int {
    q('INSERT INTO events (template_id, name, event_date, start_time, rehearsal_time, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$t['id'] ?? null, $name ?? $t['name'], $date, $time ?? $t['start_time'], $reh ?? ($t['rehearsal_time'] ?? null), 'draft', $by, now(), now()]);
    $eid = (int)db()->lastInsertId();
    if ($t) {
        $sort = 0;
        foreach (rows('SELECT tp.* FROM template_positions tp JOIN positions p ON p.id = tp.position_id JOIN position_groups g ON g.id = p.group_id WHERE tp.template_id = ? ORDER BY g.sort, p.sort', [$t['id']]) as $tp) {
            for ($i = 0; $i < (int)$tp['slots']; $i++) {
                q('INSERT INTO event_slots (event_id, position_id, sort) VALUES (?, ?, ?)', [$eid, $tp['position_id'], $sort++]);
            }
        }
        copy_template_items((int)$t['id'], $eid);
    }
    if ($lp = leader_position_id()) {
        $leader = ($t && !empty($t['leader_id'])) ? (int)val("SELECT id FROM users WHERE id = ? AND status = 'active'", [$t['leader_id']]) : 0;
        q('INSERT INTO event_slots (event_id, position_id, user_id, assigned_by, assigned_at, sort) VALUES (?, ?, ?, ?, ?, -1)',
            [$eid, $lp, $leader ?: null, $leader ? $by : null, $leader ? now() : null]);
    }
    return $eid;
}

function month_bounds(string $ym): array {
    $start = $ym . '-01';
    return [$start, date('Y-m-t', strtotime($start))];
}

/** Câte servicii de duminică are fiecare om într-o lună (fără refuzuri). Joile nu se numără. */
function workload(string $ym): array {
    [$a, $b] = month_bounds($ym);
    $out = [];
    foreach (rows("SELECT DISTINCT s.user_id, s.event_id, e.event_date FROM event_slots s JOIN events e ON e.id = s.event_id WHERE e.event_date BETWEEN ? AND ? AND e.status <> 'cancelled' AND s.user_id IS NOT NULL AND (s.response IS NULL OR s.response <> 'declined')", [$a, $b]) as $r) {
        if ((int)date('w', strtotime($r['event_date'])) !== 0) continue;
        $out[(int)$r['user_id']] = ($out[(int)$r['user_id']] ?? 0) + 1;
    }
    return $out;
}

/** [eticheta, culoare, clasa] pentru numărul de duminici pe lună. */
function load_level(int $n): array {
    if ($n <= LOAD_OK_MAX) return ['ok', '#2F6B3F', 'lv-ok'];
    if ($n < 4) return ['peste', '#A1520B', 'lv-high'];
    return ['mult peste', '#B42318', 'lv-max'];
}

function load_bar(int $n): string {
    [$label, $color] = load_level($n);
    return '<span class="load"><span class="load-bar"><span style="width:' . min(100, $n * 25) . '%;background:' . $color . '"></span></span><span class="xs" style="font-weight:700;color:' . $color . '">' . $n . ' dum. · ' . $label . '</span></span>';
}

/** Duminicile la care e programat fiecare om, într-o fereastră în jurul unei date (pentru șirul de duminici la rând). */
function sundays_served(string $around, int $weeks = 10): array {
    $a = date('Y-m-d', strtotime($around . " -$weeks weeks"));
    $b = date('Y-m-d', strtotime($around . " +$weeks weeks"));
    $out = [];
    foreach (rows("SELECT s.user_id, e.event_date FROM event_slots s JOIN events e ON e.id = s.event_id WHERE e.event_date BETWEEN ? AND ? AND e.status <> 'cancelled' AND s.user_id IS NOT NULL AND (s.response IS NULL OR s.response <> 'declined')", [$a, $b]) as $r) {
        if ((int)date('w', strtotime($r['event_date'])) === 0) $out[(int)$r['user_id']][$r['event_date']] = true;
    }
    return $out;
}

/** Câte duminici la rând ar sluji omul dacă e programat și pe duminica $sunday (inclusiv luna trecută). */
function sunday_streak(array $dates, string $sunday): int {
    $n = 1;
    for ($d = date('Y-m-d', strtotime($sunday . ' -7 days')); isset($dates[$d]); $d = date('Y-m-d', strtotime($d . ' -7 days'))) $n++;
    for ($d = date('Y-m-d', strtotime($sunday . ' +7 days')); isset($dates[$d]); $d = date('Y-m-d', strtotime($d . ' +7 days'))) $n++;
    return $n;
}

function event_slots(int $eid): array {
    return rows('SELECT s.*, p.name AS position, p.is_leader, p.group_id, g.name AS group_name, g.sort AS gsort, p.sort AS psort, u.name AS user_name, u.avatar, u.phone, u.is_test
        FROM event_slots s JOIN positions p ON p.id = s.position_id JOIN position_groups g ON g.id = p.group_id
        LEFT JOIN users u ON u.id = s.user_id WHERE s.event_id = ? ORDER BY g.sort, p.sort, s.sort, s.id', [$eid]);
}

/** Numerotează locurile repetate: Cameră 1, Cameră 2. */
function slot_labels(array $slots): array {
    $tot = [];
    foreach ($slots as $s) $tot[$s['position_id']] = ($tot[$s['position_id']] ?? 0) + 1;
    $seen = [];
    foreach ($slots as &$s) {
        $seen[$s['position_id']] = ($seen[$s['position_id']] ?? 0) + 1;
        $s['label'] = $s['position'] . ($tot[$s['position_id']] > 1 ? ' ' . $seen[$s['position_id']] : '');
    }
    return $slots;
}

function blocked_on(string $date): array {
    $out = [];
    foreach (rows('SELECT * FROM blockouts WHERE start_date <= ? AND end_date >= ?', [$date, $date]) as $b) $out[(int)$b['user_id']] = $b;
    return $out;
}

function event_title(array $e): string {
    $t = strtotime($e['event_date']);
    return $e['name'] . ' · ' . RO_DAYS[(int)date('w', $t)] . ' ' . ro_date($e['event_date'], date('Y', $t) !== date('Y')) . ' · ' . $e['start_time'];
}
function event_short_date(string $d): string {
    $t = strtotime($d);
    return mb_substr(RO_DAYS[(int)date('w', $t)], 0, 3) . ' ' . date('j', $t);
}

function response_badge(?array $s, bool $published): string {
    if (!$s || !$s['user_id']) return '';
    if ($s['response'] === 'accepted') return '<span class="badge ok">Confirmat</span>';
    if ($s['response'] === 'declined') return '<span class="badge bad">A refuzat</span>';
    return $published ? '<span class="badge warn">Neconfirmat</span>' : '<span class="badge grey">Ciornă</span>';
}

function user_part_names(int $uid, int $positionId): string {
    $n = q('SELECT pp.name FROM user_parts up JOIN position_parts pp ON pp.id = up.part_id WHERE up.user_id = ? AND pp.position_id = ? ORDER BY pp.sort', [$uid, $positionId])->fetchAll(PDO::FETCH_COLUMN);
    return implode(', ', $n);
}

/** Anunță pe email persoanele nou programate la un eveniment publicat. */
function notify_assigned(int $eid, ?array &$who = null): int {
    $who = [];
    $e = row('SELECT * FROM events WHERE id = ?', [$eid]);
    if (!$e || $e['status'] !== 'published') return 0;
    $slots = rows('SELECT s.*, p.name AS position, u.name, u.email FROM event_slots s JOIN positions p ON p.id = s.position_id JOIN users u ON u.id = s.user_id WHERE s.event_id = ? AND s.notified_at IS NULL AND (s.response IS NULL)', [$eid]);
    $byUser = [];
    foreach ($slots as $s) $byUser[(int)$s['user_id']][] = $s;
    $link = full_url('');
    $n = 0;
    foreach ($byUser as $list) {
        $u = $list[0];
        $pos = implode(', ', array_unique(array_column($list, 'position')));
        $when = RO_DAYS[(int)date('w', strtotime($e['event_date']))] . ', ' . ro_date($e['event_date'], true) . ', ora ' . $e['start_time'] . ($e['rehearsal_time'] ? ' (repetiție ' . $e['rehearsal_time'] . ')' : '');
        send_mail($u['email'], 'Ești programat: ' . $e['name'] . ' · ' . ro_date($e['event_date']),
            'Salut, ' . first_name($u['name']) . "!\n\nEști programat la " . $e['name'] . ":\n$when\nPoziție: $pos\n\nConfirmă sau spune că nu poți ajunge:\n\n$link",
            ['Confirmă în aplicație', $link]);
        foreach ($list as $s) q('UPDATE event_slots SET notified_at = ? WHERE id = ?', [now(), $s['id']]);
        $who[] = $u['name'] . ' – ' . $pos;
        $n++;
    }
    return $n;
}

/** Rezumat pentru cine a programat și pentru liderul serviciului (o singură dată dacă e aceeași persoană). */
function notify_scheduler_summary(array $e, array $me, array $who, bool $published): void {
    if (!$who) return;
    $eid = (int)$e['id'];
    $slots = slot_labels(event_slots($eid));
    $empty = array_values(array_map(fn($s) => $s['label'], array_filter($slots, fn($s) => !$s['user_id'])));
    $when = RO_DAYS[(int)date('w', strtotime($e['event_date']))] . ', ' . ro_date($e['event_date'], true) . ', ora ' . $e['start_time'];
    $link = full_url('planificare/eveniment/' . $eid);
    $body = ($published ? 'Programul a fost publicat pentru echipă.' : 'Am trimis anunțuri pentru programul de mai jos.') . "\n\n" . $e['name'] . "\n$when\n\n"
        . 'Am anunțat pe email (' . count($who) . "):\n• " . implode("\n• ", $who)
        . ($empty ? "\n\nLocuri încă libere:\n• " . implode("\n• ", $empty) : "\n\nToate locurile sunt ocupate.")
        . "\n\nPublicat de: " . $me['name'];
    $to = [(int)$me['id'] => $me];
    $lp = leader_position_id();
    if ($lp) {
        $leader = row("SELECT u.* FROM event_slots s JOIN users u ON u.id = s.user_id WHERE s.event_id = ? AND s.position_id = ? AND u.status = 'active' LIMIT 1", [$eid, $lp]);
        if ($leader) $to[(int)$leader['id']] = $leader;
    }
    foreach ($to as $u) {
        $isLeader = (int)$u['id'] !== (int)$me['id'];
        send_mail($u['email'], 'Confirmare program: ' . $e['name'] . ' · ' . ro_date($e['event_date']),
            'Salut, ' . first_name($u['name']) . "!\n\n" . ($isLeader ? "Ești liderul acestui serviciu.\n" : '') . $body,
            ['Deschide evenimentul', role_rank($u['role']) >= 2 ? $link : full_url('program/' . $eid)]);
    }
}
