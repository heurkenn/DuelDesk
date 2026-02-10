-- Tournament signup limits / windows.

ALTER TABLE tournaments
  ADD COLUMN max_entrants INT UNSIGNED NULL AFTER starts_at,
  ADD COLUMN signup_closes_at DATETIME NULL AFTER max_entrants,
  ADD KEY idx_tournaments_signup_closes_at (signup_closes_at);

