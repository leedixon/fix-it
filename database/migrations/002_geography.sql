-- ---------------------------------------------------------------------------
-- Adds the geography layer to a database created before it existed.
--
-- For an install that already has the original schema (25 tables, market-level
-- pro service areas, no counties). A fresh install gets all of this from
-- schema.sql and must NOT run this file.
--
--   mysql -u USER -p DBNAME < database/migrations/002_geography.sql
--
-- Then reload the demo data, which is now Northwest Illinois rather than Texas:
--
--   mysql -u USER -p DBNAME < database/seed.sql
--
-- SAFE TO RE-RUN. MySQL has no ADD COLUMN IF NOT EXISTS, so every ALTER below
-- is guarded by a check against information_schema and skipped if it has
-- already been applied. A migration that fails halfway must be re-runnable:
-- MySQL does not roll back DDL, so a partial apply is a state you have to be
-- able to recover from by running the file again.
--
-- Verified by building a database from the pre-geography schema, applying this
-- file twice, and diffing the result against a fresh schema.sql install.
-- ---------------------------------------------------------------------------

-- --- counties, cities, and the market they belong to ------------------------

CREATE TABLE IF NOT EXISTS counties (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fips        CHAR(5)      NOT NULL,
  name        VARCHAR(80)  NOT NULL,
  short_name  VARCHAR(80)  NOT NULL,
  slug        VARCHAR(80)  NOT NULL,
  state       CHAR(2)      NOT NULL,
  population  INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_counties_fips (fips),
  UNIQUE KEY uq_counties_slug (slug),
  KEY ix_counties_state (state, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS market_counties (
  market_id INT UNSIGNED NOT NULL,
  county_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (market_id, county_id),
  UNIQUE KEY uq_county_one_market (county_id),
  CONSTRAINT fk_mc_market FOREIGN KEY (market_id) REFERENCES markets (id)  ON DELETE CASCADE,
  CONSTRAINT fk_mc_county FOREIGN KEY (county_id) REFERENCES counties (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cities (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  county_id  INT UNSIGNED NOT NULL,
  market_id  INT UNSIGNED NOT NULL,
  name       VARCHAR(80)  NOT NULL,
  slug       VARCHAR(80)  NOT NULL,
  state      CHAR(2)      NOT NULL,
  population INT UNSIGNED NULL,
  has_page   TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cities_market_slug (market_id, slug),
  KEY ix_cities_county (county_id, name),
  KEY ix_cities_pages (market_id, has_page, sort_order),
  CONSTRAINT fk_cities_county FOREIGN KEY (county_id) REFERENCES counties (id) ON DELETE CASCADE,
  CONSTRAINT fk_cities_market FOREIGN KEY (market_id) REFERENCES markets (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS zip_counties (
  zip        VARCHAR(10)  NOT NULL,
  county_id  INT UNSIGNED NOT NULL,
  is_primary TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (zip, county_id),
  KEY ix_zip_county (county_id),
  CONSTRAINT fk_zc_county FOREIGN KEY (county_id) REFERENCES counties (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- pros gain a home county and city ---------------------------------------

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'pro_profiles' AND column_name = 'home_county_id') = 0,
  'ALTER TABLE pro_profiles ADD COLUMN home_county_id INT UNSIGNED NULL AFTER service_radius_miles',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'pro_profiles' AND column_name = 'home_city_id') = 0,
  'ALTER TABLE pro_profiles ADD COLUMN home_city_id INT UNSIGNED NULL AFTER home_county_id',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.table_constraints
    WHERE table_schema = DATABASE() AND table_name = 'pro_profiles' AND constraint_name = 'fk_pro_county') = 0,
  'ALTER TABLE pro_profiles ADD CONSTRAINT fk_pro_county FOREIGN KEY (home_county_id) REFERENCES counties (id) ON DELETE SET NULL',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.table_constraints
    WHERE table_schema = DATABASE() AND table_name = 'pro_profiles' AND constraint_name = 'fk_pro_city') = 0,
  'ALTER TABLE pro_profiles ADD CONSTRAINT fk_pro_city FOREIGN KEY (home_city_id) REFERENCES cities (id) ON DELETE SET NULL',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- --- coverage moves from market level to county level -----------------------
-- pro_service_areas held only "this pro works in this market", which cannot
-- express a Galena furnace tech who will not drive to Belvidere. Nothing is
-- migrated across because the old rows carry no county information to migrate;
-- coverage is re-established by the seed, or by pros choosing their counties.

CREATE TABLE IF NOT EXISTS pro_county_areas (
  pro_id    BIGINT UNSIGNED NOT NULL,
  county_id INT UNSIGNED NOT NULL,
  market_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (pro_id, county_id),
  KEY ix_pca_market (market_id, county_id),
  CONSTRAINT fk_pca_pro    FOREIGN KEY (pro_id)    REFERENCES pro_profiles (id) ON DELETE CASCADE,
  CONSTRAINT fk_pca_county FOREIGN KEY (county_id) REFERENCES counties (id)     ON DELETE CASCADE,
  CONSTRAINT fk_pca_market FOREIGN KEY (market_id) REFERENCES markets (id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS pro_service_areas;

-- --- jobs gain a county and a city ------------------------------------------

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'jobs' AND column_name = 'county_id') = 0,
  'ALTER TABLE jobs ADD COLUMN county_id INT UNSIGNED NULL AFTER trade_id',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'jobs' AND column_name = 'city_id') = 0,
  'ALTER TABLE jobs ADD COLUMN city_id INT UNSIGNED NULL AFTER county_id',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'jobs' AND index_name = 'ix_jobs_city') = 0,
  'ALTER TABLE jobs ADD KEY ix_jobs_city (city_id, status, published_at)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'jobs' AND index_name = 'ix_jobs_county') = 0,
  'ALTER TABLE jobs ADD KEY ix_jobs_county (county_id, status)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.table_constraints
    WHERE table_schema = DATABASE() AND table_name = 'jobs' AND constraint_name = 'fk_jobs_county') = 0,
  'ALTER TABLE jobs ADD CONSTRAINT fk_jobs_county FOREIGN KEY (county_id) REFERENCES counties (id) ON DELETE SET NULL',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.table_constraints
    WHERE table_schema = DATABASE() AND table_name = 'jobs' AND constraint_name = 'fk_jobs_city') = 0,
  'ALTER TABLE jobs ADD CONSTRAINT fk_jobs_city FOREIGN KEY (city_id) REFERENCES cities (id) ON DELETE SET NULL',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- --- trades ------------------------------------------------------------------
-- Deliberately not touched here. The northern-market list adds Roofing &
-- Gutters and Landscaping & Snow, which shifts the ids of Fencing & Decks and
-- Odd Jobs — so an INSERT here would collide with existing rows that mean
-- something else. seed.sql reloads the full list. An install with real jobs
-- already referencing trade ids would need a remapping migration instead.

INSERT IGNORE INTO migrations (filename) VALUES ('002_geography.sql');
