CREATE TABLE IF NOT EXISTS tournaments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  game VARCHAR(120) NOT NULL,
  format ENUM('single_elim','double_elim','round_robin') NOT NULL DEFAULT 'single_elim',
  status ENUM('draft','published','running','completed') NOT NULL DEFAULT 'draft',
  starts_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_tournaments_slug (slug),
  KEY idx_tournaments_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS players (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  handle VARCHAR(64) NOT NULL,
  discord_user_id VARCHAR(32) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_players_handle (handle),
  KEY idx_players_discord_user_id (discord_user_id)
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

CREATE TABLE IF NOT EXISTS matches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tournament_id BIGINT UNSIGNED NOT NULL,
  round INT NOT NULL,
  bracket ENUM('winners','losers','grand') NOT NULL DEFAULT 'winners',
  best_of INT UNSIGNED NOT NULL DEFAULT 3,
  player1_id BIGINT UNSIGNED NULL,
  player2_id BIGINT UNSIGNED NULL,
  score1 INT UNSIGNED NOT NULL DEFAULT 0,
  score2 INT UNSIGNED NOT NULL DEFAULT 0,
  winner_id BIGINT UNSIGNED NULL,
  status ENUM('pending','scheduled','in_progress','reported','confirmed','void') NOT NULL DEFAULT 'pending',
  scheduled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_matches_tournament_round (tournament_id, round),
  KEY idx_matches_players (player1_id, player2_id),
  CONSTRAINT fk_matches_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_matches_p1 FOREIGN KEY (player1_id) REFERENCES players(id) ON DELETE SET NULL,
  CONSTRAINT fk_matches_p2 FOREIGN KEY (player2_id) REFERENCES players(id) ON DELETE SET NULL,
  CONSTRAINT fk_matches_winner FOREIGN KEY (winner_id) REFERENCES players(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
