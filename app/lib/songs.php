<?php
declare(strict_types=1);

const SONG_KEYS = ['Do', 'Do♯', 'Re♭', 'Re', 'Mi♭', 'Mi', 'Fa', 'Fa♯', 'Sol', 'La♭', 'La', 'Si♭', 'Si'];
const RECENT_DAYS = 14;
const SONG_KEY_MAX = 20;
const SONG_BOOK_MAX = 20;

/** Tonurile adăugate de editori în Setări → Tonuri (pe lângă cele standard). */
function custom_keys(): array {
    static $c = null;
    return $c ??= rows('SELECT id, name, sort FROM song_keys ORDER BY sort, id');
}

/** Cele 26 de tonuri standard: Do … Si, major și minor. */
function standard_keys(): array {
    return array_merge(SONG_KEYS, array_map(fn($k) => $k . ' m', SONG_KEYS));
}

function is_standard_key(string $k): bool {
    $l = mb_strtolower(trim($k));
    foreach (standard_keys() as $s) if (mb_strtolower($s) === $l) return true;
    return false;
}

function is_known_key(string $k): bool {
    if (in_array($k, standard_keys(), true)) return true;
    foreach (custom_keys() as $c) if ($c['name'] === $k) return true;
    return false;
}

/** Semnele din Excel → ce înseamnă (pentru tooltip). */
const USE_SYMBOLS = [
    '🥖🍷' => 'Cina Domnului', '⭐' => 'Crăciun', '☀️' => 'Paști', '🌽🍇' => 'Mulțumire', '💧💦' => 'Botez',
    '🌴🌸' => 'Florii', '✝️' => 'Vinerea Mare', '🕯️' => 'Advent', '🧨' => 'Anul Nou', '🔥' => 'Rusalii',
];

function symbol_title(?string $note): string {
    if (!$note) return '';
    $out = [];
    foreach (USE_SYMBOLS as $sym => $name) if (str_contains($note, $sym)) $out[] = $name;
    return implode(', ', array_unique($out));
}

/** Cărțile de cântări din Setări → Cărți, în ordinea aleasă. */
function books_all(): array {
    static $b = null;
    return $b ??= rows('SELECT id, name, sort FROM song_books ORDER BY sort, id');
}

function is_known_book(string $name): bool {
    foreach (books_all() as $b) if ($b['name'] === $name) return true;
    return false;
}

/** Cartea de cântări pentru filtru: numele cărții așa cum e scris; fără carte → „”. */
function book_group(?string $source): string {
    return trim((string)$source);
}

function media_dir(): string {
    $d = STORAGE_DIR . '/media';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}

/** Lista completă, cu agregatele pentru tabel. */
function songs_all(): array {
    $today = date('Y-m-d');
    $since = date('Y-m-d', strtotime('-90 days'));
    $songs = rows('SELECT * FROM songs ORDER BY title');
    $uses = [];
    foreach (rows('SELECT song_id, MAX(used_on) AS last_used, COUNT(*) AS total, SUM(CASE WHEN used_on >= ? THEN 1 ELSE 0 END) AS recent FROM song_uses WHERE used_on <= ? GROUP BY song_id', [$since, $today]) as $r) {
        $uses[(int)$r['song_id']] = $r;
    }
    $media = [];
    foreach (rows('SELECT * FROM song_media ORDER BY is_main DESC, sort, id') as $m) $media[(int)$m['song_id']][] = $m;
    $labels = song_labels_by_song();
    foreach ($songs as &$s) {
        $id = (int)$s['id'];
        $s['last_used'] = $uses[$id]['last_used'] ?? null;
        $s['total'] = (int)($uses[$id]['total'] ?? 0);
        $s['recent'] = (int)($uses[$id]['recent'] ?? 0);
        $s['media'] = $media[$id] ?? [];
        $s['labels'] = $labels[$id] ?? [];
        $s['yt'] = count(array_filter($s['media'], fn($m) => $m['kind'] === 'youtube'));
        $s['audio'] = count(array_filter($s['media'], fn($m) => $m['kind'] === 'audio'));
    }
    return $songs;
}

function song_labels_by_song(): array {
    $out = [];
    foreach (rows('SELECT sl.song_id, l.id, l.name, l.kind FROM song_labels sl JOIN labels l ON l.id = sl.label_id ORDER BY l.kind, l.sort, l.name') as $r) {
        $out[(int)$r['song_id']][] = $r;
    }
    return $out;
}

