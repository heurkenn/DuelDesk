-- Pick/Ban: store starting side per picked/decider map.
CREATE TABLE IF NOT EXISTS match_pickban_sides (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id BIGINT UNSIGNED NOT NULL,
  map_key VARCHAR(64) NOT NULL,
  side_for_slot1 ENUM('attack','defense') NOT NULL,
  chosen_by_slot TINYINT UNSIGNED NULL,
  chosen_by_user_id BIGINT UNSIGNED NULL,
  source ENUM('choice','coin') NOT NULL DEFAULT 'choice',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_mps_match_map (match_id, map_key),
  KEY idx_mps_match_id (match_id),
  KEY idx_mps_chosen_by (chosen_by_user_id),
  CONSTRAINT fk_mps_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_mps_chosen_by FOREIGN KEY (chosen_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

