-- ===========================================================================
-- 011 — a place for a business's logo
--
-- pro_photos has existed since the first build, with a moderation status,
-- captions, dimensions and a sort order — everything a gallery needs — and
-- nothing has ever written to it. Same story as reviews and hired_pro_id: the
-- table was designed and the feature was never reachable.
--
-- The gallery needs no schema change. A logo does: there was nowhere to put
-- one, which is why every profile shows coloured initials.
--
-- A logo is not a work photo and gets its own column rather than a row in
-- pro_photos with a flag. They are cropped differently (a square, against a
-- gallery's landscape), kept in different formats (PNG with transparency,
-- against a re-encoded JPEG), and there is exactly one — a "which of these
-- five is the logo" question is one nobody should have to answer.
-- ===========================================================================

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'pro_profiles'
                   AND column_name = 'logo_path') = 0,
  'ALTER TABLE pro_profiles ADD COLUMN logo_path VARCHAR(255) NULL DEFAULT NULL',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Nothing on a suspended or rejected listing should be reachable, so the
-- serving route joins back to the profile. This index is what keeps that
-- join cheap once a gallery has a few hundred rows in it.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = 'pro_photos'
                   AND index_name = 'idx_pro_photos_live') = 0,
  'ALTER TABLE pro_photos ADD KEY idx_pro_photos_live (pro_id, status, sort_order)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
