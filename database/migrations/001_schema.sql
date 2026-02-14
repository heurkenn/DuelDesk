-- DuelDesk schema (squashed).
--
-- Note: Older versions of DuelDesk used many incremental migration files (ALTER TABLE, etc.).
-- If you have an existing DB volume from before the squash, wipe it and re-run migrations:
--   bin/dev.sh reset
--   bin/dev.sh up

-- Users (auth + optional Discord link)
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user',
  discord_user_id VARCHAR(32) NULL,
  discord_username VARCHAR(64) NULL,
  discord_global_name VARCHAR(64) NULL,
  discord_avatar VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_users_username (username),
  UNIQUE KEY uniq_users_discord_user_id (discord_user_id),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Games catalog (admin-managed)
CREATE TABLE IF NOT EXISTS games (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  image_width INT UNSIGNED NOT NULL,
  image_height INT UNSIGNED NOT NULL,
  image_mime VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_games_slug (slug),
  UNIQUE KEY uniq_games_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rulesets (admin-managed map veto presets)
CREATE TABLE IF NOT EXISTS rulesets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  game_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  kind ENUM('map_veto') NOT NULL DEFAULT 'map_veto',
  ruleset_json TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_rulesets_game_name (game_id, name),
  KEY idx_rulesets_game_id (game_id),
  KEY idx_rulesets_created_at (created_at),
  CONSTRAINT fk_rulesets_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LAN events ("mega-tournois"): an event can contain multiple tournaments.
CREATE TABLE IF NOT EXISTS lan_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_user_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  participant_type ENUM('solo','team') NOT NULL DEFAULT 'solo',
  status ENUM('draft','published','running','completed') NOT NULL DEFAULT 'draft',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  location VARCHAR(160) NULL,
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_lan_events_slug (slug),
  KEY idx_lan_events_created_at (created_at),
  KEY idx_lan_events_owner_user_id (owner_user_id),
  CONSTRAINT fk_lan_events_owner_user FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tournaments (core)
CREATE TABLE IF NOT EXISTS tournaments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_user_id BIGINT UNSIGNED NULL,
  lan_event_id BIGINT UNSIGNED NULL,
  game_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  game VARCHAR(120) NOT NULL,
  format ENUM('single_elim','double_elim','round_robin') NOT NULL DEFAULT 'single_elim',
  participant_type ENUM('solo','team') NOT NULL DEFAULT 'solo',
  team_size INT UNSIGNED NULL,
  team_match_mode ENUM('standard','lineup_duels','multi_round') NOT NULL DEFAULT 'standard',
  status ENUM('draft','published','running','completed') NOT NULL DEFAULT 'draft',
  starts_at DATETIME NULL,
  max_entrants INT UNSIGNED NULL,
  signup_closes_at DATETIME NULL,
  best_of_default INT UNSIGNED NOT NULL DEFAULT 3,
  best_of_final INT UNSIGNED NULL,
  ruleset_json TEXT NULL,
  pickban_start_mode ENUM('coin_toss','higher_seed') NOT NULL DEFAULT 'coin_toss',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_tournaments_slug (slug),
  KEY idx_tournaments_created_at (created_at),
  KEY idx_tournaments_owner_user_id (owner_user_id),
  KEY idx_tournaments_lan_event_id (lan_event_id),
  KEY idx_tournaments_game_id (game_id),
  KEY idx_tournaments_signup_closes_at (signup_closes_at),
  CONSTRAINT fk_tournaments_owner_user FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_tournaments_lan_event FOREIGN KEY (lan_event_id) REFERENCES lan_events(id) ON DELETE SET NULL,
  CONSTRAINT fk_tournaments_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Players (solo entrants, linked to users)
CREATE TABLE IF NOT EXISTS players (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  handle VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_players_user_id (user_id),
  KEY idx_players_handle (handle),
  CONSTRAINT fk_players_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tournament_players (
  tournament_id BIGINT UNSIGNED NOT NULL,
  player_id BIGINT UNSIGNED NOT NULL,
  seed INT UNSIGNED NULL,
  checked_in TINYINT(1) NOT NULL DEFAULT 0,
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tournament_id, player_id),
  KEY idx_tp_player_id (player_id),
  CONSTRAINT fk_tp_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_tp_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teams (team entrants) + roster
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

-- Matches (SE/DE/RR) + reporting workflow
CREATE TABLE IF NOT EXISTS matches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tournament_id BIGINT UNSIGNED NOT NULL,
  round INT NOT NULL,
  round_pos INT UNSIGNED NOT NULL DEFAULT 1,
  bracket ENUM('winners','losers','grand','round_robin') NOT NULL DEFAULT 'winners',
  best_of INT UNSIGNED NOT NULL DEFAULT 3,
  player1_id BIGINT UNSIGNED NULL,
  player2_id BIGINT UNSIGNED NULL,
  team1_id BIGINT UNSIGNED NULL,
  team2_id BIGINT UNSIGNED NULL,
  score1 INT UNSIGNED NOT NULL DEFAULT 0,
  score2 INT UNSIGNED NOT NULL DEFAULT 0,
  winner_id BIGINT UNSIGNED NULL,
  winner_team_id BIGINT UNSIGNED NULL,
  status ENUM('pending','scheduled','in_progress','reported','disputed','confirmed','void') NOT NULL DEFAULT 'pending',
  scheduled_at DATETIME NULL,
  reported_score1 INT UNSIGNED NULL,
  reported_score2 INT UNSIGNED NULL,
  reported_winner_slot TINYINT UNSIGNED NULL,
  reported_by_user_id BIGINT UNSIGNED NULL,
  reported_at DATETIME NULL,
  counter_reported_score1 INT UNSIGNED NULL,
  counter_reported_score2 INT UNSIGNED NULL,
  counter_reported_winner_slot TINYINT UNSIGNED NULL,
  counter_reported_by_user_id BIGINT UNSIGNED NULL,
  counter_reported_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_matches_tournament_round (tournament_id, round),
  KEY idx_matches_tournament_bracket_round (tournament_id, bracket, round, round_pos),
  UNIQUE KEY uniq_matches_roundpos (tournament_id, bracket, round, round_pos),
  KEY idx_matches_players (player1_id, player2_id),
  KEY idx_matches_team1_id (team1_id),
  KEY idx_matches_team2_id (team2_id),
  KEY idx_matches_winner_team_id (winner_team_id),
  KEY idx_matches_reported_by (reported_by_user_id),
  KEY idx_matches_counter_reported_by (counter_reported_by_user_id),
  CONSTRAINT fk_matches_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_matches_p1 FOREIGN KEY (player1_id) REFERENCES players(id) ON DELETE SET NULL,
  CONSTRAINT fk_matches_p2 FOREIGN KEY (player2_id) REFERENCES players(id) ON DELETE SET NULL,
  CONSTRAINT fk_matches_winner FOREIGN KEY (winner_id) REFERENCES players(id) ON DELETE SET NULL,
  CONSTRAINT fk_matches_team1 FOREIGN KEY (team1_id) REFERENCES teams(id) ON DELETE SET NULL,
  CONSTRAINT fk_matches_team2 FOREIGN KEY (team2_id) REFERENCES teams(id) ON DELETE SET NULL,
  CONSTRAINT fk_matches_winner_team FOREIGN KEY (winner_team_id) REFERENCES teams(id) ON DELETE SET NULL,
  CONSTRAINT fk_matches_reported_by_user FOREIGN KEY (reported_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_matches_counter_reported_by_user FOREIGN KEY (counter_reported_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team match lineups + duels (crew battle / order of play)
CREATE TABLE IF NOT EXISTS match_team_lineups (
  match_id BIGINT UNSIGNED NOT NULL,
  team_slot TINYINT UNSIGNED NOT NULL,
  pos INT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (match_id, team_slot, pos),
  UNIQUE KEY uniq_mtl_match_slot_user (match_id, team_slot, user_id),
  KEY idx_mtl_user_id (user_id),
  CONSTRAINT fk_mtl_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_mtl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_team_duels (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id BIGINT UNSIGNED NOT NULL,
  kind ENUM('regular','captain_tiebreak') NOT NULL DEFAULT 'regular',
  duel_index INT UNSIGNED NOT NULL,
  team1_user_id BIGINT UNSIGNED NOT NULL,
  team2_user_id BIGINT UNSIGNED NOT NULL,
  winner_slot TINYINT UNSIGNED NULL,
  status ENUM('pending','confirmed') NOT NULL DEFAULT 'pending',
  reported_by_user_id BIGINT UNSIGNED NULL,
  reported_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_mtd_match_kind_index (match_id, kind, duel_index),
  KEY idx_mtd_match_id (match_id),
  KEY idx_mtd_t1_user (team1_user_id),
  KEY idx_mtd_t2_user (team2_user_id),
  KEY idx_mtd_reported_by (reported_by_user_id),
  CONSTRAINT fk_mtd_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_mtd_t1_user FOREIGN KEY (team1_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_mtd_t2_user FOREIGN KEY (team2_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_mtd_reported_by FOREIGN KEY (reported_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-match rounds (multi-round points scoring; useful for games like Fall Guys)
CREATE TABLE IF NOT EXISTS match_rounds (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id BIGINT UNSIGNED NOT NULL,
  round_index INT UNSIGNED NOT NULL,
  kind ENUM('regular','tiebreak') NOT NULL DEFAULT 'regular',
  points1 INT NOT NULL DEFAULT 0,
  points2 INT NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_match_rounds_match_idx (match_id, round_index),
  KEY idx_match_rounds_match (match_id),
  KEY idx_match_rounds_created_by (created_by_user_id),
  CONSTRAINT fk_match_rounds_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_rounds_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log (admin + reporting)
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tournament_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(64) NOT NULL,
  entity_type VARCHAR(32) NULL,
  entity_id BIGINT UNSIGNED NULL,
  meta_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_logs_tournament_created (tournament_id, created_at),
  KEY idx_audit_logs_user_created (user_id, created_at),
  CONSTRAINT fk_audit_logs_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting (login/register)
CREATE TABLE IF NOT EXISTS rate_limits (
  k VARCHAR(190) NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  reset_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (k),
  KEY idx_rate_limits_reset_at (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Map pick/ban ("veto") state per match
CREATE TABLE IF NOT EXISTS match_pickbans (
  match_id BIGINT UNSIGNED NOT NULL,
  status ENUM('running','locked') NOT NULL DEFAULT 'running',
  config_json TEXT NOT NULL,
  coin_call_slot TINYINT UNSIGNED NOT NULL,
  coin_call ENUM('heads','tails') NOT NULL,
  coin_result ENUM('heads','tails') NOT NULL,
  first_turn_slot TINYINT UNSIGNED NOT NULL,
  tossed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  locked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (match_id),
  CONSTRAINT fk_match_pickbans_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_pickban_actions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id BIGINT UNSIGNED NOT NULL,
  step_index INT UNSIGNED NOT NULL,
  slot TINYINT UNSIGNED NULL,
  action ENUM('ban','pick','decider') NOT NULL,
  map_key VARCHAR(64) NOT NULL,
  map_name VARCHAR(120) NOT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_mpa_match_step (match_id, step_index),
  UNIQUE KEY uniq_mpa_match_map (match_id, map_key),
  KEY idx_mpa_match_id (match_id),
  KEY idx_mpa_created_by (created_by_user_id),
  CONSTRAINT fk_mpa_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_mpa_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
