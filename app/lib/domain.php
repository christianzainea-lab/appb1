<?php
declare(strict_types=1);

/** Grupuri cu pozițiile și partidele lor: [ ['id','name','positions'=>[['id','name','parts'=>[...] ]]] ] */
/** Id-ul poziției „Lider” (0 dacă nu există încă). */
function leader_position_id(): int {
    static $id = null;
    if ($id === null) { try { $id = (int)val('SELECT id FROM positions WHERE is_leader = 1 LIMIT 1'); } catch (Throwable $e) { $id = 0; } }
    return $id;
}

/** Grupele cu pozițiile lor. „Lider” apare doar cu $withLeader (nu e o poziție din profil). */
function position_tree(bool $withLeader = false): array {
    $groups = rows('SELECT * FROM position_groups ORDER BY sort, id');
    $lp = leader_position_id();
    $positions = array_values(array_filter(rows('SELECT * FROM positions ORDER BY sort, id'), fn($p) => $withLeader || (int)$p['id'] !== $lp));
    $parts = rows('SELECT * FROM position_parts ORDER BY sort, id');
    $counts = [];
    foreach (rows('SELECT position_id, COUNT(*) AS n FROM user_positions up JOIN users u ON u.id = up.user_id WHERE u.status = ? GROUP BY position_id', ['active']) as $r) {
        $counts[(int)$r['position_id']] = (int)$r['n'];
    }
    $byPos = [];
    foreach ($parts as $p) $byPos[(int)$p['position_id']][] = $p;
    $byGroup = [];
    foreach ($positions as $p) {
        $p['parts'] = $byPos[(int)$p['id']] ?? [];
        $p['people'] = $counts[(int)$p['id']] ?? 0;
        $byGroup[(int)$p['group_id']][] = $p;
    }
    foreach ($groups as &$g) $g['positions'] = $byGroup[(int)$g['id']] ?? [];
    unset($g);
    return array_values(array_filter($groups, fn($g) => $g['positions'] || !$lp || (int)val('SELECT COUNT(*) FROM positions WHERE group_id = ? AND id = ?', [$g['id'], $lp]) === 0));
}

function user_position_ids(int $uid): array {
    return array_map('intval', q('SELECT position_id FROM user_positions WHERE user_id = ?', [$uid])->fetchAll(PDO::FETCH_COLUMN));
}
function user_part_ids(int $uid): array {
    return array_map('intval', q('SELECT part_id FROM user_parts WHERE user_id = ?', [$uid])->fetchAll(PDO::FETCH_COLUMN));
}

/** Salvează pozițiile și partidele alese (doar id-uri valide). */
function save_user_positions(int $uid, array $positionIds, array $partIds): void {
    $validPos = array_map('intval', q('SELECT id FROM positions')->fetchAll(PDO::FETCH_COLUMN));
    $validParts = [];
    foreach (rows('SELECT id, position_id FROM position_parts') as $r) $validParts[(int)$r['id']] = (int)$r['position_id'];
    $positionIds = array_values(array_intersect(array_map('intval', $positionIds), $validPos));
    $partIds = array_values(array_filter(array_map('intval', $partIds), fn($id) => isset($validParts[$id])));
    // o partidă implică poziția ei
    foreach ($partIds as $pid) if (!in_array($validParts[$pid], $positionIds, true)) $positionIds[] = $validParts[$pid];

    $pdo = db();
    $pdo->beginTransaction();
    q('DELETE FROM user_positions WHERE user_id = ?', [$uid]);
    q('DELETE FROM user_parts WHERE user_id = ?', [$uid]);
    foreach (array_unique($positionIds) as $id) q('INSERT INTO user_positions (user_id, position_id) VALUES (?, ?)', [$uid, $id]);
    foreach (array_unique($partIds) as $id) q('INSERT INTO user_parts (user_id, part_id) VALUES (?, ?)', [$uid, $id]);
    $pdo->commit();
}

/** Etichetele de poziții ale unui om, ex. ["Voce · Alto", "Clape"]. */
function user_position_labels(int $uid): array {
    $pos = rows('SELECT p.id, p.name FROM user_positions up JOIN positions p ON p.id = up.position_id JOIN position_groups g ON g.id = p.group_id WHERE up.user_id = ? ORDER BY g.sort, p.sort', [$uid]);
    $parts = [];
    foreach (rows('SELECT pp.position_id, pp.name FROM user_parts u JOIN position_parts pp ON pp.id = u.part_id WHERE u.user_id = ? ORDER BY pp.sort', [$uid]) as $r) {
        $parts[(int)$r['position_id']][] = $r['name'];
    }
    $out = [];
    foreach ($pos as $p) {
        $out[] = $p['name'] . (isset($parts[(int)$p['id']]) ? ' · ' . implode(', ', $parts[(int)$p['id']]) : '');
    }
    return $out;
}

function avatar_html(array $u, int $size = 36, string $bg = '#F6E7E5', string $fg = '#650B0A'): string {
    $style = "width:{$size}px;height:{$size}px;font-size:" . max(11, (int)round($size / 2.8)) . "px;background:$bg;color:$fg";
    if (!empty($u['avatar']) && is_file(STORAGE_DIR . '/avatars/' . $u['avatar'])) {
        return '<img class="avatar" src="' . e(url('poza/' . $u['avatar'])) . '" alt="" style="width:' . $size . 'px;height:' . $size . 'px">';
    }
    return '<span class="avatar" aria-hidden="true" style="' . $style . '">' . e(initials($u['name'])) . '</span>';
}

function invite_valid(?array $inv): bool {
    if (!$inv || !$inv['active']) return false;
    if ($inv['expires_at'] && strtotime($inv['expires_at']) < time()) return false;
    if ($inv['kind'] === 'personal' && $inv['uses'] > 0) return false;
    return true;
}

function notify_admins_new_request(array $u): void {
    $admins = rows("SELECT * FROM users WHERE role = 'admin' AND status = 'active'");
    $link = full_url('setari/persoane');
    foreach ($admins as $a) {
        send_mail($a['email'], 'Cerere nouă de înscriere: ' . $u['name'],
            "{$u['name']} ({$u['email']}) s-a înscris și așteaptă aprobarea ta.\n\n$link",
            ['Vezi cererea', $link]);
    }
}
