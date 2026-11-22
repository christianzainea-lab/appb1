<?php
// Cărțile de cântări (Setări → Cărți): lista din filtrul cântărilor și din formularul cântării.
/** @var PDO $pdo */
$has = function (string $table, string $col) use ($pdo): bool {
    try { $pdo->query("SELECT $col FROM $table LIMIT 1"); return true; } catch (Throwable $e) { return false; }
};
$mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

if (!$has('song_books', 'id')) {
    $pdo->exec($mysql
        ? 'CREATE TABLE song_books (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(20) NOT NULL,
            sort INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        : 'CREATE TABLE song_books (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(20) NOT NULL,
            sort INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
          )');
}

// dacă există deja cântări cu o carte scrisă, cărțile intră în listă (o singură dată)
if ((int)$pdo->query('SELECT COUNT(*) FROM song_books')->fetchColumn() === 0) {
    $srcs = $pdo->query("SELECT source, COUNT(*) AS n FROM songs WHERE source IS NOT NULL AND source <> '' GROUP BY source ORDER BY n DESC, source")->fetchAll(PDO::FETCH_COLUMN);
    $ins = $pdo->prepare('INSERT INTO song_books (name, sort, created_at) VALUES (?, ?, ?)');
    foreach ($srcs as $i => $src) $ins->execute([mb_substr((string)$src, 0, 20), $i, date('Y-m-d H:i:s')]);
}
