-- ===========================================================================
-- 008 — let a homeowner actually leave a review
--
-- The reviews table, the rating columns, the display on profiles and the
-- honest aggregateRating have all existed since the first build. What has
-- never existed is any way to write a row into it. Every rating on the site
-- today came out of the seed.
--
-- Two things were missing, and they are both about authorisation.
--
-- A homeowner has no account. They pay, they get a job reference, and that
-- is the whole relationship. So a review has to be authorised by something
-- they hold rather than something they log into — and it cannot be the
-- reference, because the reference is printed on the public jobs board at
-- /jobs/{reference}. Anyone reading the board could leave a review on
-- anyone's job. Hence a separate token that is never displayed.
--
-- And jobs.hired_pro_id has been in the schema since the beginning and is
-- written by nothing. Asking "which of these did you hire?" as the first
-- question of the review form is what finally fills it — one question the
-- homeowner can answer months later without having kept any state.
--
-- Idempotent: MySQL has no ADD COLUMN IF NOT EXISTS, so each change is
-- guarded by an information_schema check and run through a prepared
-- statement. Safe to apply twice, and safe on a half-applied database.
-- ===========================================================================

-- --- the token the invitation carries ---------------------------------------
--
-- Nullable: a job only gets one when its invitation is sent, so an
-- un-invited job has nothing to guess at. 64 hex characters from
-- random_bytes(32).

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'jobs' AND column_name = 'review_token') = 0,
  'ALTER TABLE jobs ADD COLUMN review_token CHAR(64) NULL DEFAULT NULL AFTER hired_pro_id',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Unique so a collision is a database error rather than one homeowner
-- rating another homeowner's tradesperson.
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'jobs' AND index_name = 'uniq_jobs_review_token') = 0,
  'ALTER TABLE jobs ADD UNIQUE KEY uniq_jobs_review_token (review_token)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- --- when we asked ----------------------------------------------------------
--
-- Set once. The sweep reads it to know not to ask the same person twice,
-- which matters more than it sounds: a second "how did it go?" for a job
-- somebody already answered reads as a site that is not paying attention.

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'jobs' AND column_name = 'review_invited_at') = 0,
  'ALTER TABLE jobs ADD COLUMN review_invited_at DATETIME NULL DEFAULT NULL AFTER review_token',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- --- one review per job -----------------------------------------------------
--
-- A homeowner hires one tradesperson for one job, so one job yields one
-- review. Enforced here rather than only in PHP because the alternative is a
-- double-submitted form quietly doubling a pro's review count, and a rating
-- that can be inflated by pressing a button twice is not a rating.
--
-- Verified clean before adding: the three seeded reviews sit on three
-- different jobs.

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'reviews' AND index_name = 'uniq_reviews_job') = 0,
  'ALTER TABLE reviews ADD UNIQUE KEY uniq_reviews_job (job_id)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
