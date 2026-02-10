-- Move to username-only auth.

ALTER TABLE users
  CHANGE COLUMN display_name username VARCHAR(80) NOT NULL;

-- Ensure no empty usernames (shouldn't happen).
UPDATE users SET username = CONCAT('user-', id) WHERE username = '';

-- Make potential duplicates unique by appending the user id.
UPDATE users u
JOIN (
  SELECT username
  FROM users
  GROUP BY username
  HAVING COUNT(*) > 1
) d ON d.username = u.username
SET u.username = CONCAT(u.username, '-', u.id);

ALTER TABLE users
  DROP INDEX uniq_users_email,
  DROP COLUMN email;

ALTER TABLE users
  ADD UNIQUE KEY uniq_users_username (username);
