-- Dispute flow: allow a counter-report, mark match as `disputed` until admin resolves.

ALTER TABLE matches
  MODIFY status ENUM('pending','scheduled','in_progress','reported','disputed','confirmed','void') NOT NULL DEFAULT 'pending',
  ADD COLUMN counter_reported_score1 INT UNSIGNED NULL AFTER reported_at,
  ADD COLUMN counter_reported_score2 INT UNSIGNED NULL AFTER counter_reported_score1,
  ADD COLUMN counter_reported_winner_slot TINYINT UNSIGNED NULL AFTER counter_reported_score2,
  ADD COLUMN counter_reported_by_user_id BIGINT UNSIGNED NULL AFTER counter_reported_winner_slot,
  ADD COLUMN counter_reported_at DATETIME NULL AFTER counter_reported_by_user_id,
  ADD KEY idx_matches_counter_reported_by (counter_reported_by_user_id),
  ADD CONSTRAINT fk_matches_counter_reported_by_user FOREIGN KEY (counter_reported_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