function labels_all(): array {
    $counts = [];
    foreach (rows('SELECT label_id, COUNT(*) AS n FROM song_labels GROUP BY label_id') as $r) $counts[(int)$r['label_id']] = (int)$r['n'];
    $ls = rows('SELECT * FROM labels ORDER BY kind, sort, name');
    foreach ($ls as &$l) $l['songs'] = $counts[(int)$l['id']] ?? 0;
    return $ls;
}

function label_kind_name(string $k): string { return $k === 'tema' ? 'Teme' : 'Ocazii'; }

function song_ref(array $s): string {
    return trim(($s['source'] ?? '') . ' ' . ($s['source_no'] ?? ''));
}

/** Datele pentru butonul de redare (o variantă). */
function play_attrs(array $m, string $songTitle): string {
    $label = $songTitle . ($m['title'] ? ' · ' . $m['title'] : '');
    if ($m['kind'] === 'youtube' && $m['youtube_id']) {
        return 'data-play="yt" data-src="' . e($m['youtube_id']) . '" data-title="' . e($label) . '"';
    }
    if ($m['kind'] === 'audio') {
        return 'data-play="audio" data-src="' . e(url('media/' . (int)$m['id'])) . '" data-title="' . e($label) . '"';
    }
    return '';
}

function main_media(array $media): ?array {
    foreach ($media as $m) if ($m['kind'] === 'audio' && $m['is_main']) return $m;
    foreach ($media as $m) if ($m['is_main']) return $m;
    return $media[0] ?? null;
}

function fmt_duration(?int $s): string {
    if (!$s) return '';
    return intdiv($s, 60) . ':' . str_pad((string)($s % 60), 2, '0', STR_PAD_LEFT);
}
function fmt_size(?int $b): string {
    if (!$b) return '';
    return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '') . ' MB' : max(1, (int)round($b / 1024)) . ' KB';
}

function youtube_id_from(string $url): ?string {
    $url = trim($url);
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) return $url;
    if (preg_match('#(?:youtu\.be/|[?&]v=|/embed/|/shorts/|/live/)([A-Za-z0-9_-]{11})#', $url, $m)) return $m[1];
    return null;
}

function use_label(array $u): string {
    $t = strtotime($u['used_on']);
    $day = mb_strtolower(mb_substr(RO_DAYS[(int)date('w', $t)], 0, 3));
    $slot = $u['slot'] === 'D' ? ' · dimineața' : ($u['slot'] === 'S' ? ' · după-amiaza' : '');
    return $day . ' ' . ro_date($u['used_on'], date('Y', $t) !== date('Y')) . $slot;
}

function ago_days(?string $date): string {
    if (!$date) return '';
    $d = (int)floor((strtotime(date('Y-m-d')) - strtotime($date)) / 86400);
    if ($d <= 0) return 'azi';
    if ($d === 1) return 'ieri';
    if ($d < 14) return "acum $d zile";
    if ($d < 60) return 'acum ' . (int)round($d / 7) . ' săpt.';
    if ($d < 365) return 'acum ' . (int)round($d / 30) . ' luni';
    $y = (int)round($d / 365);
    return $y === 1 ? 'acum 1 an' : "acum $y ani";
}

/** Servește un fișier cu suport pentru Range (necesar pentru derulare pe iPhone). */
function serve_file(string $path, string $mime, string $downloadName, bool $inline = true): never {
    $size = filesize($path);
    $start = 0;
    $end = $size - 1;
    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=86400');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($downloadName));
    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] === '' && $m[2] !== '') { $start = max(0, $size - (int)$m[2]); }
        else { $start = (int)$m[1]; if ($m[2] !== '') $end = min((int)$m[2], $size - 1); }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */$size");
            exit;
        }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Length: ' . ($end - $start + 1));
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') exit;
    while (ob_get_level()) ob_end_clean();
    $fp = fopen($path, 'rb');
    fseek($fp, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fp)) {
        $chunk = fread($fp, (int)min(65536, $left));
        echo $chunk;
        $left -= strlen($chunk);
        flush();
    }
    fclose($fp);
    exit;
}

function upload_limit_mb(): int {
    $toBytes = function (string $v): int {
        $v = trim($v); $n = (int)$v; $u = strtolower(substr($v, -1));
        return match ($u) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    };
    $b = min($toBytes((string)ini_get('upload_max_filesize')), $toBytes((string)ini_get('post_max_size')));
    return max(1, (int)floor($b / 1048576));
}
