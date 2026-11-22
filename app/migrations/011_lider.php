<?php
// B1 Volunteers · „Lider” pe fiecare serviciu: o poziție specială, deschisă oricui are cont,
// singura care se poate cumula cu o altă poziție la același eveniment.
/** @var PDO $pdo */
$has = function (string $table, string $col) use ($pdo): bool {
    try { $pdo->query("SELECT $col FROM $table LIMIT 1"); return true; } catch (Throwable $e) { return false; }
};
if (!$has('positions', 'is_leader')) $pdo->exec('ALTER TABLE positions ADD COLUMN is_leader TINYINT NOT NULL DEFAULT 0');
if (!$has('templates', 'leader_id')) $pdo->exec('ALTER TABLE templates ADD COLUMN leader_id INT UNSIGNED NULL');

$pid = (int)$pdo->query('SELECT id FROM positions WHERE is_leader = 1 LIMIT 1')->fetchColumn();
if (!$pid) {
    $pdo->prepare('INSERT INTO position_groups (name, sort) VALUES (?, ?)')->execute(['Conducere', -100]);
    $gid = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO positions (group_id, name, sort, is_leader) VALUES (?, ?, ?, ?)')->execute([$gid, 'Lider', 0, 1]);
    $pid = (int)$pdo->lastInsertId();
}
// un loc de lider la fiecare serviciu de azi înainte care nu are deja unul
$st = $pdo->prepare("SELECT e.id FROM events e WHERE e.status <> 'cancelled' AND e.event_date >= ? AND NOT EXISTS (SELECT 1 FROM event_slots s WHERE s.event_id = e.id AND s.position_id = ?)");
$st->execute([date('Y-m-d'), $pid]);
$ins = $pdo->prepare('INSERT INTO event_slots (event_id, position_id, sort) VALUES (?, ?, -1)');
foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $eid) $ins->execute([(int)$eid, $pid]);
