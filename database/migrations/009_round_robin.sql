-- Enable storing round-robin schedules in `matches`.
-- This is safe for existing data because we only add a new ENUM value.

ALTER TABLE matches
  MODIFY COLUMN bracket ENUM('winners','losers','grand','round_robin') NOT NULL DEFAULT 'winners';

