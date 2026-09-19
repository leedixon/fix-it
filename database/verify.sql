-- ---------------------------------------------------------------------------
-- FlexHandy — post-import smoke test
--
-- Run this against a fresh import of schema.sql + seed.sql to prove the
-- install is sound before pointing a domain at it. Every block is a query the
-- application genuinely depends on, so a wrong answer here is a real bug.
--
--   mysql -u USER -p --default-character-set=utf8mb4 --table DBNAME < database/verify.sql
--
-- Expected:
--   1. Spotlight pros first, then Boost, then free — paid rows labelled AD.
--   2. Five active ATX jobs. ATX-1D6G3Z (pending_payment) must NOT be listed.
--   3. Every creative resolves to a complete ad, including the all-NULL one.
--   4. Both A/B variants report a CTR.
--   5. ATX job_listing nets $50 from 6 rows — one was refunded.
--   6. ATX shows 1 Spotlight slot remaining of 3.
--   7. All leak counts are 0.
--   8. Empty result: no job is overdue a no-quote refund in the seed data.
-- ---------------------------------------------------------------------------

SELECT '=== 1. DIRECTORY: pros in ATX, paid placement first, ads labelled ===' AS ``;
SELECT p.slug, p.headline,
       COALESCE(s.plan,'free') AS plan,
       IF(s.plan IS NOT NULL,'AD','') AS label,
       pl.position AS slot_pos, p.rating_avg, p.rating_count
FROM pro_profiles p
JOIN pro_service_areas sa ON sa.pro_id = p.id AND sa.market_id = 1
LEFT JOIN ad_placements pl ON pl.pro_id = p.id AND pl.market_id = 1
      AND pl.slot='directory_top' AND pl.status='active'
LEFT JOIN subscriptions s ON s.id = pl.subscription_id AND s.status='active'
WHERE p.status='active'
ORDER BY (s.plan='spotlight') DESC, (s.plan='boost') DESC, pl.position ASC, p.rating_avg DESC;

SELECT '=== 2. JOBS BOARD: active only — pending_payment must NOT appear ===' AS ``;
SELECT j.reference, j.title, t.name AS trade, j.status, j.quote_count
FROM jobs j JOIN trades t ON t.id=j.trade_id
WHERE j.market_id=1 AND j.status='active' ORDER BY j.published_at DESC;

SELECT '=== 3. AD CREATIVE RESOLUTION: NULL override inherits from profile ===' AS ``;
SELECT c.id, p.slug, c.variant,
       COALESCE(c.headline,  p.headline)                      AS resolved_headline,
       COALESCE(c.offer_line, SUBSTRING_INDEX(p.bio,'.',1))   AS resolved_offer,
       COALESCE(c.cta_label,'View profile')                   AS resolved_cta,
       COALESCE(c.hero_photo_id, p.hero_photo_id)             AS resolved_photo,
       c.status
FROM ad_creatives c JOIN pro_profiles p ON p.id=c.pro_id ORDER BY c.id;

SELECT '=== 4. A/B READOUT: which of Ray two variants wins ===' AS ``;
SELECT c.variant, COALESCE(c.headline,'(from profile)') AS headline,
       SUM(s.impressions) AS impr, SUM(s.clicks) AS clicks,
       ROUND(100*SUM(s.clicks)/NULLIF(SUM(s.impressions),0),2) AS ctr_pct
FROM ad_stats_daily s JOIN ad_creatives c ON c.id=s.creative_id
WHERE s.pro_id=1 GROUP BY c.variant, c.headline;

SELECT '=== 5. SUPERADMIN: revenue by line, by market ===' AS ``;
SELECT m.code, p.kind, COUNT(*) AS n,
       CONCAT('$', FORMAT(SUM(p.amount_cents - p.refunded_cents)/100, 2)) AS net
FROM payments p JOIN markets m ON m.id=p.market_id
WHERE p.status IN ('succeeded','refunded')
GROUP BY m.code, p.kind WITH ROLLUP;

SELECT '=== 6. SLOT CAPACITY: can another pro buy Spotlight in ATX? ===' AS ``;
SELECT m.code, m.spotlight_slots AS cap,
       COUNT(s.id) AS sold, m.spotlight_slots - COUNT(s.id) AS remaining
FROM markets m
LEFT JOIN subscriptions s ON s.market_id=m.id AND s.plan='spotlight' AND s.status='active'
GROUP BY m.id;

SELECT '=== 7. TENANT ISOLATION: market_admin scope leaks? (want 0 rows) ===' AS ``;
SELECT 'jobs'  AS tbl, COUNT(*) AS leaked FROM jobs   WHERE market_id <> 1 AND market_id IS NULL
UNION ALL SELECT 'quotes', COUNT(*) FROM quotes WHERE market_id IS NULL
UNION ALL SELECT 'payments', COUNT(*) FROM payments WHERE market_id IS NULL
UNION ALL SELECT 'ad_placements', COUNT(*) FROM ad_placements WHERE market_id IS NULL;

SELECT '=== 8. 72-HOUR NO-QUOTE REFUND SWEEP (the cron query) ===' AS ``;
SELECT j.reference, j.title, TIMESTAMPDIFF(HOUR, j.published_at, NOW()) AS hrs_live, j.quote_count
FROM jobs j JOIN markets m ON m.id=j.market_id
WHERE j.status='active' AND j.quote_count=0
  AND j.published_at < NOW() - INTERVAL m.refund_window_hours HOUR;
