-- ===========================================================================
-- 003 — mark seed rows as demonstration data
--
-- The seed ships ten invented tradespeople, their jobs and their reviews, so
-- the site can be looked at before real people have signed up. Those rows are
-- useful and they are also fabricated business listings: a visitor has no way
-- to tell Ray's Handyman from a real business unless the page says so.
--
-- This flag is what lets the site say so. Every template that renders a
-- flagged row labels it, and one config switch hides them all at launch.
-- Without the flag the only way to separate seed rows from real ones later is
-- to remember which ids were which, which nobody will.
--
-- Idempotent: MySQL has no ADD COLUMN IF NOT EXISTS, so each change is guarded
-- by an information_schema check and run through a prepared statement. Safe to
-- apply twice, and safe on a database where it half-applied.
-- ===========================================================================

-- --- the flag itself --------------------------------------------------------

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'pro_profiles' AND column_name = 'is_demo') = 0,
  'ALTER TABLE pro_profiles ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'jobs' AND column_name = 'is_demo') = 0,
  'ALTER TABLE jobs ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'reviews' AND column_name = 'is_demo') = 0,
  'ALTER TABLE reviews ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'is_demo') = 0,
  'ALTER TABLE users ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- --- indexes ----------------------------------------------------------------
-- The directory filters on this on every request once sample data is hidden,
-- so it leads with market scope the same way the other listing indexes do.

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'pro_profiles' AND index_name = 'ix_pro_demo') = 0,
  'CREATE INDEX ix_pro_demo ON pro_profiles (is_demo, status)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'jobs' AND index_name = 'ix_jobs_demo') = 0,
  'CREATE INDEX ix_jobs_demo ON jobs (market_id, is_demo, status)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- --- flag the rows that already exist ---------------------------------------
--
-- Unqualified, and that is the correct scope: this migration runs before the
-- site has ever been public, so every pro profile, job and review in the
-- database came from seed.sql. There is no real listing for it to mislabel.
--
-- The heuristic it replaced — matching '%@example.com' — flagged three of the
-- ten seeded tradespeople and missed the seven given realistic business
-- addresses, which is worse than not flagging at all: it would have put a
-- "Sample" badge on some fabricated listings and left the rest looking
-- genuine.
--
-- Rows created after this point set the flag themselves: seed.sql sets it,
-- and a real signup leaves it at the column default of 0.

UPDATE users        SET is_demo = 1;
UPDATE pro_profiles SET is_demo = 1;
UPDATE jobs         SET is_demo = 1;
UPDATE reviews      SET is_demo = 1;

INSERT IGNORE INTO migrations (filename) VALUES ('003_demo_flag.sql');
