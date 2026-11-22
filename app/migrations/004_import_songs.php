<?php
// Pachet pentru alte biserici: fără cântările B1. Se creează doar ocaziile obișnuite din anul bisericesc;
// cântările se adaugă din aplicație sau din Cântări → Importă (Excel / CSV).
/** @var PDO $pdo */
if ((int)$pdo->query('SELECT COUNT(*) FROM labels')->fetchColumn() > 0) return;
$ins = $pdo->prepare('INSERT INTO labels (name, kind, sort) VALUES (?, ?, ?)');
foreach (['Cină', 'Crăciun', 'An Nou', 'Florii', 'Paști', 'Rusalii', 'Botez', 'Binecuvântare'] as $i => $o) $ins->execute([$o, 'ocazie', $i]);
