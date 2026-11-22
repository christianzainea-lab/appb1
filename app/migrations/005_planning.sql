-- B1 Volunteers · Runda 3 · planificare
CREATE TABLE events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  template_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  event_date DATE NOT NULL,
  start_time CHAR(5) NOT NULL,
  rehearsal_time CHAR(5) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'draft',
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY events_date (event_date),
  KEY events_tpl (template_id, event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_slots (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  position_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  response VARCHAR(12) NULL,
  response_note VARCHAR(255) NULL,
  responded_at DATETIME NULL,
  assigned_by INT UNSIGNED NULL,
  assigned_at DATETIME NULL,
  notified_at DATETIME NULL,
  sort INT NOT NULL DEFAULT 0,
  KEY event_slots_event (event_id),
  KEY event_slots_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blockouts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  reason VARCHAR(160) NULL,
  created_at DATETIME NOT NULL,
  KEY blockouts_user (user_id, start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
