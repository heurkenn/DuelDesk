ALTER TABLE tournaments
  ADD COLUMN participant_type ENUM('solo','team') NOT NULL DEFAULT 'solo' AFTER format,
  ADD COLUMN team_size INT UNSIGNED NULL AFTER participant_type;

CREATE TABLE IF NOT EXISTS teams (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tournament_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  join_code CHAR(10) NOT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_teams_join_code (join_code),
  UNIQUE KEY uniq_teams_tournament_slug (tournament_id, slug),
  KEY idx_teams_tournament_id (tournament_id),
  KEY idx_teams_created_by_user_id (created_by_user_id),
  CONSTRAINT fk_teams_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_teams_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_members (
  team_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('captain','member') NOT NULL DEFAULT 'member',
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (team_id, user_id),
  KEY idx_team_members_user_id (user_id),
  CONSTRAINT fk_team_members_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_team_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tournament_teams (
  tournament_id BIGINT UNSIGNED NOT NULL,
  team_id BIGINT UNSIGNED NOT NULL,
  seed INT UNSIGNED NULL,
  checked_in TINYINT(1) NOT NULL DEFAULT 0,
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tournament_id, team_id),
  KEY idx_tt_team_id (team_id),
  CONSTRAINT fk_tt_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_tt_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE matches
  ADD COLUMN round_pos INT UNSIGNED NOT NULL DEFAULT 1 AFTER round,
  ADD COLUMN team1_id BIGINT UNSIGNED NULL AFTER player2_id,
  ADD COLUMN team2_id BIGINT UNSIGNED NULL AFTER team1_id,
  ADD COLUMN winner_team_id BIGINT UNSIGNED NULL AFTER winner_id;

ALTER TABLE matches
  ADD KEY idx_matches_tournament_bracket_round (tournament_id, bracket, round, round_pos),
  ADD UNIQUE KEY uniq_matches_roundpos (tournament_id, bracket, round, round_pos),
  ADD KEY idx_matches_team1_id (team1_id),
  ADD KEY idx_matches_team2_id (team2_id),
  ADD KEY idx_matches_winner_team_id (winner_team_id),
  ADD CONSTRAINT fk_matches_team1 FOREIGN KEY (team1_id) REFERENCES teams(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_matches_team2 FOREIGN KEY (team2_id) REFERENCES teams(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_matches_winner_team FOREIGN KEY (winner_team_id) REFERENCES teams(id) ON DELETE SET NULL;

