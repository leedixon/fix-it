-- ---------------------------------------------------------------------------
-- Pre-launch waitlist, captured by the holding page at fixlisted.com.
--
--   mysql -u USER -p DBNAME < database/migrations/001_waitlist.sql
--
-- Not tenant-scoped: signups arrive before a visitor has a market, and the
-- county they pick is what places them later.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS waitlist (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role       ENUM('pro','homeowner') NOT NULL,
  name       VARCHAR(120) NOT NULL,
  email      VARCHAR(191) NOT NULL,
  phone      VARCHAR(32)  NOT NULL DEFAULT '',
  trade      VARCHAR(80)  NOT NULL DEFAULT '',
  counties   VARCHAR(255) NOT NULL DEFAULT '',
  town       VARCHAR(80)  NOT NULL DEFAULT '',
  note       TEXT         NULL,
  contacted_at DATETIME   NULL,
  ip         VARBINARY(16) NULL,
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  -- Someone signing up twice updates their entry rather than creating a
  -- duplicate you would have to de-dupe by hand later.
  UNIQUE KEY uq_waitlist_email_role (email, role),
  KEY ix_waitlist_role (role, created_at),
  KEY ix_waitlist_followup (role, contacted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migrations (filename) VALUES ('001_waitlist.sql');
