-- Player/captain match reporting (pending admin confirmation).

ALTER TABLE matches
  ADD COLUMN reported_score1 INT UNSIGNED NULL AFTER scheduled_at,
  ADD COLUMN reported_score2 INT UNSIGNED NULL AFTER reported_score1,
  ADD COLUMN reported_winner_slot TINYINT UNSIGNED NULL AFTER reported_score2,
  ADD COLUMN reported_by_user_id BIGINT UNSIGNED NULL AFTER reported_winner_slot,
  ADD COLUMN reported_at DATETIME NULL AFTER reported_by_user_id,
  ADD KEY idx_matches_reported_by (reported_by_user_id),
  ADD CONSTRAINT fk_matches_reported_by_user FOREIGN KEY (reported_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

