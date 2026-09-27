-- ===========================================================================
-- 009 — flag the seeded money, so it stops being counted as real
--
-- The admin dashboard showed "Revenue, 30 days: $496" on a site with one
-- listing, no open jobs and nothing sold. $486 of that was invented by the
-- seed and about $10 was a real test card.
--
-- Migration 003 gave is_demo to the four tables whose rows a VISITOR sees —
-- users, pro_profiles, jobs, reviews — and bin/demo.php purges exactly those
-- four. But the seed writes to twenty-one tables, and the ones carrying money
-- were not among the four. So:
--
--   * payments kept $496 of fabricated revenue that no filter could exclude,
--     because there was no flag to filter on;
--   * demo.php purge deleted the demo pros and jobs and left their payments,
--     subscriptions, placements and quotes behind as orphans;
--   * every one of those rows feeds a number an owner makes decisions from.
--
-- The visitor-facing rule was right and the internal one was never written.
-- This adds the same flag to the seven transactional tables and backfills it.
--
-- Backfill has to work on a database where the demo PARENTS are already gone,
-- which is the state production is in. So it keys off two things that survive
-- a purge: the seed's own fake Stripe identifiers (pi_demo_, sub_demo_), and
-- orphanhood — a row pointing at a pro or job that no longer exists came from
-- the seed, because nothing else deletes those rows.
--
-- Nothing is deleted here. Flagging is reversible and a DELETE across payment
-- history is not; bin/demo.php purge is what removes them, deliberately.
--
-- Idempotent: each change is guarded by an information_schema check and run
-- through a prepared statement.
-- ===========================================================================

-- --- the flag, on every table that carries money or activity ---------------

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE()
                 AND table_name = 'payments' AND column_name = 'is_demo') = 0,
  'ALTER TABLE payments ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER status', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE()
                 AND table_name = 'subscriptions' AND column_name = 'is_demo') = 0,
  'ALTER TABLE subscriptions ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER status', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE()
                 AND table_name = 'quotes' AND column_name = 'is_demo') = 0,
  'ALTER TABLE quotes ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER status', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE()
                 AND table_name = 'ad_placements' AND column_name = 'is_demo') = 0,
  'ALTER TABLE ad_placements ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER status', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE()
                 AND table_name = 'ad_creatives' AND column_name = 'is_demo') = 0,
  'ALTER TABLE ad_creatives ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER status', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE()
                 AND table_name = 'ad_stats_daily' AND column_name = 'is_demo') = 0,
  'ALTER TABLE ad_stats_daily ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE()
                 AND table_name = 'moderation_items' AND column_name = 'is_demo') = 0,
  'ALTER TABLE moderation_items ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER status', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- --- backfill: the seed's own fake Stripe identifiers -----------------------
--
-- A real payment intent from Stripe is pi_ followed by 24 base-58 characters
-- and never contains the word demo. These are the seed's, and they survive a
-- purge because nothing was ever deleting them.

UPDATE payments SET is_demo = 1
 WHERE is_demo = 0
   AND (stripe_payment_intent LIKE 'pi_demo_%' OR stripe_checkout_session LIKE '%_demo_%');

UPDATE subscriptions SET is_demo = 1
 WHERE is_demo = 0 AND stripe_subscription_id LIKE 'sub_demo_%';

-- --- backfill: orphans ------------------------------------------------------
--
-- A row pointing at a pro or a job that is not there came from the seed. The
-- only thing that deletes a pro_profile or a job is demo.php purge, so an
-- orphan is a purge survivor by definition. Written as NOT EXISTS rather than
-- a LEFT JOIN so it reads as the question being asked.

UPDATE quotes q SET q.is_demo = 1
 WHERE q.is_demo = 0
   AND (NOT EXISTS (SELECT 1 FROM pro_profiles p WHERE p.id = q.pro_id)
     OR NOT EXISTS (SELECT 1 FROM jobs j WHERE j.id = q.job_id)
     OR EXISTS (SELECT 1 FROM pro_profiles p WHERE p.id = q.pro_id AND p.is_demo = 1)
     OR EXISTS (SELECT 1 FROM jobs j WHERE j.id = q.job_id AND j.is_demo = 1));

UPDATE payments pm SET pm.is_demo = 1
 WHERE pm.is_demo = 0
   AND (pm.job_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM jobs j WHERE j.id = pm.job_id))
   AND pm.stripe_payment_intent NOT LIKE 'pi_3%'
   AND pm.stripe_payment_intent NOT LIKE 'pi_1%';

UPDATE ad_placements pl SET pl.is_demo = 1
 WHERE pl.is_demo = 0
   AND (NOT EXISTS (SELECT 1 FROM pro_profiles p WHERE p.id = pl.pro_id)
     OR EXISTS (SELECT 1 FROM pro_profiles p WHERE p.id = pl.pro_id AND p.is_demo = 1)
     OR EXISTS (SELECT 1 FROM subscriptions s WHERE s.id = pl.subscription_id AND s.is_demo = 1));

UPDATE ad_creatives c SET c.is_demo = 1
 WHERE c.is_demo = 0
   AND (NOT EXISTS (SELECT 1 FROM pro_profiles p WHERE p.id = c.pro_id)
     OR EXISTS (SELECT 1 FROM pro_profiles p WHERE p.id = c.pro_id AND p.is_demo = 1));

UPDATE ad_stats_daily d SET d.is_demo = 1
 WHERE d.is_demo = 0
   AND (NOT EXISTS (SELECT 1 FROM pro_profiles p WHERE p.id = d.pro_id)
     OR EXISTS (SELECT 1 FROM pro_profiles p WHERE p.id = d.pro_id AND p.is_demo = 1)
     OR EXISTS (SELECT 1 FROM ad_placements pl WHERE pl.id = d.placement_id AND pl.is_demo = 1));

-- moderation_items is polymorphic, so each subject_type is asked separately.
-- Writing only the pro_profile case the first time left two of three seeded
-- rows unflagged and still counting towards "Waiting on you".

UPDATE moderation_items m SET m.is_demo = 1
 WHERE m.is_demo = 0
   AND m.subject_type = 'pro_profile'
   AND (NOT EXISTS (SELECT 1 FROM pro_profiles p WHERE p.id = m.subject_id)
     OR EXISTS (SELECT 1 FROM pro_profiles p WHERE p.id = m.subject_id AND p.is_demo = 1));

UPDATE moderation_items m SET m.is_demo = 1
 WHERE m.is_demo = 0
   AND m.subject_type = 'job'
   AND (NOT EXISTS (SELECT 1 FROM jobs j WHERE j.id = m.subject_id)
     OR EXISTS (SELECT 1 FROM jobs j WHERE j.id = m.subject_id AND j.is_demo = 1));

UPDATE moderation_items m SET m.is_demo = 1
 WHERE m.is_demo = 0
   AND m.subject_type = 'ad_creative'
   AND (NOT EXISTS (SELECT 1 FROM ad_creatives c WHERE c.id = m.subject_id)
     OR EXISTS (SELECT 1 FROM ad_creatives c WHERE c.id = m.subject_id AND c.is_demo = 1));
