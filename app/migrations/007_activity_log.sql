-- B1 Volunteers · istoricul modificărilor (cine, ce, când)
CREATE TABLE activity_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL,
  user_id INT UNSIGNED NULL,
  user_name VARCHAR(120) NULL,
  area VARCHAR(16) NOT NULL,
  object_id INT UNSIGNED NULL,
  object_label VARCHAR(190) NULL,
  action VARCHAR(24) NOT NULL,
  summary TEXT NULL,
  KEY activity_area_obj (area, object_id),
  KEY activity_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
