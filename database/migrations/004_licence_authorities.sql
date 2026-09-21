-- ===========================================================================
-- 004 — who licenses which trade, in which state
--
-- Trade licensing in the United States has no national registry, no common
-- number format, and no agreement about which trades need a licence at all.
-- Illinois alone splits it three ways: plumbers are licensed by Public Health,
-- roofers by IDFPR, and electricians by individual cities with no state
-- licence existing.
--
-- That belongs in data, not in code. The review screen looks up whoever
-- licenses this applicant's trade in this applicant's state and says what to
-- check. Opening a market in Wisconsin is then a few rows an administrator
-- types in, not a deploy.
--
-- Global reference data, like counties and trades: not tenant scoped, because
-- Illinois licensing law does not vary by which market is looking at it.
--
-- Idempotent. Safe to apply twice.
-- ===========================================================================

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'licence_authorities') = 0,
  "CREATE TABLE licence_authorities (
     id            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
     state         CHAR(2)      NOT NULL,

     -- 0 means 'every other trade in this state', which is the fallback row.
     -- Deliberately 0 rather than NULL: MySQL treats NULLs as distinct in a
     -- unique index, so a NULL here would allow several conflicting fallback
     -- rows for one state. No foreign key for the same reason; the trade
     -- taxonomy is fixed and nothing deletes from it.
     trade_id      SMALLINT UNSIGNED NOT NULL DEFAULT 0,

     -- Whether the state licenses this trade at all. False is a real answer,
     -- not a missing one — an Illinois electrician has no state licence and
     -- treating that as a red flag would be wrong.
     licensed      TINYINT(1)   NOT NULL DEFAULT 1,

     authority     VARCHAR(120) NOT NULL DEFAULT '',
     lookup_url    VARCHAR(255) NOT NULL DEFAULT '',
     number_format VARCHAR(60)  NOT NULL DEFAULT '',
     guidance      VARCHAR(600) NOT NULL DEFAULT '',

     updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
     PRIMARY KEY (id),
     UNIQUE KEY uq_licence_state_trade (state, trade_id),
     KEY ix_licence_state (state)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- --- Illinois ---------------------------------------------------------------
-- Only what is actually known. A state with no rows produces an explicit
-- "we have no guidance for this yet" on the review screen, which is the
-- correct thing to say — silence must never read as "no licence needed".

INSERT INTO licence_authorities (state, trade_id, licensed, authority, lookup_url, number_format, guidance)
VALUES
 ('IL', 1, 1, 'Illinois Department of Public Health',
  'https://dph.illinois.gov/topics-services/environmental-health-protection/plumbing.html',
  '058-xxxxxx',
  'Plumbers are licensed by Public Health, not IDFPR. Searching the IDFPR register for a plumber finds nothing and means nothing. Check the number is current and in this person''s name.'),

 ('IL', 7, 1, 'IDFPR — Division of Professional Regulation',
  'https://idfpr.illinois.gov/licenselookup/licenselookup.asp',
  '104-xxxxxx',
  'Roofing contractors are licensed by IDFPR under the Roofing Industry Licensing Act. Use the Professional Regulation lookup and search by business name or licence number.'),

 ('IL', 2, 0, 'The city or county',
  '', '',
  'Illinois has no statewide electrician licence. Electricians are licensed municipally — Rockford and Freeport each run their own. Ask which municipality issued it and check with that office. No state number is normal here, not a red flag.'),

 ('IL', 0, 0, 'Not licensed at state level',
  '', '',
  'Illinois does not license this trade at state level. Insurance is what matters: ask for a certificate of liability insurance, check it is current and in the business name. Some towns register contractors, so it is worth asking which.')
ON DUPLICATE KEY UPDATE
  licensed = VALUES(licensed), authority = VALUES(authority),
  lookup_url = VALUES(lookup_url), number_format = VALUES(number_format),
  guidance = VALUES(guidance);

INSERT IGNORE INTO migrations (filename) VALUES ('004_licence_authorities.sql');
