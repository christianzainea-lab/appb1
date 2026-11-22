<?php
// Date de pornire: doar ce trebuie ca aplicația să meargă (fără utilizatori sau cântări de test).
/** @var PDO $pdo */

$groups = [
    ['Laudă · voce și instrumente', [
        ['Voce', ['Solist', 'Soprană', 'Alto', 'Tenor', 'Bariton / Bas']],
        ['Clape', []], ['Tobe', []], ['Bas', []], ['Vioară', []], ['Chitară acustică', []], ['Shaker', []],
    ]],
    ['Tehnic și media', [
        ['Sunet', []], ['Proiecție', []], ['Video', []], ['Cameră', []],
    ]],
];

$pos = []; // nume => id
$gs = 0;
foreach ($groups as [$gname, $items]) {
    $pdo->prepare('INSERT INTO position_groups (name, sort) VALUES (?, ?)')->execute([$gname, $gs++]);
    $gid = (int)$pdo->lastInsertId();
    $ps = 0;
    foreach ($items as [$pname, $parts]) {
        $pdo->prepare('INSERT INTO positions (group_id, name, sort) VALUES (?, ?, ?)')->execute([$gid, $pname, $ps++]);
        $pid = (int)$pdo->lastInsertId();
        $pos[$pname] = $pid;
        foreach ($parts as $i => $part) {
            $pdo->prepare('INSERT INTO position_parts (position_id, name, sort) VALUES (?, ?, ?)')->execute([$pid, $part, $i]);
        }
    }
}

$music = ['Voce' => 2, 'Clape' => 1, 'Tobe' => 1, 'Bas' => 1, 'Vioară' => 1, 'Chitară acustică' => 1, 'Shaker' => 1];
$tech = ['Sunet' => 1, 'Proiecție' => 1, 'Video' => 1, 'Cameră' => 2];
$all = $music + $tech;

$sundayItems = [
    ['item', 'Rugăciune de început'], ['song', 'Cântare'], ['song', 'Cântare'], ['song', 'Cântare'],
    ['item', 'Anunțuri'], ['item', 'Citire biblică'], ['item', 'Predică'], ['song', 'Cântare'],
    ['item', 'Rugăciune de încheiere'],
];
$cinaItems = [
    ['item', 'Rugăciune de început'], ['song', 'Cântare'], ['song', 'Cântare'], ['song', 'Cântare'],
    ['item', 'Anunțuri'], ['item', 'Citire biblică'], ['item', 'Predică'], ['item', 'Cina Domnului'],
    ['song', 'Cântare'], ['item', 'Rugăciune de încheiere'],
];

// weekday: 0 = duminică … 4 = joi; rule: every | first | not_first | none
$templates = [
    ['Joi – Studiu', 4, 'every', '19:00', null, $tech,
        [['item', 'Rugăciune de început'], ['item', 'Studiu biblic'], ['item', 'Rugăciune de încheiere']]],
    ['Duminică dimineața', 0, 'not_first', '10:00', '09:00', $all, $sundayItems],
    ['Duminică dimineața – Cina Domnului', 0, 'first', '10:00', '09:00', $all, $cinaItems],
    ['Duminică după-amiaza', 0, 'first', '17:00', '16:00', $all, $sundayItems],
];
foreach ($templates as $i => [$name, $wd, $rule, $time, $reh, $slots, $items]) {
    $pdo->prepare('INSERT INTO templates (name, weekday, rule, start_time, rehearsal_time, sort) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$name, $wd, $rule, $time, $reh, $i]);
    $tid = (int)$pdo->lastInsertId();
    foreach ($slots as $pname => $n) {
        $pdo->prepare('INSERT INTO template_positions (template_id, position_id, slots) VALUES (?, ?, ?)')
            ->execute([$tid, $pos[$pname], $n]);
    }
    foreach ($items as $j => [$kind, $title]) {
        $pdo->prepare('INSERT INTO template_items (template_id, sort, kind, title) VALUES (?, ?, ?, ?)')
            ->execute([$tid, $j, $kind, $title]);
    }
}

$wa = "*{eveniment} – {data}, {ora}*\nRepetiție: {repetitie}\n\n*Program*\n{ordinea}\n\n*Echipa*\n{echipa}\n\nÎnregistrări: {link}";
$defaults = [
    'app_name' => 'B1 Volunteers',
    'church_name' => '',
    'whatsapp_template' => $wa,
    'whatsapp_include_key' => '1',
    'whatsapp_include_items' => '1',
    'whatsapp_first_names' => '1',
];
foreach ($defaults as $k => $v) {
    $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?)')->execute([$k, $v]);
}
