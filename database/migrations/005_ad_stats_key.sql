-- ===========================================================================
-- 005 — make the daily ad rollup upsertable
--
-- uq_stats_day was (stat_date, placement_id, creative_id). Ads here are
-- derived from the tradesperson's own profile rather than from an uploaded
-- creative, so creative_id is always NULL — and MySQL treats NULLs as
-- distinct in a unique index, which means the key never matched and
-- INSERT ... ON DUPLICATE KEY UPDATE would have inserted a new row for every
-- single impression instead of incrementing one.
--
-- Keyed on (stat_date, placement_id) instead. creative_id stays on the table
-- for the day there are uploaded creatives; adding it back to the key is
-- another migration then.
-- ===========================================================================

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'ad_stats_daily'
      AND index_name = 'uq_stats_day') > 0,
  'ALTER TABLE ad_stats_daily DROP INDEX uq_stats_day',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Existing rows may already hold several entries for one placement on one
-- day — that is exactly what the broken key allowed. Collapse them into one,
-- summing the counters, before the new key forbids them. Summing rather than
-- picking one keeps the totals a tradesperson may already have been shown.

CREATE TEMPORARY TABLE tmp_ad_rollup AS
SELECT MIN(id) AS keep_id, stat_date, placement_id,
       SUM(impressions) AS impressions, SUM(clicks) AS clicks,
       SUM(quotes_sent) AS quotes_sent, SUM(jobs_won) AS jobs_won
  FROM ad_stats_daily
 GROUP BY stat_date, placement_id;

UPDATE ad_stats_daily s
  JOIN tmp_ad_rollup r ON r.keep_id = s.id
   SET s.impressions = r.impressions, s.clicks = r.clicks,
       s.quotes_sent = r.quotes_sent, s.jobs_won = r.jobs_won;

DELETE s FROM ad_stats_daily s
  LEFT JOIN tmp_ad_rollup r ON r.keep_id = s.id
 WHERE r.keep_id IS NULL;

DROP TEMPORARY TABLE tmp_ad_rollup;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'ad_stats_daily'
      AND index_name = 'uq_stats_placement_day') = 0,
  'ALTER TABLE ad_stats_daily ADD UNIQUE KEY uq_stats_placement_day (stat_date, placement_id)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

INSERT IGNORE INTO migrations (filename) VALUES ('005_ad_stats_key.sql');
