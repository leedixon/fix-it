-- ===========================================================================
-- 012 — the seeded photos are demo data too
--
-- Migration 009 gave the demo flag to the seven transactional tables that
-- were feeding invented numbers into the admin. pro_photos was not among
-- them, because at that point nothing rendered a photo and the rows were
-- inert.
--
-- Building the gallery made them live. The seed inserts seven pro_photos
-- rows whose paths point at files that have never existed — so the moment a
-- profile started drawing its gallery, three broken images appeared on Ojo
-- Plumbing and four more across the other sample listings.
--
-- Found by uploading a photo and looking at the result, which showed three
-- pictures already on a profile that had never had one.
--
-- Backfilled by orphanhood: a row whose file is not on disk and whose pro is
-- a sample listing came from the seed. Nothing is deleted here — that is
-- demo.php purge's job, and it now covers this table too.
-- ===========================================================================

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'pro_photos'
                   AND column_name = 'is_demo') = 0,
  'ALTER TABLE pro_photos ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE pro_photos ph SET ph.is_demo = 1
 WHERE ph.is_demo = 0
   AND (NOT EXISTS (SELECT 1 FROM pro_profiles p WHERE p.id = ph.pro_id)
     OR EXISTS (SELECT 1 FROM pro_profiles p WHERE p.id = ph.pro_id AND p.is_demo = 1)
     -- The seed writes a path with a folder in it; a real upload stores a
     -- bare generated filename, because the folder comes from the pro id.
     OR ph.path LIKE '%/%');
