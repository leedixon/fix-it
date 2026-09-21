-- ===========================================================================
-- 006 — a third staff role, and a record of who invited whom
--
-- The ladder is superadmin > market_admin > moderator, and the gap that
-- matters is between the first and the other two: only a superadmin sees or
-- changes money, manages the team, or deletes an account.
--
-- 'moderator' is added after 'market_admin' rather than at the end. The enum's
-- order is the order FIELD() sorts by, and a staff list that puts a moderator
-- between the managers and the tradespeople reads correctly without a CASE
-- expression at every call site.
-- ===========================================================================

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'users'
      AND column_name = 'role' AND column_type LIKE '%moderator%') = 0,
  "ALTER TABLE users MODIFY COLUMN role
     ENUM('superadmin','market_admin','moderator','pro','homeowner')
     NOT NULL DEFAULT 'homeowner'",
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Who invited a staff member, and when they accepted.
--
-- Worth keeping separately from created_at: an invite sent in March and
-- accepted in June is a fact somebody will want back, and "who let this
-- person in" is the first question asked after anything goes wrong.

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'users'
      AND column_name = 'invited_by') = 0,
  'ALTER TABLE users ADD COLUMN invited_by BIGINT UNSIGNED NULL AFTER is_demo',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'users'
      AND column_name = 'invited_at') = 0,
  'ALTER TABLE users ADD COLUMN invited_at DATETIME NULL AFTER invited_by',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'users'
      AND column_name = 'accepted_at') = 0,
  'ALTER TABLE users ADD COLUMN accepted_at DATETIME NULL AFTER invited_at',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ON DELETE SET NULL, not CASCADE: removing the person who did the inviting
-- must never remove the people they invited.
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.table_constraints
    WHERE table_schema = DATABASE() AND table_name = 'users'
      AND constraint_name = 'fk_users_invited_by') = 0,
  'ALTER TABLE users ADD CONSTRAINT fk_users_invited_by
     FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE SET NULL',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

INSERT IGNORE INTO migrations (filename) VALUES ('006_moderator_role.sql');
