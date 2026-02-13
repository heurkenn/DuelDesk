-- Rulesets (admin-managed map veto presets)
CREATE TABLE IF NOT EXISTS rulesets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  game_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  kind ENUM('map_veto') NOT NULL DEFAULT 'map_veto',
  ruleset_json TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_rulesets_game_name (game_id, name),
  KEY idx_rulesets_game_id (game_id),
  KEY idx_rulesets_created_at (created_at),
  CONSTRAINT fk_rulesets_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

