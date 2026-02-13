-- Optional finals best-of override (grand final / tournament final).

ALTER TABLE tournaments
  ADD COLUMN best_of_final INT UNSIGNED NULL AFTER best_of_default;

