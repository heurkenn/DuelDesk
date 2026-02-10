ALTER TABLE players
  ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id,
  ADD UNIQUE KEY uniq_players_user_id (user_id),
  ADD CONSTRAINT fk_players_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL;
