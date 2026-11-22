-- B1 Volunteers · Runda 2 · cântări
CREATE TABLE songs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  author VARCHAR(120) NULL,
  source VARCHAR(20) NULL,
  source_no VARCHAR(20) NULL,
  song_key VARCHAR(12) NULL,
  bpm SMALLINT NULL,
  notes TEXT NULL,
  chords_file VARCHAR(120) NULL,
  chords_name VARCHAR(190) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY songs_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE labels (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  kind VARCHAR(16) NOT NULL DEFAULT 'ocazie',
  sort INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE song_labels (
  song_id INT UNSIGNED NOT NULL,
  label_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (song_id, label_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE song_media (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  song_id INT UNSIGNED NOT NULL,
  kind VARCHAR(10) NOT NULL,
  title VARCHAR(120) NULL,
  youtube_id VARCHAR(20) NULL,
  url VARCHAR(400) NULL,
  file VARCHAR(120) NULL,
  mime VARCHAR(60) NULL,
  size INT UNSIGNED NULL,
  duration INT NULL,
  is_main TINYINT NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY song_media_song (song_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE song_uses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  song_id INT UNSIGNED NOT NULL,
  used_on DATE NOT NULL,
  slot CHAR(1) NULL,
  note VARCHAR(40) NULL,
  event_id INT UNSIGNED NULL,
  KEY song_uses_song (song_id, used_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
