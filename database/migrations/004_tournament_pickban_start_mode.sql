-- Tournament setting: how to decide who is "Team A" (starter) in pick/ban.
-- MariaDB supports ADD COLUMN IF NOT EXISTS (idempotent).
ALTER TABLE tournaments
  ADD COLUMN IF NOT EXISTS pickban_start_mode ENUM('coin_toss','higher_seed') NOT NULL DEFAULT 'coin_toss'
  AFTER ruleset_json;

