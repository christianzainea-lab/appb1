<?php
declare(strict_types=1);

/**
 * Istoricul modificărilor. Zone: song, event, user, response, settings.
 * Cântările și evenimentele își arată istoricul pe pagina lor (editori + admini);
 * restul se vede doar în /debuglog (admini).
 */
function log_activity(string $area, ?int $objectId, ?string $label, string $action, string $summary = '', ?array $actor = null): void {
    $u = $actor ?? (function_exists('current_user') ? current_user() : null);
    try {
        q('INSERT INTO activity_log (created_at, user_id, user_name, area, object_id, object_label, action, summary) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [now(), $u['id'] ?? null, $u['name'] ?? null, $area, $objectId, $label !== null ? mb_substr($label, 0, 190) : null, $action, mb_substr($summary, 0, 4000)]);
    } catch (Throwable $e) {
        log_error('app', 'activity_log: ' . $e->getMessage()); // istoricul nu trebuie să blocheze nicio acțiune
    }
}

function activity_for(string $area, int $objectId, int $limit = 200): array {
    return rows('SELECT * FROM activity_log WHERE area = ? AND object_id = ? ORDER BY id DESC LIMIT ' . (int)$limit, [$area, $objectId]);
}

const ACTIVITY_AREAS = ['song' => 'Cântări', 'event' => 'Evenimente', 'user' => 'Persoane', 'response' => 'Răspunsuri', 'settings' => 'Setări'];

function activity_when(string $dt): string {
    $t = strtotime($dt);
    return ro_date($dt, date('Y', $t) !== date('Y')) . ' ' . date('H:i', $t);
}

/* ---------- cântări: ce s-a schimbat ---------- */

function song_snapshot(int $sid): ?array {
    $s = row('SELECT * FROM songs WHERE id = ?', [$sid]);
    if (!$s) return null;
    $s['labels'] = q('SELECT l.name FROM song_labels sl JOIN labels l ON l.id = sl.label_id WHERE sl.song_id = ? ORDER BY l.name', [$sid])->fetchAll(PDO::FETCH_COLUMN);
    $s['media'] = [];
    foreach (rows('SELECT * FROM song_media WHERE song_id = ? ORDER BY id', [$sid]) as $m) $s['media'][(int)$m['id']] = $m;
    return $s;
}

function media_name(array $m): string {
    return ($m['kind'] === 'audio' ? 'MP3' : 'YouTube') . ($m['title'] ? ' „' . $m['title'] . '”' : '');
}

function song_diff(?array $a, array $b): string {
    if (!$a) {
        $bits = array_filter([song_ref($b), $b['song_key'] ? 'ton ' . $b['song_key'] : '', $b['bpm'] ? $b['bpm'] . ' BPM' : '']);
        $media = count($b['media']);
        return 'A adăugat cântarea' . ($bits ? ' (' . implode(', ', $bits) . ')' : '') . ($media ? ", $media " . ($media === 1 ? 'înregistrare' : 'înregistrări') : '') . '.';
    }
    $out = [];
    $fields = ['title' => 'titlu', 'author' => 'autor', 'source' => 'sursă', 'source_no' => 'număr', 'song_key' => 'ton', 'bpm' => 'BPM'];
    foreach ($fields as $k => $label) {
        if ((string)$a[$k] !== (string)$b[$k]) $out[] = $label . ': ' . ($a[$k] === null || $a[$k] === '' ? '—' : $a[$k]) . ' → ' . ($b[$k] === null || $b[$k] === '' ? '—' : $b[$k]);
    }
    if ((string)$a['notes'] !== (string)$b['notes']) $out[] = 'notițele pentru echipă';
    if ((string)($a['lyrics'] ?? '') !== (string)($b['lyrics'] ?? '')) $out[] = ($a['lyrics'] ?? '') === '' ? 'a adăugat versurile' : (($b['lyrics'] ?? '') === '' ? 'a șters versurile' : 'versurile');
    $add = array_diff($b['labels'], $a['labels']);
    $rem = array_diff($a['labels'], $b['labels']);
    if ($add) $out[] = 'etichete + ' . implode(', ', $add);
    if ($rem) $out[] = 'etichete − ' . implode(', ', $rem);
    foreach (array_diff_key($b['media'], $a['media']) as $m) $out[] = 'a adăugat ' . media_name($m);
    foreach (array_diff_key($a['media'], $b['media']) as $m) $out[] = 'a șters ' . media_name($m);
    foreach (array_intersect_key($b['media'], $a['media']) as $id => $m) {
        if ((string)$a['media'][$id]['title'] !== (string)$m['title']) $out[] = 'redenumit ' . media_name($a['media'][$id]) . ' → „' . $m['title'] . '”';
    }
    $mainA = array_keys(array_filter($a['media'], fn($m) => $m['is_main']));
    $mainB = array_keys(array_filter($b['media'], fn($m) => $m['is_main']));
    if ($mainA !== $mainB && $mainB && isset($a['media'][$mainB[0]])) $out[] = 'principală: ' . media_name($b['media'][$mainB[0]]);
    if ((string)$a['chords_file'] !== (string)$b['chords_file']) $out[] = $b['chords_file'] ? 'acorduri încărcate (' . $b['chords_name'] . ')' : 'acorduri șterse';
    return $out ? ucfirst(implode('; ', $out)) . '.' : '';
}

/* ---------- evenimente: detalii ---------- */

function event_diff(array $a, array $b): string {
    $out = [];
    foreach (['name' => 'nume', 'event_date' => 'data', 'start_time' => 'ora', 'rehearsal_time' => 'repetiția'] as $k => $label) {
        if ((string)$a[$k] !== (string)$b[$k]) $out[] = $label . ': ' . ($a[$k] ?: '—') . ' → ' . ($b[$k] ?: '—');
    }
    if ((string)$a['notes'] !== (string)$b['notes']) $out[] = 'notițele';
    return $out ? 'Detalii: ' . implode('; ', $out) . '.' : '';
}

/** Versiunea care rulează: din app/build.php (scris la deploy) sau din git local. */
function build_info(): array {
    $f = APP_DIR . '/build.php';
    $b = is_file($f) ? (array)(include $f) : [];
    return ['version' => APP_VERSION, 'sha' => $b['sha'] ?? 'local', 'time' => $b['time'] ?? null, 'branch' => $b['branch'] ?? ''];
}

/** Înregistrează schimbarea pozițiilor cuiva (dacă s-a schimbat ceva). */
function log_positions_change(int $uid, string $name, array $before): void {
    $after = user_position_labels($uid);
    $add = array_diff($after, $before);
    $rem = array_diff($before, $after);
    if (!$add && !$rem) return;
    log_activity('user', $uid, $name, 'positions', 'Poziții' . ($add ? ' + ' . implode(', ', $add) : '') . ($rem ? ($add ? ';' : '') . ' − ' . implode(', ', $rem) : '') . '.');
}
