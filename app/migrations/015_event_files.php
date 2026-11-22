<?php
// B1 Volunteers · fișiere atașate elementelor din ordinea serviciului (predică, anunțuri…)
/** @var PDO $pdo */
$has = function (string $table, string $col) use ($pdo): bool {
    try { $pdo->query("SELECT $col FROM $table LIMIT 1"); return true; } catch (Throwable $e) { return false; }
};
// cheie stabilă pentru fiecare element (ordinea se salvează în întregime, id-urile se schimbă)
if (!$has('event_items', 'uid')) $pdo->exec('ALTER TABLE event_items ADD COLUMN uid VARCHAR(16) NULL');
$up = $pdo->prepare('UPDATE event_items SET uid = ? WHERE id = ?');
foreach ($pdo->query('SELECT id FROM event_items WHERE uid IS NULL')->fetchAll(PDO::FETCH_COLUMN) as $id) $up->execute([bin2hex(random_bytes(8)), (int)$id]);

if (!$has('event_files', 'id')) {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $pdo->exec($mysql
        ? 'CREATE TABLE event_files (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            item_uid VARCHAR(16) NOT NULL,
            file VARCHAR(80) NOT NULL,
            name VARCHAR(190) NOT NULL,
            size INT UNSIGNED NOT NULL DEFAULT 0,
            token VARCHAR(40) NOT NULL,
            uploaded_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            KEY event_files_event (event_id),
            UNIQUE KEY event_files_token (token)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        : 'CREATE TABLE event_files (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id INTEGER NOT NULL,
            item_uid VARCHAR(16) NOT NULL,
            file VARCHAR(80) NOT NULL,
            name VARCHAR(190) NOT NULL,
            size INTEGER NOT NULL DEFAULT 0,
            token VARCHAR(40) NOT NULL UNIQUE,
            uploaded_by INTEGER NULL,
            created_at DATETIME NOT NULL
          )');
}
