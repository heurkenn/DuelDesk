-- Per-tournament default best-of (used when generating brackets).

ALTER TABLE tournaments
  ADD COLUMN best_of_default INT UNSIGNED NOT NULL DEFAULT 3 AFTER signup_closes_at;

