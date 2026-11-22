<?php
declare(strict_types=1);

/** Jurnalul complet al modificărilor. Doar administratori; nu apare în niciun meniu. */
function debuglog(): void {
    require_role('admin');
    header('X-Robots-Tag: noindex, nofollow');
    $area = (string)($_GET['zona'] ?? '');
    $search = trim((string)($_GET['q'] ?? ''));
    $from = (string)($_GET['de_la'] ?? '');
    $to = (string)($_GET['pana_la'] ?? '');
    $page = max(1, (int)($_GET['pagina'] ?? 1));
    $per = 100;
    $where = ['1 = 1'];
    $params = [];
    if (isset(ACTIVITY_AREAS[$area])) { $where[] = 'area = ?'; $params[] = $area; }
    if ($search !== '') {
        $where[] = '(summary LIKE ? OR object_label LIKE ? OR user_name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }
    if (valid_date($from)) { $where[] = 'created_at >= ?'; $params[] = $from . ' 00:00:00'; }
    if (valid_date($to)) { $where[] = 'created_at <= ?'; $params[] = $to . ' 23:59:59'; }
    $sqlWhere = implode(' AND ', $where);
    $total = (int)val("SELECT COUNT(*) FROM activity_log WHERE $sqlWhere", $params);
    $items = rows("SELECT * FROM activity_log WHERE $sqlWhere ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
    $songs = array_map('intval', q('SELECT id FROM songs')->fetchAll(PDO::FETCH_COLUMN));
    $events = array_map('intval', q("SELECT id FROM events WHERE status <> 'cancelled'")->fetchAll(PDO::FETCH_COLUMN));
    render('pages/debuglog', [
        'nav' => '', 'items' => $items, 'total' => $total, 'page' => $page, 'pages' => max(1, (int)ceil($total / $per)),
        'area' => $area, 'search' => $search, 'from' => $from, 'to' => $to,
        'songs' => array_flip($songs), 'events' => array_flip($events), 'build' => build_info(),
    ]);
}
