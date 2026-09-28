-- ===========================================================================
-- 010 — job alerts for tradespeople who have not listed yet
--
-- "Get these by email" on the jobs board pointed at /for-pros. The alerts
-- themselves already existed and worked: when a job's payment clears, the
-- webhook emails every active listed pro covering that county and trade. What
-- did not exist was any way to receive them WITHOUT listing first — so the
-- button promised a signup box and delivered a sales page.
--
-- That gap matters more than a mislabelled button. A tradesperson who wants to
-- see the work before committing to a profile is the single warmest prospect
-- this site can have, and there was nowhere for them to go.
--
-- Free, and that is a product decision rather than an oversight. /for-pros
-- promises "no per-lead charge" in the hero and answers "do you sell my lead"
-- with "no". A paid subscription to receive jobs is a per-lead charge with a
-- monthly wrapper, and it would make that page read as weasel words.
--
-- Three tables rather than a CSV column, because the matching query — find
-- everyone who wants THIS county and THIS trade — is the whole feature, and a
-- comma-separated list cannot be joined.
--
-- Idempotent throughout: guarded by information_schema and run through
-- prepared statements.
-- ===========================================================================

SET @sql := IF((SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'job_alerts') = 0,
"CREATE TABLE job_alerts (
   id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
   market_id      INT UNSIGNED NOT NULL,
   email          VARCHAR(191) NOT NULL,
   first_name     VARCHAR(80) NOT NULL DEFAULT '',
   business_name  VARCHAR(160) NOT NULL DEFAULT '',
   -- 64 hex characters. Confirms the address on the way in and unsubscribes
   -- on the way out; one secret, because a subscriber who can prove they own
   -- the address can already do both.
   token          CHAR(64) NOT NULL,
   -- Nothing is sent until this is set. Confirmed opt-in is not legally
   -- required in the US, but this product IS email — if the domain's
   -- reputation goes, the alerts stop arriving and there is no product left.
   confirmed_at   DATETIME NULL DEFAULT NULL,
   unsubscribed_at DATETIME NULL DEFAULT NULL,
   last_sent_at   DATETIME NULL DEFAULT NULL,
   sent_count     INT UNSIGNED NOT NULL DEFAULT 0,
   -- Set when this address later appears on a real listing, so the admin can
   -- see which subscribers converted and stop counting them twice.
   became_pro_id  BIGINT UNSIGNED NULL DEFAULT NULL,
   ip             VARBINARY(16) NULL,
   created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (id),
   UNIQUE KEY uniq_job_alerts_token (token),
   UNIQUE KEY uniq_job_alerts_email_market (market_id, email),
   KEY idx_job_alerts_live (market_id, confirmed_at, unsubscribed_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"DO 0");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- --- what they want to hear about -------------------------------------------
--
-- Both are required at signup. A subscriber who picks nothing would match
-- every job in six counties across ten trades, which is spam by any other
-- name and unsubscribes itself within a week.

SET @sql := IF((SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'job_alert_trades') = 0,
"CREATE TABLE job_alert_trades (
   alert_id BIGINT UNSIGNED NOT NULL,
   trade_id SMALLINT UNSIGNED NOT NULL,
   PRIMARY KEY (alert_id, trade_id),
   KEY idx_jat_trade (trade_id),
   CONSTRAINT fk_jat_alert FOREIGN KEY (alert_id) REFERENCES job_alerts (id) ON DELETE CASCADE
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"DO 0");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'job_alert_counties') = 0,
"CREATE TABLE job_alert_counties (
   alert_id  BIGINT UNSIGNED NOT NULL,
   county_id INT UNSIGNED NOT NULL,
   PRIMARY KEY (alert_id, county_id),
   KEY idx_jac_county (county_id),
   CONSTRAINT fk_jac_alert FOREIGN KEY (alert_id) REFERENCES job_alerts (id) ON DELETE CASCADE
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"DO 0");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- --- a listed tradesperson can stop the alerts too --------------------------
--
-- No email on this site carried an unsubscribe link. Job alerts are the one
-- kind that clearly needs one — they are sent repeatedly, unprompted, about
-- new opportunities — and CAN-SPAM wants a working opt-out in every message
-- of that kind, enforced per message rather than per campaign.
--
-- A flag rather than a row so it travels with the profile: suspend the
-- listing, reinstate it later, and the preference is still theirs. The
-- unsubscribe link carries an HMAC of the profile id keyed on app.key, so no
-- new token table is needed for a link nobody has to remember.

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'pro_profiles'
                   AND column_name = 'job_alerts_off') = 0,
  'ALTER TABLE pro_profiles ADD COLUMN job_alerts_off TINYINT(1) NOT NULL DEFAULT 0',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
