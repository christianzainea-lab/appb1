<?php
// B1 Volunteers · tonuri adăugate de editori (pe lângă cele 26 standard) și tonuri de până la 20 de caractere
/** @var PDO $pdo */
$has = function (string $table, string $col) use ($pdo): bool {
    try { $pdo->query("SELECT $col FROM $table LIMIT 1"); return true; } catch (Throwable $e) { return false; }
};
$mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

if (!$has('song_keys', 'id')) {
    $pdo->exec($mysql
        ? 'CREATE TABLE song_keys (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(20) NOT NULL,
            sort INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        : 'CREATE TABLE song_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(20) NOT NULL,
            sort INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
          )');
}

// SQLite nu limitează lungimea; în MySQL coloanele cresc de la 12 la 20 (se poate repeta fără efect)
if ($mysql) {
    $pdo->exec('ALTER TABLE songs MODIFY song_key VARCHAR(20) NULL');
    $pdo->exec('ALTER TABLE event_items MODIFY song_key VARCHAR(20) NULL');
}
