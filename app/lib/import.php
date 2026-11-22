<?php
declare(strict_types=1);

/*
 * Importul cântărilor din Excel (.xlsx) sau CSV.
 * Pașii: fișierul se citește → rândurile se salvează temporar în storage/import → previzualizare → import.
 * Fără biblioteci: .xlsx e o arhivă zip cu XML, citită cu ZipArchive + SimpleXML.
 */

const IMPORT_MAX_MB = 5;
const IMPORT_MAX_ROWS = 5000;

/** Coloanele cunoscute: cheie internă => [antetul din model, alte denumiri acceptate]. */
const IMPORT_COLUMNS = [
    'title' => ['Titlu', ['titlu', 'titlul', 'title', 'nume', 'denumire', 'cantare', 'cantarea', 'song']],
    'book' => ['Carte', ['carte', 'cartea', 'sursa', 'culegere', 'book', 'source', 'songbook']],
    'number' => ['Număr', ['numar', 'numarul', 'nr', 'no', 'number', 'num']],
    'key' => ['Ton', ['ton', 'tonul', 'tonalitate', 'gama', 'key']],
    'author' => ['Autor', ['autor', 'artist', 'autorartist', 'compozitor', 'author']],
    'bpm' => ['BPM', ['bpm', 'tempo']],
    'ocazii' => ['Ocazii', ['ocazii', 'ocazie', 'occasions', 'occasion']],
    'teme' => ['Teme', ['teme', 'tema', 'etichete', 'eticheta', 'themes', 'tags', 'labels']],
    'youtube' => ['YouTube', ['youtube', 'link', 'linkuri', 'linkyoutube', 'video', 'inregistrare', 'inregistrari']],
    'lyrics' => ['Versuri', ['versuri', 'versurile', 'text', 'lyrics']],
    'notes' => ['Note', ['note', 'notite', 'observatii', 'mentiuni', 'notes']],
];

function import_plain(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't']);
    return (string)preg_replace('/[^a-z0-9]+/', '', $s);
}

/** Text curat: diacritice corecte (ș, ț cu virgulă), fără spații în plus. */
function import_clean(string $s, bool $multiline = false): string {
    $s = strtr($s, ['ş' => 'ș', 'Ş' => 'Ș', 'ţ' => 'ț', 'Ţ' => 'Ț', "\r\n" => "\n", "\r" => "\n", "\u{00A0}" => ' ']);
    if (!$multiline) return trim((string)preg_replace('/\s+/u', ' ', $s));
    return trim((string)preg_replace("/[ \t]+\n/", "\n", $s));
}

/* ---------- citirea fișierului ---------- */

/** Întoarce [rânduri (liste de celule), eroare]. Primul rând e antetul. */
function import_read_file(string $path, string $origName): array {
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext === 'xls') return [[], 'Fișierele .xls (Excel vechi) nu se pot citi. În Excel alege Fișier → Salvare ca → „Registru de lucru Excel (.xlsx)” sau „CSV UTF-8”.'];
    if ($ext === 'xlsx') return import_read_xlsx($path);
    if (in_array($ext, ['csv', 'txt'], true)) return import_read_csv($path);
    return [[], 'Alege un fișier Excel (.xlsx) sau CSV.'];
}

