-- ===========================================================================
-- 013 — the academy
--
-- Two of them: a public one that teaches homeowners and tradespeople how to
-- use the site, and a staff handbook on running it.
--
-- The lesson TEXT is not in here, and that is the decision this table exists
-- to support. Lessons live in app/Core/Academy.php, in code, for one reason:
-- a lesson about approving a listing application should change in the same
-- commit as the approval screen. Put the words in a table and the screen
-- moves in March while the lesson still describes February, and nobody finds
-- out until a moderator follows instructions that no longer match.
--
-- What does belong in a table is everything the owner needs to change
-- WITHOUT a deploy:
--
--   * the video for a lesson, added whenever it gets recorded;
--   * whether a lesson is published at all, so one that is wrong can be
--     pulled in ten seconds rather than a release.
--
-- A row here is an override. A lesson with no row uses the defaults from
-- code, which is why this table starts empty and stays small.
-- ===========================================================================

SET @sql := IF((SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'academy_lessons') = 0,
"CREATE TABLE academy_lessons (
   slug        VARCHAR(100) NOT NULL,
   -- Watched rather than embedded blind: the renderer only accepts hosts it
   -- knows, so a pasted link cannot put an arbitrary iframe on a public page.
   video_url   VARCHAR(500) NULL DEFAULT NULL,
   -- NULL means 'whatever the code says'. Only an explicit 0 or 1 here
   -- overrides it, so publishing state is not silently reset by a deploy.
   published   TINYINT(1) NULL DEFAULT NULL,
   updated_by  BIGINT UNSIGNED NULL,
   updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   PRIMARY KEY (slug)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"DO 0");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
