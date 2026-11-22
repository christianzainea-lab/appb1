-- B1 Volunteers · ordinea serviciului și calendarul personal
CREATE TABLE event_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  sort INT NOT NULL DEFAULT 0,
  kind VARCHAR(16) NOT NULL DEFAULT 'item',
  title VARCHAR(120) NOT NULL,
  song_id INT UNSIGNED NULL,
  song_key VARCHAR(12) NULL,
  note VARCHAR(190) NULL,
  KEY event_items_event (event_id, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO event_items (event_id, sort, kind, title)
  SELECT e.id, ti.sort, ti.kind, ti.title FROM events e JOIN template_items ti ON ti.template_id = e.template_id WHERE e.status <> 'cancelled'
    AND NOT EXISTS (SELECT 1 FROM event_items x WHERE x.event_id = e.id);

ALTER TABLE users ADD COLUMN calendar_token VARCHAR(64) NULL;