function import_read_csv(string $path): array {
    $raw = (string)file_get_contents($path);
    if (str_starts_with($raw, "\xEF\xBB\xBF")) $raw = substr($raw, 3);
    elseif (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
        $raw = (string)mb_convert_encoding(substr($raw, 2), 'UTF-8', str_starts_with($raw, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE');
    }
    // CSV-ul salvat de Excel în română vine adesea în Windows-1250
    if (!mb_check_encoding($raw, 'UTF-8')) {
        $conv = @iconv('CP1250', 'UTF-8//IGNORE', $raw);
        $raw = $conv !== false ? $conv : mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-2');
    }
    if (trim($raw) === '') return [[], 'Fișierul este gol.'];
    $first = strtok($raw, "\n");
    $counts = [';' => substr_count($first, ';'), ',' => substr_count($first, ','), "\t" => substr_count($first, "\t")];
    arsort($counts);
    $sep = (string)array_key_first($counts);
    $fp = fopen('php://temp', 'w+');
    fwrite($fp, $raw);
    rewind($fp);
    $rows = [];
    while (($r = fgetcsv($fp, 0, $sep, '"', '')) !== false) {
        if ($r === [null]) continue;
        $rows[] = array_map(fn($c) => (string)$c, $r);
        if (count($rows) > IMPORT_MAX_ROWS + 1) break;
    }
    fclose($fp);
    return [$rows, null];
}

function import_read_xlsx(string $path): array {
    if (!class_exists('ZipArchive')) return [[], 'Serverul nu poate citi fișiere Excel (lipsește extensia zip). Salvează fișierul ca CSV și încearcă din nou.'];
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return [[], 'Fișierul Excel nu se poate deschide. Verifică că e un .xlsx salvat corect.'];
    $read = function (string $name) use ($zip): ?string {
        $st = $zip->statName($name);
        if (!$st || $st['size'] > 60 * 1048576) return null; // protecție la arhive „umflate”
        $s = $zip->getFromName($name);
        return $s === false ? null : $s;
    };
    $xml = fn(?string $s) => $s === null ? null : @simplexml_load_string($s, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);

    // primul tabel din registru
    $sheetPath = 'xl/worksheets/sheet1.xml';
    $wb = $xml($read('xl/workbook.xml'));
    $rels = $xml($read('xl/_rels/workbook.xml.rels'));
    if ($wb && $rels && isset($wb->sheets->sheet[0])) {
        $rid = (string)($wb->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');
        foreach ($rels->Relationship as $rel) {
            if ((string)$rel['Id'] === $rid) {
                $t = ltrim((string)$rel['Target'], '/');
                $sheetPath = str_starts_with($t, 'xl/') ? $t : 'xl/' . $t;
            }
        }
    }
    $shared = [];
    if ($ss = $xml($read('xl/sharedStrings.xml'))) {
        foreach ($ss->si as $si) {
            if (isset($si->t)) { $shared[] = (string)$si->t; continue; }
            $txt = '';
            foreach ($si->r as $run) $txt .= (string)$run->t;
            $shared[] = $txt;
        }
    }
    $sheet = $xml($read($sheetPath));
    $zip->close();
    if (!$sheet || !isset($sheet->sheetData)) return [[], 'Nu găsesc niciun tabel în fișierul Excel.'];

    $colIndex = function (string $ref): int {
        $letters = preg_replace('/\d+/', '', strtoupper($ref));
        $n = 0;
        foreach (str_split((string)$letters) as $ch) $n = $n * 26 + (ord($ch) - 64);
        return max(0, $n - 1);
    };
    $rows = [];
    foreach ($sheet->sheetData->row as $r) {
        $cells = [];
        $next = 0;
        foreach ($r->c as $c) {
            $i = isset($c['r']) ? $colIndex((string)$c['r']) : $next;
            $next = $i + 1;
            $t = (string)($c['t'] ?? '');
            if ($t === 's') $v = $shared[(int)$c->v] ?? '';
            elseif ($t === 'inlineStr') {
                $v = isset($c->is->t) ? (string)$c->is->t : '';
                if (!isset($c->is->t)) foreach ($c->is->r as $run) $v .= (string)$run->t;
            } elseif ($t === 'b') $v = ((string)$c->v) === '1' ? 'DA' : 'NU';
            else {
                $v = (string)$c->v;
                // numerele din Excel: 245 vine ca „245”, uneori ca „245.0” sau „2.45E2”
                if ($t !== 'str' && is_numeric($v) && (float)$v == (int)round((float)$v) && abs((float)$v) < 1e9) $v = (string)(int)round((float)$v);
            }
            $cells[$i] = $v;
        }
        if (!$cells) continue;
        $max = max(array_keys($cells));
        $line = [];
        for ($k = 0; $k <= $max; $k++) $line[] = $cells[$k] ?? '';
        $rows[] = $line;
        if (count($rows) > IMPORT_MAX_ROWS + 1) break;
    }
    return [$rows, null];
}

/* ---------- interpretarea rândurilor ---------- */

/** Rândurile brute → [cântări normalizate (cu avertismente), coloanele recunoscute, eroare]. */
function import_parse(array $rows): array {
    // antetul: primul rând care are o coloană „Titlu”
    $map = [];
    $headerAt = null;
    foreach (array_slice($rows, 0, 10, true) as $ri => $r) {
        $m = [];
        foreach ($r as $ci => $h) {
            $p = import_plain((string)$h);
            foreach (IMPORT_COLUMNS as $key => [, $aliases]) {
                if (!isset($m[$key]) && in_array($p, $aliases, true)) { $m[$key] = $ci; break; }
            }
        }
        if (isset($m['title'])) { $map = $m; $headerAt = $ri; break; }
    }
    if ($headerAt === null) return [[], [], 'Nu găsesc coloana „Titlu” în primul rând. Folosește modelul de mai jos (prima linie: Titlu, Carte, Număr, Ton …).'];

    $data = array_slice($rows, $headerAt + 1, null, true);
    if (count($data) > IMPORT_MAX_ROWS) return [[], [], 'Fișierul are prea multe rânduri (maximum ' . IMPORT_MAX_ROWS . ' odată). Împarte-l în mai multe fișiere.'];
    $out = [];
    foreach ($data as $ri => $r) {
        $get = fn(string $k, bool $ml = false) => isset($map[$k]) ? import_clean((string)($r[$map[$k]] ?? ''), $ml) : '';
        $song = [
            'line' => $ri + 1,
            'title' => $get('title'), 'book' => $get('book'), 'number' => $get('number'), 'key' => $get('key'),
            'author' => $get('author'), 'bpm' => $get('bpm'), 'ocazii' => $get('ocazii', true), 'teme' => $get('teme', true),
            'youtube' => $get('youtube', true), 'lyrics' => $get('lyrics', true), 'notes' => $get('notes', true),
        ];
        $empty = true;
        foreach ($song as $k => $v) if ($k !== 'line' && $v !== '') { $empty = false; break; }
        if ($empty) continue; // rândurile goale se sar fără mesaj
        $out[] = $song;
    }
    $found = array_map(fn($k) => IMPORT_COLUMNS[$k][0], array_keys($map));
    return [$out, $found, $out ? null : 'Fișierul nu are nicio cântare sub antet.'];
}

/** Tonul scris în fișier → tonul din aplicație (ex. „G”, „Sol”, „F#m”, „Mib” → „Sol”, „Fa♯ m”, „Mi♭”), sau null dacă nu-l recunoaștem. */
function import_key(string $k): ?string {
    $k = trim($k);
    if ($k === '') return '';
    foreach (array_merge(standard_keys(), array_column(custom_keys(), 'name')) as $known) {
        if (mb_strtolower($known) === mb_strtolower($k)) return $known;
    }
    $en = ['C' => 'Do', 'D' => 'Re', 'E' => 'Mi', 'F' => 'Fa', 'G' => 'Sol', 'A' => 'La', 'B' => 'Si', 'H' => 'Si'];
    $acc = fn(string $a) => match (mb_strtolower($a)) { '#', '♯', 'diez' => '♯', 'b', '♭', 'bemol' => '♭', default => '' };
    if (preg_match('/^([A-H])\s*(#|♯|b|♭)?\s*(m|min|minor|moll)?$/u', $k, $m)) {
        $note = $en[$m[1]]; $a = $acc($m[2] ?? ''); $minor = !empty($m[3]);
    } elseif (preg_match('/^(do|re|mi|fa|sol|la|si)\s*(#|♯|b|♭|diez|bemol)?\s*(m|min|minor|major)?$/iu', $k, $m)) {
        $note = mb_convert_case(mb_strtolower($m[1]), MB_CASE_TITLE); $a = $acc($m[2] ?? ''); $minor = !empty($m[3]) && mb_strtolower($m[3]) !== 'major';
    } else {
        return null;
    }
    $name = $note . $a;
    $enh = ['Do♯' => 'Do♯', 'Re♭' => 'Re♭', 'Re♯' => 'Mi♭', 'Sol♭' => 'Fa♯', 'Sol♯' => 'La♭', 'La♯' => 'Si♭', 'Fa♭' => 'Mi', 'Mi♯' => 'Fa', 'Si♯' => 'Do', 'Do♭' => 'Si'];
    if (!in_array($name, SONG_KEYS, true)) $name = $enh[$name] ?? $name;
    if (!in_array($name, SONG_KEYS, true)) return null;
    return $name . ($minor ? ' m' : '');
}

function import_split(string $s): array {
    $parts = preg_split('/\s*[,;|\n]\s*/u', $s) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && !in_array(mb_strtolower($p), array_map('mb_strtolower', $out), true)) $out[] = mb_substr($p, 0, 60);
    }
    return $out;
}

function import_title_key(string $t): string { return import_plain($t); }

/**
 * Compară rândurile cu baza de date. Fiecare rând primește:
 * status: new | exists | dupfile | error, match (id-ul cântării existente), warn (listă), plus valorile curățate.
 */
function import_plan(array $songs): array {
    $byRef = []; $byTitle = [];
    foreach (rows('SELECT id, title, source, source_no FROM songs') as $s) {
        $src = mb_strtolower(trim((string)$s['source']));
        if ($src !== '' && trim((string)$s['source_no']) !== '') $byRef[$src . '|' . mb_strtolower(trim((string)$s['source_no']))] = (int)$s['id'];
        $byTitle[import_title_key($s['title']) . '|' . $src] ??= (int)$s['id'];
    }
    $books = [];
    foreach (books_all() as $b) $books[mb_strtolower($b['name'])] = $b['name'];
    $seen = [];
    $newBooks = [];
    foreach ($songs as &$r) {
        $r['warn'] = [];
        $r['status'] = 'new';
        $r['match'] = null;
        if (mb_strlen($r['title']) < 2) { $r['status'] = 'error'; $r['warn'][] = 'Lipsește titlul.'; continue; }
        if (mb_strlen($r['title']) > 190) { $r['title'] = mb_substr($r['title'], 0, 190); $r['warn'][] = 'Titlul a fost scurtat.'; }
        // cartea: cea din listă (fără să conteze literele mari) sau una nouă
        if ($r['book'] !== '') {
            if (mb_strlen($r['book']) > SONG_BOOK_MAX) { $r['warn'][] = 'Cartea „' . $r['book'] . '” are peste ' . SONG_BOOK_MAX . ' caractere; rămâne fără carte.'; $r['book'] = ''; }
            elseif (isset($books[mb_strtolower($r['book'])])) $r['book'] = $books[mb_strtolower($r['book'])];
            else { $books[mb_strtolower($r['book'])] = $r['book']; $newBooks[] = $r['book']; $r['warn'][] = 'Carte nouă: ' . $r['book'] . ' (se adaugă în Setări → Cărți).'; }
        }
        if (mb_strlen($r['number']) > 20) { $r['warn'][] = 'Numărul are peste 20 de caractere; nu se importă.'; $r['number'] = ''; }
        if ($r['key'] !== '') {
            $k = import_key($r['key']);
            if ($k === null || mb_strlen($k) > SONG_KEY_MAX) { $r['warn'][] = 'Ton necunoscut „' . $r['key'] . '”; rămâne fără ton.'; $r['key'] = ''; }
            else $r['key'] = $k;
        }
        if ($r['bpm'] !== '') {
            if (!ctype_digit($r['bpm']) || (int)$r['bpm'] < 30 || (int)$r['bpm'] > 250) { $r['warn'][] = 'BPM „' . $r['bpm'] . '” ignorat (30–250).'; $r['bpm'] = ''; }
        }
        $r['author'] = mb_substr($r['author'], 0, 120);
        $r['ocazii_list'] = import_split($r['ocazii']);
        $r['teme_list'] = import_split($r['teme']);
        $r['yt'] = [];
        foreach (preg_split('/[\s,;]+/u', $r['youtube']) ?: [] as $u) {
            if ($u === '') continue;
            $id = youtube_id_from($u);
            if ($id) { if (!in_array($id, $r['yt'], true)) $r['yt'][] = $id; }
            else $r['warn'][] = 'Link YouTube nerecunoscut: ' . mb_substr($u, 0, 60);
        }
        // dubluri: întâi carte + număr, apoi titlu (în aceeași carte)
        $src = mb_strtolower($r['book']);
        $refKey = ($src !== '' && $r['number'] !== '') ? $src . '|' . mb_strtolower($r['number']) : null;
        $titleKey = import_title_key($r['title']) . '|' . $src;
        $fileKey = $refKey ?? 't:' . $titleKey;
        if (isset($seen[$fileKey])) { $r['status'] = 'dupfile'; $r['warn'][] = 'Apare deja mai sus în fișier (rândul ' . $seen[$fileKey] . ').'; continue; }
        $seen[$fileKey] = $r['line'];
        $match = ($refKey !== null ? ($byRef[$refKey] ?? null) : null) ?? $byTitle[$titleKey] ?? null;
        if ($match) { $r['status'] = 'exists'; $r['match'] = $match; }
    }
    unset($r);
    return [$songs, array_values(array_unique($newBooks))];
}

/** Importă. $update = true: cântările care există deja primesc valorile completate în fișier. Întoarce [noi, actualizate]. */
function import_apply(array $plan, bool $update, array $me): array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $now = now();
        $bookNames = array_map('mb_strtolower', array_column(rows('SELECT name FROM song_books'), 'name'));
        $labelId = function (string $name, string $kind): int {
            $id = val('SELECT id FROM labels WHERE LOWER(name) = LOWER(?) AND kind = ?', [$name, $kind]);
            if ($id) return (int)$id;
            q('INSERT INTO labels (name, kind, sort) VALUES (?, ?, ?)', [$name, $kind, (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM labels WHERE kind = ?', [$kind])]);
            return (int)db()->lastInsertId();
        };
        $added = 0; $updated = 0;
        foreach ($plan as $r) {
            if ($r['status'] === 'error' || $r['status'] === 'dupfile') continue;
            if ($r['status'] === 'exists' && !$update) continue;
            if ($r['book'] !== '' && !in_array(mb_strtolower($r['book']), $bookNames, true)) {
                q('INSERT INTO song_books (name, sort, created_at) VALUES (?, ?, ?)', [$r['book'], (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM song_books'), $now]);
                $bookNames[] = mb_strtolower($r['book']);
            }
            $fields = ['title' => $r['title'], 'author' => $r['author'], 'source' => $r['book'], 'source_no' => $r['number'], 'song_key' => $r['key'],
                'bpm' => $r['bpm'], 'lyrics' => $r['lyrics'], 'notes' => $r['notes']];
            if ($r['status'] === 'new') {
                q('INSERT INTO songs (title, author, source, source_no, song_key, bpm, lyrics, notes, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                    $r['title'], $r['author'] ?: null, $r['book'] ?: null, $r['number'] ?: null, $r['key'] ?: null, $r['bpm'] === '' ? null : (int)$r['bpm'],
                    $r['lyrics'] ?: null, $r['notes'] ?: null, $me['id'], $now, $now]);
                $sid = (int)$pdo->lastInsertId();
                $added++;
            } else {
                $sid = (int)$r['match'];
                $set = []; $vals = [];
                foreach ($fields as $col => $v) {
                    if ($v === '' || $col === 'title') continue; // doar ce e completat; titlul rămâne cel din aplicație
                    $set[] = "$col = ?"; $vals[] = $col === 'bpm' ? (int)$v : $v;
                }
                if ($set || $r['ocazii_list'] || $r['teme_list'] || $r['yt']) {
                    $set[] = 'updated_at = ?'; $vals[] = $now; $vals[] = $sid;
                    q('UPDATE songs SET ' . implode(', ', $set) . ' WHERE id = ?', $vals);
                    $updated++;
                }
            }
            foreach (['ocazie' => $r['ocazii_list'], 'tema' => $r['teme_list']] as $kind => $names) {
                foreach ($names as $n) {
                    $lid = $labelId($n, $kind);
                    if (!val('SELECT COUNT(*) FROM song_labels WHERE song_id = ? AND label_id = ?', [$sid, $lid])) q('INSERT INTO song_labels (song_id, label_id) VALUES (?, ?)', [$sid, $lid]);
                }
            }
            $hasMain = (bool)val('SELECT COUNT(*) FROM song_media WHERE song_id = ? AND is_main = 1', [$sid]);
            $sort = (int)val('SELECT COALESCE(MAX(sort), -1) + 1 FROM song_media WHERE song_id = ?', [$sid]);
            foreach ($r['yt'] as $yid) {
                if (val("SELECT COUNT(*) FROM song_media WHERE song_id = ? AND kind = 'youtube' AND youtube_id = ?", [$sid, $yid])) continue;
                q('INSERT INTO song_media (song_id, kind, title, youtube_id, url, is_main, sort, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$sid, 'youtube', null, $yid, 'https://youtu.be/' . $yid, $hasMain ? 0 : 1, $sort++, $now]);
                $hasMain = true;
            }
        }
        $pdo->commit();
        return [$added, $updated];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/* ---------- fișierul temporar ---------- */

function import_dir(): string {
    $d = STORAGE_DIR . '/import';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    foreach (glob($d . '/*.json') ?: [] as $old) if (filemtime($old) < time() - 86400) @unlink($old); // curățenie: mai vechi de o zi
    return $d;
}

function import_store(array $songs, array $found, string $fileName, int $userId): string {
    $tok = bin2hex(random_bytes(12));
    file_put_contents(import_dir() . "/$tok.json", json_encode(['user' => $userId, 'file' => $fileName, 'cols' => $found, 'songs' => $songs], JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $tok;
}

function import_load(string $tok, int $userId): ?array {
    if (!preg_match('/^[a-f0-9]{24}$/', $tok)) return null;
    $f = import_dir() . "/$tok.json";
    if (!is_file($f)) return null;
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) && (int)($d['user'] ?? 0) === $userId ? $d : null;
}

function import_forget(string $tok): void {
    if (preg_match('/^[a-f0-9]{24}$/', $tok)) @unlink(import_dir() . "/$tok.json");
}

/* ---------- modelul de completat ---------- */

function import_template_rows(): array {
    $head = array_values(array_map(fn($c) => $c[0], IMPORT_COLUMNS));
    $ex = ['Exemplu – șterge acest rând', 'CR', '245', 'Sol', '', '72', 'Crăciun', 'Laudă, Har', 'https://youtu.be/xxxxxxxxxxx', "Strofa 1, rândul 1\nStrofa 1, rândul 2", ''];
    return [$head, $ex];
}

function import_template_csv(): string {
    $fp = fopen('php://temp', 'w+');
    fwrite($fp, "\xEF\xBB\xBF"); // ca Excel să citească diacriticele
    foreach (import_template_rows() as $r) fputcsv($fp, $r, ';', '"', '');
    rewind($fp);
    $s = (string)stream_get_contents($fp);
    fclose($fp);
    return $s;
}

/** Un .xlsx minimal (un singur tabel, antet îngroșat). Întoarce calea fișierului temporar sau null. */
function import_template_xlsx(): ?string {
    if (!class_exists('ZipArchive')) return null;
    $x = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $col = fn(int $i) => ($i >= 26 ? chr(64 + intdiv($i, 26)) : '') . chr(65 + $i % 26);
    $sheetRows = '';
    foreach (import_template_rows() as $ri => $r) {
        $cells = '';
        foreach ($r as $ci => $v) {
            $ref = $col($ci) . ($ri + 1);
            $cells .= '<c r="' . $ref . '" t="inlineStr"' . ($ri === 0 ? ' s="1"' : ' s="2"') . '><is><t xml:space="preserve">' . $x($v) . '</t></is></c>';
        }
        $sheetRows .= '<row r="' . ($ri + 1) . '">' . $cells . '</row>';
    }
    $widths = [34, 10, 9, 9, 22, 7, 18, 22, 32, 40, 24];
    $cols = '';
    foreach ($widths as $i => $w) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Cântări" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="3"><xf/><xf fontId="1" applyFont="1"/><xf applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs></styleSheet>',
        'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>' . $cols . '</cols><sheetData>' . $sheetRows . '</sheetData></worksheet>',
    ];
    $tmp = tempnam(sys_get_temp_dir(), 'tpl');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return null;
    foreach ($files as $n => $c) $zip->addFromString($n, $c);
    $zip->close();
    return $tmp;
}
