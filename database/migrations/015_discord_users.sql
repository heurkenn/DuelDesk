-- Discord account linking (OAuth2 identify)

ALTER TABLE users
  ADD COLUMN discord_user_id VARCHAR(32) NULL AFTER role,
  ADD COLUMN discord_username VARCHAR(64) NULL AFTER discord_user_id,
  ADD COLUMN discord_global_name VARCHAR(64) NULL AFTER discord_username,
  ADD COLUMN discord_avatar VARCHAR(64) NULL AFTER discord_global_name,
  ADD UNIQUE KEY uniq_users_discord_user_id (discord_user_id);

