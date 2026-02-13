-- LAN events ("mega-tournois"): an event can contain multiple tournaments.
CREATE TABLE IF NOT EXISTS lan_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_user_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  status ENUM('draft','published','running','completed') NOT NULL DEFAULT 'draft',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  location VARCHAR(160) NULL,
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_lan_events_slug (slug),
  KEY idx_lan_events_created_at (created_at),
  KEY idx_lan_events_owner_user_id (owner_user_id),
  CONSTRAINT fk_lan_events_owner_user FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tournaments: optional link to a LAN event.
ALTER TABLE tournaments
  ADD COLUMN IF NOT EXISTS lan_event_id BIGINT UNSIGNED NULL AFTER owner_user_id;

-- Add index (idempotent).
SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tournaments'
    AND INDEX_NAME = 'idx_tournaments_lan_event_id'
);
SET @sql := IF(
  @idx_exists = 0,
  'CREATE INDEX idx_tournaments_lan_event_id ON tournaments(lan_event_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add FK (idempotent).
SET @fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tournaments'
    AND CONSTRAINT_NAME = 'fk_tournaments_lan_event'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
  @fk_exists = 0,
  'ALTER TABLE tournaments ADD CONSTRAINT fk_tournaments_lan_event FOREIGN KEY (lan_event_id) REFERENCES lan_events(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
