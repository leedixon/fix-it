-- ===========================================================================
-- 014 — the contact page becomes a form, and the messages are kept
--
-- /contact was two mailto: links. That works, right up until it does not: a
-- phone with no mail app configured opens nothing at all, a webmail user gets
-- a blank compose window in a tab they did not want, and either way the
-- message exists only in one inbox. A bounce, a spam filter or a mis-filed
-- thread loses it silently, and there is no record on the site that anybody
-- ever wrote in.
--
-- So the form posts here first and the email goes second. The email can fail
-- without the message being lost, which is the whole point of the table.
--
-- The audience is stored rather than inferred from the wording, because it
-- decides which fields were asked for and which are worth reading back. A
-- homeowner is asked for a job reference; a tradesperson for a business name
-- and the counties they cover.
--
-- Idempotent: guarded by information_schema and run through prepared
-- statements, like every migration here.
-- ===========================================================================

SET @sql := IF((SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'contact_messages') = 0,
"CREATE TABLE contact_messages (
   id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
   market_id     INT UNSIGNED NOT NULL,
   -- 'homeowner', 'pro' or 'other'. Which tab they chose, kept as written
   -- rather than guessed at later from the message body.
   audience      VARCHAR(20) NOT NULL DEFAULT 'other',
   name          VARCHAR(120) NOT NULL,
   email         VARCHAR(191) NOT NULL,
   phone         VARCHAR(32) NOT NULL DEFAULT '',
   -- Homeowners: the job it is about, if they have one. Tradespeople: their
   -- business. One column each rather than a blob, because both are searched.
   job_reference VARCHAR(32) NOT NULL DEFAULT '',
   business_name VARCHAR(160) NOT NULL DEFAULT '',
   subject       VARCHAR(200) NOT NULL DEFAULT '',
   message       TEXT NOT NULL,
   -- Set the moment a staff member opens it. Drives the sidebar count, and
   -- nothing else: a message is never deleted from here by the admin screen,
   -- because the record of somebody asking for help outlives the reply.
   read_at       DATETIME NULL DEFAULT NULL,
   read_by       BIGINT UNSIGNED NULL DEFAULT NULL,
   -- Whether the notification email actually went. A message sitting here
   -- with this null is one nobody was told about.
   notified_at   DATETIME NULL DEFAULT NULL,
   ip            VARBINARY(16) NULL,
   user_agent    VARCHAR(255) NOT NULL DEFAULT '',
   created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (id),
   KEY idx_contact_unread (market_id, read_at, created_at),
   KEY idx_contact_email (market_id, email)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"DO 0");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
