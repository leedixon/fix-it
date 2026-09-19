-- ---------------------------------------------------------------------------
-- Fix Listed — schema
--
-- Target: MySQL 8.0 / MariaDB 10.4+ on A2 Hosting shared cPanel.
-- Engine InnoDB, utf8mb4 throughout. Indexed strings are capped at VARCHAR(191)
-- so the schema still imports on older servers with the 767-byte index limit.
--
-- MULTITENANCY
--   Every tenant-owned row carries `market_id`. Composite indexes lead with
--   market_id so a market admin's queries stay on an index. The application
--   adds `market_id = ?` to every query through a single scoped-query helper;
--   nothing reads these tables directly. Superadmins are the only role that
--   may drop the scope, and only through an explicitly named method.
--
--   Global (non-tenant) tables: users, trades, webhook_events, password_resets.
--   A user's identity is global — one email, one login — while their role and
--   their data are scoped to a market.
--
-- MONEY
--   All money is INT cents. No floats, no DECIMAL rounding surprises.
--
-- Import:  mysql -u USER -p DBNAME < database/schema.sql
--    or:   phpMyAdmin → Import → schema.sql
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ===========================================================================
-- TENANTS
-- ===========================================================================

CREATE TABLE markets (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug                  VARCHAR(64)  NOT NULL,           -- url segment: /austin
  name                  VARCHAR(120) NOT NULL,           -- "Austin, TX"
  code                  VARCHAR(8)   NOT NULL,           -- "ATX", shown in UI
  city                  VARCHAR(120) NOT NULL,
  state                 CHAR(2)      NOT NULL,
  timezone              VARCHAR(64)  NOT NULL DEFAULT 'America/Chicago',
  status                ENUM('staged','live','paused') NOT NULL DEFAULT 'staged',

  -- Pricing is per market. A new market can launch with listing_fee_cents = 0
  -- to fill the jobs board, then switch the fee on without a deploy.
  listing_fee_cents     INT UNSIGNED NOT NULL DEFAULT 1000,
  boost_price_cents     INT UNSIGNED NOT NULL DEFAULT 4900,
  spotlight_price_cents INT UNSIGNED NOT NULL DEFAULT 14900,

  -- Placement inventory caps. Scarcity is the product; if every pro can buy
  -- the top slot, nobody's top slot is worth anything.
  boost_slots           SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  spotlight_slots       SMALLINT UNSIGNED NOT NULL DEFAULT 3,

  adsense_enabled       TINYINT(1)   NOT NULL DEFAULT 1,
  job_listing_days      SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  refund_window_hours   SMALLINT UNSIGNED NOT NULL DEFAULT 72,  -- no-quote auto refund

  launched_at           DATETIME     NULL,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_markets_slug (slug),
  UNIQUE KEY uq_markets_code (code),
  KEY ix_markets_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- IDENTITY  (global — not tenant scoped)
-- ===========================================================================

CREATE TABLE users (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  market_id          INT UNSIGNED NULL,                  -- NULL only for superadmin
  role               ENUM('superadmin','market_admin','pro','homeowner') NOT NULL DEFAULT 'homeowner',
  email              VARCHAR(191) NOT NULL,
  password_hash      VARCHAR(255) NULL,                  -- NULL until they set one
  first_name         VARCHAR(80)  NOT NULL DEFAULT '',
  last_name          VARCHAR(80)  NOT NULL DEFAULT '',
  phone              VARCHAR(32)  NOT NULL DEFAULT '',
  status             ENUM('active','suspended','deleted') NOT NULL DEFAULT 'active',
  email_verified_at  DATETIME     NULL,
  phone_verified_at  DATETIME     NULL,
  sms_opt_in         TINYINT(1)   NOT NULL DEFAULT 0,
  last_login_at      DATETIME     NULL,
  last_login_ip      VARBINARY(16) NULL,
  failed_logins      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until       DATETIME     NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY ix_users_market_role (market_id, role, status),
  CONSTRAINT fk_users_market FOREIGN KEY (market_id) REFERENCES markets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL,                     -- sha256 of the emailed token
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_resets_token (token_hash),
  KEY ix_resets_user (user_id, expires_at),
  CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- TRADES  (global taxonomy, shared by every market)
-- ===========================================================================

CREATE TABLE trades (
  id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug       VARCHAR(64)  NOT NULL,
  name       VARCHAR(80)  NOT NULL,
  icon       VARCHAR(40)  NOT NULL DEFAULT '',
  sort_order SMALLINT     NOT NULL DEFAULT 0,
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_trades_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- PROS
-- ===========================================================================

CREATE TABLE pro_profiles (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  market_id            INT UNSIGNED NOT NULL,            -- home market
  user_id              BIGINT UNSIGNED NOT NULL,
  slug                 VARCHAR(191) NOT NULL,            -- /austin/pro/ray-okafor
  business_name        VARCHAR(160) NOT NULL DEFAULT '',
  headline             VARCHAR(160) NOT NULL DEFAULT '', -- "Master Plumber"
  bio                  TEXT         NULL,
  hourly_rate_cents    INT UNSIGNED NULL,
  min_hours            DECIMAL(3,1) NOT NULL DEFAULT 2.0,
  years_experience     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  service_radius_miles SMALLINT UNSIGNED NOT NULL DEFAULT 25,
  base_zip             VARCHAR(12)  NOT NULL DEFAULT '',

  -- Credentials. The *_verified_at timestamps are set by an admin, never by
  -- the pro — the badge means a human checked it against state records.
  license_number       VARCHAR(64)  NOT NULL DEFAULT '',
  license_state        CHAR(2)      NOT NULL DEFAULT '',
  license_verified_at  DATETIME     NULL,
  license_verified_by  BIGINT UNSIGNED NULL,
  insurance_carrier    VARCHAR(120) NOT NULL DEFAULT '',
  insurance_expires_on DATE         NULL,
  insurance_verified_at DATETIME    NULL,
  background_checked_at DATETIME    NULL,

  -- Denormalised counters. Rebuilt nightly by cron; cheap reads on a shared host.
  rating_avg           DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  rating_count         INT UNSIGNED NOT NULL DEFAULT 0,
  jobs_completed       INT UNSIGNED NOT NULL DEFAULT 0,
  response_minutes     INT UNSIGNED NULL,                -- median first reply

  status               ENUM('draft','pending_review','active','suspended') NOT NULL DEFAULT 'draft',
  hero_photo_id        BIGINT UNSIGNED NULL,             -- photo the ad unit leads with
  published_at         DATETIME     NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pro_user (user_id),
  UNIQUE KEY uq_pro_market_slug (market_id, slug),
  KEY ix_pro_market_status (market_id, status, rating_avg),
  CONSTRAINT fk_pro_market FOREIGN KEY (market_id) REFERENCES markets (id) ON DELETE CASCADE,
  CONSTRAINT fk_pro_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pro_trades (
  pro_id   BIGINT UNSIGNED NOT NULL,
  trade_id SMALLINT UNSIGNED NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (pro_id, trade_id),
  KEY ix_pro_trades_trade (trade_id),
  CONSTRAINT fk_pt_pro   FOREIGN KEY (pro_id)   REFERENCES pro_profiles (id) ON DELETE CASCADE,
  CONSTRAINT fk_pt_trade FOREIGN KEY (trade_id) REFERENCES trades (id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pro_skills (
  id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pro_id BIGINT UNSIGNED NOT NULL,
  label  VARCHAR(60) NOT NULL,                           -- "Slab leaks", "EV chargers"
  sort_order SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_skills_pro (pro_id, sort_order),
  CONSTRAINT fk_skills_pro FOREIGN KEY (pro_id) REFERENCES pro_profiles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A pro can serve more than their home market once they grow. The directory
-- reads this table, not pro_profiles.market_id, when listing a market.
CREATE TABLE pro_service_areas (
  pro_id    BIGINT UNSIGNED NOT NULL,
  market_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (pro_id, market_id),
  KEY ix_psa_market (market_id),
  CONSTRAINT fk_psa_pro    FOREIGN KEY (pro_id)    REFERENCES pro_profiles (id) ON DELETE CASCADE,
  CONSTRAINT fk_psa_market FOREIGN KEY (market_id) REFERENCES markets (id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pro_photos (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pro_id     BIGINT UNSIGNED NOT NULL,
  market_id  INT UNSIGNED NOT NULL,
  path       VARCHAR(255) NOT NULL,                      -- storage/uploads/pro/<id>/<file>
  caption    VARCHAR(160) NOT NULL DEFAULT '',
  width      SMALLINT UNSIGNED NULL,
  height     SMALLINT UNSIGNED NULL,
  bytes      INT UNSIGNED NULL,
  status     ENUM('pending_review','approved','rejected') NOT NULL DEFAULT 'pending_review',
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_photos_pro (pro_id, status, sort_order),
  KEY ix_photos_market_status (market_id, status),
  CONSTRAINT fk_photos_pro    FOREIGN KEY (pro_id)    REFERENCES pro_profiles (id) ON DELETE CASCADE,
  CONSTRAINT fk_photos_market FOREIGN KEY (market_id) REFERENCES markets (id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- JOBS
-- ===========================================================================

CREATE TABLE jobs (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  market_id         INT UNSIGNED NOT NULL,
  user_id           BIGINT UNSIGNED NOT NULL,            -- homeowner
  trade_id          SMALLINT UNSIGNED NOT NULL,
  reference         CHAR(10)     NOT NULL,               -- "ATX-4K2P9M", shown to users
  title             VARCHAR(160) NOT NULL,
  description       TEXT         NOT NULL,
  zip               VARCHAR(12)  NOT NULL,
  urgency           ENUM('asap','this_week','this_month','flexible') NOT NULL DEFAULT 'this_week',
  budget_min_cents  INT UNSIGNED NULL,
  budget_max_cents  INT UNSIGNED NULL,

  -- A job is invisible to pros until the Stripe webhook flips it to 'active'.
  -- Nothing in the public directory ever reads a row in pending_payment.
  status            ENUM('draft','pending_payment','active','expired','closed','removed')
                    NOT NULL DEFAULT 'draft',
  quote_count       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  view_count        INT UNSIGNED NOT NULL DEFAULT 0,
  hired_pro_id      BIGINT UNSIGNED NULL,
  published_at      DATETIME     NULL,
  expires_at        DATETIME     NULL,
  closed_at         DATETIME     NULL,
  removed_reason    VARCHAR(191) NOT NULL DEFAULT '',
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jobs_reference (reference),
  KEY ix_jobs_board (market_id, status, published_at),   -- the jobs board query
  KEY ix_jobs_trade (market_id, trade_id, status),
  KEY ix_jobs_owner (user_id, status),
  KEY ix_jobs_expiry (status, expires_at),               -- cron: expire + refund sweep
  CONSTRAINT fk_jobs_market FOREIGN KEY (market_id) REFERENCES markets (id)      ON DELETE CASCADE,
  CONSTRAINT fk_jobs_user   FOREIGN KEY (user_id)   REFERENCES users (id)        ON DELETE CASCADE,
  CONSTRAINT fk_jobs_trade  FOREIGN KEY (trade_id)  REFERENCES trades (id),
  CONSTRAINT fk_jobs_hired  FOREIGN KEY (hired_pro_id) REFERENCES pro_profiles (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_photos (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id     BIGINT UNSIGNED NOT NULL,
  market_id  INT UNSIGNED NOT NULL,
  path       VARCHAR(255) NOT NULL,
  bytes      INT UNSIGNED NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_jobphotos_job (job_id, sort_order),
  CONSTRAINT fk_jobphotos_job    FOREIGN KEY (job_id)    REFERENCES jobs (id)    ON DELETE CASCADE,
  CONSTRAINT fk_jobphotos_market FOREIGN KEY (market_id) REFERENCES markets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotes (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  market_id     INT UNSIGNED NOT NULL,
  job_id        BIGINT UNSIGNED NOT NULL,
  pro_id        BIGINT UNSIGNED NOT NULL,
  amount_cents  INT UNSIGNED NULL,                       -- NULL = "needs to see it first"
  amount_type   ENUM('fixed','range','hourly','visit_required') NOT NULL DEFAULT 'fixed',
  amount_max_cents INT UNSIGNED NULL,
  message       TEXT         NOT NULL,
  can_start_on  DATE         NULL,
  status        ENUM('sent','viewed','accepted','declined','withdrawn') NOT NULL DEFAULT 'sent',
  viewed_at     DATETIME     NULL,
  responded_at  DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_quote_job_pro (job_id, pro_id),          -- one quote per pro per job
  KEY ix_quotes_pro (pro_id, status, created_at),
  KEY ix_quotes_market (market_id, created_at),
  CONSTRAINT fk_quotes_market FOREIGN KEY (market_id) REFERENCES markets (id)      ON DELETE CASCADE,
  CONSTRAINT fk_quotes_job    FOREIGN KEY (job_id)    REFERENCES jobs (id)         ON DELETE CASCADE,
  CONSTRAINT fk_quotes_pro    FOREIGN KEY (pro_id)    REFERENCES pro_profiles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  market_id    INT UNSIGNED NOT NULL,
  job_id       BIGINT UNSIGNED NULL,                     -- NULL = direct enquiry from a profile
  pro_id       BIGINT UNSIGNED NOT NULL,
  from_user_id BIGINT UNSIGNED NOT NULL,
  to_user_id   BIGINT UNSIGNED NOT NULL,
  body         TEXT     NOT NULL,
  read_at      DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_msg_thread (job_id, pro_id, created_at),
  KEY ix_msg_inbox (to_user_id, read_at, created_at),
  KEY ix_msg_market (market_id, created_at),
  CONSTRAINT fk_msg_market FOREIGN KEY (market_id)    REFERENCES markets (id)      ON DELETE CASCADE,
  CONSTRAINT fk_msg_job    FOREIGN KEY (job_id)       REFERENCES jobs (id)         ON DELETE CASCADE,
  CONSTRAINT fk_msg_pro    FOREIGN KEY (pro_id)       REFERENCES pro_profiles (id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_from   FOREIGN KEY (from_user_id) REFERENCES users (id)        ON DELETE CASCADE,
  CONSTRAINT fk_msg_to     FOREIGN KEY (to_user_id)   REFERENCES users (id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  market_id      INT UNSIGNED NOT NULL,
  pro_id         BIGINT UNSIGNED NOT NULL,
  job_id         BIGINT UNSIGNED NOT NULL,               -- required: no job, no review
  author_user_id BIGINT UNSIGNED NOT NULL,
  rating         TINYINT UNSIGNED NOT NULL,              -- 1..5
  body           TEXT     NULL,
  job_value_cents INT UNSIGNED NULL,                     -- "Slab leak repair · $640"
  status         ENUM('published','pending_review','removed') NOT NULL DEFAULT 'published',
  pro_reply      TEXT     NULL,
  pro_replied_at DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_review_job_author (job_id, author_user_id),
  KEY ix_reviews_pro (pro_id, status, created_at),
  KEY ix_reviews_market (market_id, status),
  CONSTRAINT fk_rev_market FOREIGN KEY (market_id)      REFERENCES markets (id)      ON DELETE CASCADE,
  CONSTRAINT fk_rev_pro    FOREIGN KEY (pro_id)         REFERENCES pro_profiles (id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_job    FOREIGN KEY (job_id)         REFERENCES jobs (id)         ON DELETE CASCADE,
  CONSTRAINT fk_rev_author FOREIGN KEY (author_user_id) REFERENCES users (id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- MONEY
-- ===========================================================================

-- One ledger row per Stripe charge, whatever it paid for. This is the table
-- the superadmin revenue screen reads; nothing sums Stripe's API at runtime.
CREATE TABLE payments (
  id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  market_id                INT UNSIGNED NOT NULL,
  user_id                  BIGINT UNSIGNED NULL,
  kind                     ENUM('job_listing','subscription','one_off') NOT NULL,
  job_id                   BIGINT UNSIGNED NULL,
  subscription_id          BIGINT UNSIGNED NULL,
  amount_cents             INT UNSIGNED NOT NULL,
  currency                 CHAR(3)      NOT NULL DEFAULT 'USD',
  stripe_checkout_session  VARCHAR(191) NULL,
  stripe_payment_intent    VARCHAR(191) NULL,
  stripe_charge_id         VARCHAR(191) NULL,
  status                   ENUM('pending','succeeded','failed','refunded','partially_refunded')
                           NOT NULL DEFAULT 'pending',
  refunded_cents           INT UNSIGNED NOT NULL DEFAULT 0,
  refunded_at              DATETIME     NULL,
  refund_reason            VARCHAR(191) NOT NULL DEFAULT '',
  failure_message          VARCHAR(255) NOT NULL DEFAULT '',
  paid_at                  DATETIME     NULL,
  created_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pay_intent (stripe_payment_intent),
  UNIQUE KEY uq_pay_session (stripe_checkout_session),
  KEY ix_pay_market_kind (market_id, kind, paid_at),     -- revenue by line, by market
  KEY ix_pay_user (user_id, created_at),
  KEY ix_pay_job (job_id),
  CONSTRAINT fk_pay_market FOREIGN KEY (market_id) REFERENCES markets (id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE SET NULL,
  CONSTRAINT fk_pay_job    FOREIGN KEY (job_id)    REFERENCES jobs (id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  market_id              INT UNSIGNED NOT NULL,
  pro_id                 BIGINT UNSIGNED NOT NULL,
  plan                   ENUM('boost','spotlight') NOT NULL,
  price_cents            INT UNSIGNED NOT NULL,          -- captured at signup; a later
                                                         -- market price change won't
                                                         -- silently reprice existing pros
  stripe_customer_id     VARCHAR(191) NULL,
  stripe_subscription_id VARCHAR(191) NULL,
  status                 ENUM('incomplete','trialing','active','past_due','canceled','unpaid')
                         NOT NULL DEFAULT 'incomplete',
  current_period_start   DATETIME NULL,
  current_period_end     DATETIME NULL,
  cancel_at_period_end   TINYINT(1) NOT NULL DEFAULT 0,
  canceled_at            DATETIME NULL,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sub_stripe (stripe_subscription_id),
  KEY ix_sub_market_plan (market_id, plan, status),      -- slot capacity check
  KEY ix_sub_pro (pro_id, status),
  CONSTRAINT fk_sub_market FOREIGN KEY (market_id) REFERENCES markets (id)      ON DELETE CASCADE,
  CONSTRAINT fk_sub_pro    FOREIGN KEY (pro_id)    REFERENCES pro_profiles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stripe delivers webhooks more than once. Insert here first; a duplicate
-- event_id hits the unique key and the handler exits without re-applying.
CREATE TABLE webhook_events (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  stripe_event_id VARCHAR(191) NOT NULL,
  type            VARCHAR(80)  NOT NULL,
  payload         MEDIUMTEXT   NOT NULL,
  status          ENUM('received','processed','failed','ignored') NOT NULL DEFAULT 'received',
  error           VARCHAR(255) NOT NULL DEFAULT '',
  attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  received_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at    DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wh_event (stripe_event_id),
  KEY ix_wh_status (status, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- ADVERTISING
--
-- Ads are assembled from the pro's profile. A creative holds only the three
-- fields a pro may override — headline, offer line, hero photo — plus a
-- moderation state. There is no free-form ad builder and no uploaded banner:
-- the trust signals (rating, review count, verified badges) are rendered by
-- the template from live profile data and cannot be authored by the advertiser.
--
-- NULL override = fall back to the profile value, so every paying advertiser
-- has a working ad the moment they subscribe, without touching this table.
-- ===========================================================================

CREATE TABLE ad_creatives (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  market_id       INT UNSIGNED NOT NULL,
  pro_id          BIGINT UNSIGNED NOT NULL,
  variant         CHAR(1)      NOT NULL DEFAULT 'A',     -- A/B: two live variants max
  headline        VARCHAR(80)  NULL,                     -- NULL -> pro_profiles.headline
  offer_line      VARCHAR(120) NULL,                     -- NULL -> first line of bio
  cta_label       VARCHAR(32)  NULL,                     -- NULL -> "View profile"
  hero_photo_id   BIGINT UNSIGNED NULL,                  -- NULL -> pro_profiles.hero_photo_id
  status          ENUM('draft','pending_review','approved','rejected','paused')
                  NOT NULL DEFAULT 'pending_review',
  rejected_reason VARCHAR(191) NOT NULL DEFAULT '',
  reviewed_by     BIGINT UNSIGNED NULL,
  reviewed_at     DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creative_pro_variant (pro_id, market_id, variant),
  KEY ix_creative_review (market_id, status, created_at),
  CONSTRAINT fk_cr_market FOREIGN KEY (market_id)     REFERENCES markets (id)      ON DELETE CASCADE,
  CONSTRAINT fk_cr_pro    FOREIGN KEY (pro_id)        REFERENCES pro_profiles (id) ON DELETE CASCADE,
  CONSTRAINT fk_cr_photo  FOREIGN KEY (hero_photo_id) REFERENCES pro_photos (id)   ON DELETE SET NULL,
  CONSTRAINT fk_cr_review FOREIGN KEY (reviewed_by)   REFERENCES users (id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which slot a subscription buys. Inventory is capped per market by
-- markets.boost_slots / spotlight_slots; the app checks capacity here before
-- letting a pro start a subscription.
CREATE TABLE ad_placements (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  market_id       INT UNSIGNED NOT NULL,
  pro_id          BIGINT UNSIGNED NOT NULL,
  subscription_id BIGINT UNSIGNED NULL,
  slot            ENUM('directory_top','home_featured','jobs_native','category_top','profile_related')
                  NOT NULL,
  trade_id        SMALLINT UNSIGNED NULL,                -- set for category_top
  position        SMALLINT UNSIGNED NOT NULL DEFAULT 1,  -- 1 = first
  status          ENUM('active','paused','expired') NOT NULL DEFAULT 'active',
  starts_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ends_at         DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_place_serve (market_id, slot, status, position),  -- the ad-serving query
  KEY ix_place_category (market_id, trade_id, slot, status),
  KEY ix_place_pro (pro_id, status),
  CONSTRAINT fk_pl_market FOREIGN KEY (market_id)       REFERENCES markets (id)       ON DELETE CASCADE,
  CONSTRAINT fk_pl_pro    FOREIGN KEY (pro_id)          REFERENCES pro_profiles (id)  ON DELETE CASCADE,
  CONSTRAINT fk_pl_sub    FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE SET NULL,
  CONSTRAINT fk_pl_trade  FOREIGN KEY (trade_id)        REFERENCES trades (id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Raw event log. Written on every impression and click, pruned to 45 days by
-- cron. ip/session are stored hashed so the log is not a pile of PII.
CREATE TABLE ad_events (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  market_id    INT UNSIGNED NOT NULL,
  placement_id BIGINT UNSIGNED NULL,
  creative_id  BIGINT UNSIGNED NULL,
  pro_id       BIGINT UNSIGNED NOT NULL,
  event        ENUM('impression','click') NOT NULL,
  page         VARCHAR(80)  NOT NULL DEFAULT '',
  session_hash CHAR(32)     NOT NULL DEFAULT '',         -- dedupes impressions per view
  ip_hash      CHAR(32)     NOT NULL DEFAULT '',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ev_rollup (market_id, created_at),              -- nightly aggregation
  KEY ix_ev_dedupe (placement_id, session_hash, event, created_at),
  CONSTRAINT fk_ev_market FOREIGN KEY (market_id)    REFERENCES markets (id)       ON DELETE CASCADE,
  CONSTRAINT fk_ev_place  FOREIGN KEY (placement_id) REFERENCES ad_placements (id) ON DELETE SET NULL,
  CONSTRAINT fk_ev_cr     FOREIGN KEY (creative_id)  REFERENCES ad_creatives (id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What the pro dashboard and the A/B comparison actually read.
CREATE TABLE ad_stats_daily (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  market_id    INT UNSIGNED NOT NULL,
  pro_id       BIGINT UNSIGNED NOT NULL,
  placement_id BIGINT UNSIGNED NULL,
  creative_id  BIGINT UNSIGNED NULL,
  stat_date    DATE NOT NULL,
  impressions  INT UNSIGNED NOT NULL DEFAULT 0,
  clicks       INT UNSIGNED NOT NULL DEFAULT 0,
  quotes_sent  INT UNSIGNED NOT NULL DEFAULT 0,          -- attribution: click -> quote
  jobs_won     INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stats_day (stat_date, placement_id, creative_id),
  KEY ix_stats_pro (pro_id, stat_date),
  KEY ix_stats_market (market_id, stat_date),
  CONSTRAINT fk_st_market FOREIGN KEY (market_id)    REFERENCES markets (id)       ON DELETE CASCADE,
  CONSTRAINT fk_st_pro    FOREIGN KEY (pro_id)       REFERENCES pro_profiles (id)  ON DELETE CASCADE,
  CONSTRAINT fk_st_place  FOREIGN KEY (placement_id) REFERENCES ad_placements (id) ON DELETE SET NULL,
  CONSTRAINT fk_st_cr     FOREIGN KEY (creative_id)  REFERENCES ad_creatives (id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- OPERATIONS
-- ===========================================================================

CREATE TABLE moderation_items (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  market_id      INT UNSIGNED NOT NULL,
  subject_type   ENUM('job','pro_profile','review','photo','message','ad_creative') NOT NULL,
  subject_id     BIGINT UNSIGNED NOT NULL,
  source         ENUM('auto','user_report','admin') NOT NULL DEFAULT 'auto',
  reason         VARCHAR(191) NOT NULL,
  reported_by    BIGINT UNSIGNED NULL,
  status         ENUM('open','approved','removed','dismissed') NOT NULL DEFAULT 'open',
  resolution_note VARCHAR(255) NOT NULL DEFAULT '',
  resolved_by    BIGINT UNSIGNED NULL,
  resolved_at    DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_mod_queue (market_id, status, created_at),
  KEY ix_mod_subject (subject_type, subject_id),
  CONSTRAINT fk_mod_market   FOREIGN KEY (market_id)   REFERENCES markets (id) ON DELETE CASCADE,
  CONSTRAINT fk_mod_reporter FOREIGN KEY (reported_by) REFERENCES users (id)   ON DELETE SET NULL,
  CONSTRAINT fk_mod_resolver FOREIGN KEY (resolved_by) REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anything an admin does to someone else's data lands here. Market admins are
-- staff you have not met yet; this is how you find out what they changed.
CREATE TABLE audit_log (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  market_id      INT UNSIGNED NULL,
  actor_user_id  BIGINT UNSIGNED NULL,
  action         VARCHAR(80)  NOT NULL,                  -- 'job.removed', 'refund.issued'
  subject_type   VARCHAR(40)  NOT NULL DEFAULT '',
  subject_id     BIGINT UNSIGNED NULL,
  meta           TEXT         NULL,                      -- JSON blob
  ip             VARBINARY(16) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_market (market_id, created_at),
  KEY ix_audit_actor (actor_user_id, created_at),
  KEY ix_audit_subject (subject_type, subject_id),
  CONSTRAINT fk_audit_market FOREIGN KEY (market_id)     REFERENCES markets (id) ON DELETE SET NULL,
  CONSTRAINT fk_audit_actor  FOREIGN KEY (actor_user_id) REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shared hosting has no queue worker. Mail and SMS are written here and a
-- one-minute cron drains the table, so a slow SMTP call never blocks a
-- checkout and a failed send retries instead of vanishing.
CREATE TABLE notifications (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  market_id    INT UNSIGNED NULL,
  user_id      BIGINT UNSIGNED NULL,
  channel      ENUM('email','sms') NOT NULL DEFAULT 'email',
  template     VARCHAR(64)  NOT NULL,                    -- 'job.published'
  recipient    VARCHAR(191) NOT NULL,
  subject      VARCHAR(191) NOT NULL DEFAULT '',
  payload      TEXT         NULL,                        -- JSON template vars
  status       ENUM('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
  attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error   VARCHAR(255) NOT NULL DEFAULT '',
  send_after   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at      DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_notif_drain (status, send_after),
  KEY ix_notif_user (user_id, created_at),
  CONSTRAINT fk_notif_market FOREIGN KEY (market_id) REFERENCES markets (id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Applied migration tracker, so a later schema change can be applied safely.
CREATE TABLE migrations (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  filename   VARCHAR(191) NOT NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_migrations_file (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pro_profiles.hero_photo_id references pro_photos, which is created after it.
ALTER TABLE pro_profiles
  ADD CONSTRAINT fk_pro_hero FOREIGN KEY (hero_photo_id) REFERENCES pro_photos (id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_pro_verifier FOREIGN KEY (license_verified_by) REFERENCES users (id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
