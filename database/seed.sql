-- ---------------------------------------------------------------------------
-- Fix Listed — demo seed data
--
-- Mirrors the design prototype so the application has real rows to render.
-- Safe to re-run: it truncates the tenant tables first.
--
-- Every demo account uses the password:  demo-password
-- Change or delete these before the site goes public.
--
-- Import after schema.sql:  mysql -u USER -p DBNAME < database/seed.sql
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE ad_stats_daily; TRUNCATE TABLE ad_events;   TRUNCATE TABLE ad_placements;
TRUNCATE TABLE ad_creatives;   TRUNCATE TABLE subscriptions; TRUNCATE TABLE payments;
TRUNCATE TABLE reviews;        TRUNCATE TABLE messages;    TRUNCATE TABLE quotes;
TRUNCATE TABLE job_photos;     TRUNCATE TABLE jobs;        TRUNCATE TABLE pro_photos;
TRUNCATE TABLE pro_service_areas; TRUNCATE TABLE pro_skills; TRUNCATE TABLE pro_trades;
TRUNCATE TABLE pro_profiles;   TRUNCATE TABLE moderation_items; TRUNCATE TABLE audit_log;
TRUNCATE TABLE notifications;  TRUNCATE TABLE users;       TRUNCATE TABLE trades;
TRUNCATE TABLE markets;
SET FOREIGN_KEY_CHECKS = 1;

-- --- markets ---------------------------------------------------------------
-- San Marcos launches with listing_fee_cents = 0: a brand new market has no
-- jobs on the board, and charging $10 to post into an empty room is the
-- fastest way to kill it. Switch the fee on once there are pros to answer.
INSERT INTO markets (id, slug, name, code, city, state, status, listing_fee_cents, boost_slots, spotlight_slots, launched_at) VALUES
 (1,'austin',    'Austin, TX',     'ATX','Austin',    'TX','live',  1000, 12, 3, '2026-01-14 09:00:00'),
 (2,'round-rock','Round Rock, TX', 'RRK','Round Rock','TX','live',  1000,  8, 2, '2026-04-02 09:00:00'),
 (3,'san-marcos','San Marcos, TX', 'SMT','San Marcos','TX','live',     0,  6, 2, '2026-08-19 09:00:00'),
 (4,'waco',      'Waco, TX',       'WCO','Waco',      'TX','staged', 1000, 6, 2, NULL);

-- --- trades ----------------------------------------------------------------
INSERT INTO trades (id, slug, name, icon, sort_order) VALUES
 (1,'plumbing','Plumbing','pipe',10),
 (2,'electrical','Electrical','bolt',20),
 (3,'carpentry','Carpentry','saw',30),
 (4,'drywall-paint','Drywall & Paint','roller',40),
 (5,'appliance','Appliance Repair','appliance',50),
 (6,'hvac','HVAC','fan',60),
 (7,'fencing-decks','Fencing & Decks','fence',70),
 (8,'odd-jobs','Odd Jobs','tools',80);

-- --- users -----------------------------------------------------------------
-- id 1 is the superadmin: market_id NULL, the only row that may drop tenant scope.
INSERT INTO users (id, market_id, role, email, password_hash, first_name, last_name, phone, email_verified_at, sms_opt_in) VALUES
 (1, NULL,'superadmin',   'owner@fixlisted.com',   '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Lee','Dixon','',              NOW(),0),
 (2, 1,   'market_admin', 'dana@fixlisted.com',    '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Dana','Whitfield','',          NOW(),0),
 (3, 2,   'market_admin', 'marcus@fixlisted.com',  '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Marcus','Bell','',             NOW(),0),
 -- pros
 (10,1,'pro','ray@okaforplumbing.com',    '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Ray','Okafor','(512) 555-0112',   NOW(),1),
 (11,1,'pro','teresa@vancewoodwork.com',  '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Teresa','Vance','(512) 555-0134', NOW(),1),
 (12,1,'pro','dmitri.sokolov@example.com','$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Dmitri','Sokolov','(512) 555-0155',NOW(),1),
 (13,1,'pro','alma@reyespainting.com',    '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Alma','Reyes','(512) 555-0167',   NOW(),0),
 (14,1,'pro','jonah@pikeelectric.com',    '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Jonah','Pike','(512) 555-0178',   NOW(),1),
 (15,1,'pro','beatrice.lund@example.com', '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Beatrice','Lund','(512) 555-0189',NOW(),0),
 (16,2,'pro','curtis@nwosuoutdoor.com',   '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Curtis','Nwosu','(512) 555-0191',  NOW(),1),
 (17,2,'pro','priya.raman@example.com',   '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Priya','Raman','(512) 555-0203',   NOW(),1),
 (18,3,'pro','hal@brennerhvac.com',       '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Hal','Brenner','(512) 555-0214',   NOW(),1),
 -- homeowners
 (30,1,'homeowner','marissa.k@example.com','$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Marissa','Kessler','(512) 555-0148',NOW(),1),
 (31,1,'homeowner','ben.t@example.com',    '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Ben','Tran','(512) 555-0159',      NOW(),1),
 (32,1,'homeowner','ashby@example.com',    '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Claire','Ashby','',                 NOW(),0),
 (33,1,'homeowner','grady.p@example.com',  '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Grady','Poole','',                  NOW(),0),
 (34,1,'homeowner','simone.a@example.com', '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Simone','Adeyemi','',               NOW(),0),
 (35,2,'homeowner','devon.l@example.com',  '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Devon','Little','',                 NOW(),0),
 (36,2,'homeowner','nora.f@example.com',   '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Nora','Fielding','',                NOW(),0),
 (37,3,'homeowner','rick.m@example.com',   '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK','Rick','Mendez','',                  NOW(),0);

-- --- pro profiles ----------------------------------------------------------
INSERT INTO pro_profiles
 (id, market_id, user_id, slug, business_name, headline, bio, hourly_rate_cents, years_experience,
  base_zip, license_number, license_state, license_verified_at, insurance_carrier, insurance_verified_at,
  background_checked_at, rating_avg, rating_count, jobs_completed, response_minutes, status, published_at) VALUES
 (1,1,10,'ray-okafor','Okafor Plumbing','Master Plumber','Licensed master plumber (TX M-41182). I do the diagnosis before the quote, in writing, and I don''t leave a job without pressure-testing it.',9500,18,'78704','M-41182','TX',NOW(),'Travelers',NOW(),NOW(),4.90,187,412,12,'active',NOW()),
 (2,1,11,'teresa-vance','Vance Woodwork','Finish Carpenter','Custom built-ins and trim carpentry. Twelve years, mostly referral work in Tarrytown and Hyde Park. I show up with a shop drawing, not a guess.',7800,12,'78703','','TX',NULL,'State Farm',NOW(),NOW(),5.00,96,238,40,'active',NOW()),
 (3,1,12,'dmitri-sokolov','','Handyman — General','The everything-list guy. If your honey-do list has nine unrelated items on it, that''s a normal Tuesday for me. Two-hour minimum.',6200,9,'78745','','TX',NULL,'Hiscox',NOW(),NOW(),4.80,143,520,25,'active',NOW()),
 (4,1,13,'alma-reyes','Reyes Painting','Painter & Drywall','Interior repaints and texture matching. I match orange peel and knockdown so you can''t find the patch afterward.',5500,15,'78702','','TX',NULL,'Hiscox',NOW(),NULL,4.90,74,301,60,'active',NOW()),
 (5,1,14,'jonah-pike','Pike Electric','Electrician','Journeyman electrician. Panel upgrades and EV charger installs are most of my week — I pull the permit, you don''t chase the city.',8800,7,'78751','J-88204','TX',NOW(),'Travelers',NOW(),NOW(),4.70,58,164,120,'active',NOW()),
 (6,1,15,'beatrice-lund','','Appliance Repair','Twenty-two years on appliances. I carry common parts on the truck, so most repairs are one visit, not three.',7000,22,'78723','','TX',NULL,'Nationwide',NOW(),NULL,4.60,41,890,180,'active',NOW()),
 (7,2,16,'curtis-nwosu','Nwosu Outdoor','Deck & Fence Builder','Cedar fences and deck rebuilds across Williamson County. Post holes dug to depth, concrete every post, no exceptions.',7200,11,'78665','','TX',NULL,'State Farm',NOW(),NOW(),4.90,62,148,30,'active',NOW()),
 (8,2,17,'priya-raman','','Handyman — General','Small-job specialist. Door adjustments, shelving, smart locks and thermostats. Flat-rate on anything under an hour.',5800,6,'78681','','TX',NULL,'',NULL,NOW(),4.80,37,203,45,'active',NOW()),
 (9,3,18,'hal-brenner','Brenner HVAC','HVAC Technician','EPA-certified HVAC tech covering Hays County. Summer emergency slots held open daily until 6pm.',9200,14,'78666','TACLB-52119','TX',NOW(),'Travelers',NOW(),NOW(),4.70,24,311,60,'active',NOW());

INSERT INTO pro_trades (pro_id, trade_id, is_primary) VALUES
 (1,1,1),(2,3,1),(3,8,1),(3,4,0),(4,4,1),(5,2,1),(6,5,1),(7,7,1),(7,3,0),(8,8,1),(9,6,1);

INSERT INTO pro_service_areas (pro_id, market_id) VALUES
 (1,1),(2,1),(3,1),(4,1),(5,1),(6,1),(7,2),(8,2),(9,3),
 (1,2),   -- Ray also covers Round Rock: proves the many-to-many path
 (7,1);   -- Curtis picks up Austin work too

INSERT INTO pro_skills (pro_id, label, sort_order) VALUES
 (1,'Repipe',1),(1,'Water heaters',2),(1,'Slab leaks',3),(1,'Emergency',4),
 (2,'Built-ins',1),(2,'Trim & crown',2),(2,'Cabinet refacing',3),(2,'Stairs',4),
 (3,'TV mounting',1),(3,'Drywall patch',2),(3,'Faucets',3),(3,'Furniture',4),
 (4,'Interior repaint',1),(4,'Texture match',2),(4,'Popcorn removal',3),(4,'Cabinets',4),
 (5,'Panel upgrades',1),(5,'EV chargers',2),(5,'Ceiling fans',3),(5,'Recessed lighting',4),
 (6,'Washers',1),(6,'Refrigeration',2),(6,'Ovens',3),(6,'Dishwashers',4),
 (7,'Cedar fences',1),(7,'Deck rebuilds',2),(7,'Pergolas',3),(7,'Gates',4),
 (8,'Shelving',1),(8,'Door hardware',2),(8,'Caulking',3),(8,'Smart home',4),
 (9,'AC tune-ups',1),(9,'Condenser repair',2),(9,'Duct sealing',3),(9,'Mini-splits',4);

-- --- pro photos ------------------------------------------------------------
INSERT INTO pro_photos (id, pro_id, market_id, path, caption, status, sort_order) VALUES
 (1,1,1,'uploads/pro/1/repipe-78704.jpg','Whole-house repipe, 78704','approved',1),
 (2,1,1,'uploads/pro/1/water-heater.jpg','Water heater swap','approved',2),
 (3,1,1,'uploads/pro/1/slab-leak.jpg','Slab leak, located','approved',3),
 (4,2,1,'uploads/pro/2/builtins.jpg','Living room built-ins','approved',1),
 (5,2,1,'uploads/pro/2/stairs.jpg','Stair rebuild, white oak','approved',2),
 (6,7,2,'uploads/pro/7/cedar-fence.jpg','92ft cedar privacy fence','approved',1);
UPDATE pro_profiles SET hero_photo_id = 1 WHERE id = 1;
UPDATE pro_profiles SET hero_photo_id = 4 WHERE id = 2;
UPDATE pro_profiles SET hero_photo_id = 6 WHERE id = 7;

-- --- jobs ------------------------------------------------------------------
-- j9 is deliberately left in pending_payment: it must never appear on the
-- public board, and it is what the abandoned-checkout report counts.
INSERT INTO jobs (id, market_id, user_id, trade_id, reference, title, description, zip, urgency,
                  budget_min_cents, budget_max_cents, status, quote_count, published_at, expires_at) VALUES
 (1,1,30,1,'ATX-4K2P9M','Kitchen faucet leaking at the base','Pull-down faucet drips from the base whenever the sprayer is used. Cabinet below is getting damp. Happy to buy the replacement faucet myself if that is cheaper.','78704','this_week',12000,25000,'active',7,NOW() - INTERVAL 2 HOUR, NOW() + INTERVAL 30 DAY),
 (2,1,31,8,'ATX-7Q1B4T','Mount 65" TV and hide cables in drywall','Standard drywall, no brick. Want the cables run inside the wall to an outlet behind the console. I have the mount already.','78745','flexible',15000,30000,'active',12,NOW() - INTERVAL 5 HOUR, NOW() + INTERVAL 30 DAY),
 (3,1,32,4,'ATX-2M8X5R','Repaint two bedrooms, walls and trim','Two 12x13 bedrooms, 9ft ceilings. Walls plus baseboards and door casings. Paint not purchased yet, would like a recommendation.','78731','this_month',80000,140000,'active',5,NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 30 DAY),
 (4,1,33,3,'ATX-9F3H6W','Replace rotted deck boards (approx 40 sq ft)','Back corner of the deck has about eight soft boards. Frame underneath looks solid. Cedar preferred to match, pressure-treated fine if cheaper.','78702','this_month',40000,90000,'active',9,NOW() - INTERVAL 2 DAY, NOW() + INTERVAL 30 DAY),
 (5,1,34,2,'ATX-5T7J2D','Install EV charger in attached garage','Level 2, 240V. Panel is in the garage about 15 feet from where the charger goes. Need the permit handled.','78703','this_month',60000,120000,'active',4,NOW() - INTERVAL 3 DAY, NOW() + INTERVAL 30 DAY),
 (6,2,35,7,'RRK-6C4N8V','Build 8ft cedar privacy fence, back line only','About 92 linear feet along the back property line. Old fence needs hauling off. HOA requires cap and trim.','78665','this_month',200000,350000,'active',3,NOW() - INTERVAL 6 HOUR, NOW() + INTERVAL 30 DAY),
 (7,2,36,5,'RRK-3B9L1K','Dishwasher will not drain, standing water','Bosch, about six years old. Ran the disposal, cleaned the filter, still holding water at the bottom after every cycle.','78681','asap',9000,20000,'active',2,NOW() - INTERVAL 1 HOUR, NOW() + INTERVAL 30 DAY),
 (8,3,37,6,'SMT-8V2R7P','AC blowing warm in the afternoons','Cools fine in the morning, gives up around 3pm when it gets hot. Unit is nine years old, filter is new.','78666','asap',15000,50000,'active',1,NOW() - INTERVAL 4 HOUR, NOW() + INTERVAL 30 DAY),
 (9,1,31,8,'ATX-1D6G3Z','Hang 14 pictures and two mirrors','Moving in. Everything is stacked against the wall in the living room. Mirrors are heavy and need anchors.','78704','flexible',10000,18000,'pending_payment',0,NULL,NULL);

-- --- quotes ----------------------------------------------------------------
INSERT INTO quotes (market_id, job_id, pro_id, amount_cents, amount_type, amount_max_cents, message, status, created_at) VALUES
 (1,1,1,18500,'fixed',NULL,'Sounds like the sprayer hose fitting rather than the faucet body. I can be out Thursday morning, and if it turns out the whole unit needs replacing I will show you why before I touch it.','viewed',NOW() - INTERVAL 90 MINUTE),
 (1,1,3,16000,'fixed',NULL,'Can do Wednesday afternoon. Price includes the trip and up to two hours.','sent',NOW() - INTERVAL 40 MINUTE),
 (1,4,2,68000,'range',82000,'Cedar to match, and I will sister the joists underneath if any are soft once the boards are up.','accepted',NOW() - INTERVAL 1 DAY),
 (2,6,7,285000,'fixed',NULL,'92ft of 8ft cedar with cap and trim, old fence hauled off. Two days on site.','sent',NOW() - INTERVAL 3 HOUR);

-- --- reviews ---------------------------------------------------------------
INSERT INTO reviews (market_id, pro_id, job_id, author_user_id, rating, body, job_value_cents, status, created_at) VALUES
 (1,1,1,30,5,'He found the slab leak in twenty minutes after another company spent a day and quoted me a re-pipe. The bill was a third of what I expected.',64000,'published',NOW() - INTERVAL 21 DAY),
 (1,2,4,33,5,'Teresa sent a drawing before she cut a single board. The built-ins look like they came with the house.',390000,'published',NOW() - INTERVAL 40 DAY),
 (1,3,2,31,5,'Posted my list at 9pm for ten dollars. Four quotes by lunch, and I hired the one who actually read the list.',41500,'published',NOW() - INTERVAL 12 DAY);

-- --- payments --------------------------------------------------------------
-- Eight paid job posts, one refunded under the 72-hour no-quote promise, plus
-- the monthly ad subscriptions. San Marcos shows a $0 listing (free launch).
INSERT INTO payments (market_id, user_id, kind, job_id, amount_cents, stripe_payment_intent, status, paid_at) VALUES
 (1,30,'job_listing',1,1000,'pi_demo_0001','succeeded',NOW() - INTERVAL 2 HOUR),
 (1,31,'job_listing',2,1000,'pi_demo_0002','succeeded',NOW() - INTERVAL 5 HOUR),
 (1,32,'job_listing',3,1000,'pi_demo_0003','succeeded',NOW() - INTERVAL 1 DAY),
 (1,33,'job_listing',4,1000,'pi_demo_0004','succeeded',NOW() - INTERVAL 2 DAY),
 (1,34,'job_listing',5,1000,'pi_demo_0005','succeeded',NOW() - INTERVAL 3 DAY),
 (2,35,'job_listing',6,1000,'pi_demo_0006','succeeded',NOW() - INTERVAL 6 HOUR),
 (2,36,'job_listing',7,1000,'pi_demo_0007','succeeded',NOW() - INTERVAL 1 HOUR);
INSERT INTO payments (market_id, user_id, kind, job_id, amount_cents, stripe_payment_intent, status, paid_at, refunded_cents, refunded_at, refund_reason) VALUES
 (1,31,'job_listing',NULL,1000,'pi_demo_0008','refunded',NOW() - INTERVAL 9 DAY,1000,NOW() - INTERVAL 6 DAY,'No quotes within 72 hours — auto-refund');

-- --- subscriptions + ad placements ----------------------------------------
INSERT INTO subscriptions (id, market_id, pro_id, plan, price_cents, stripe_subscription_id, status, current_period_start, current_period_end) VALUES
 (1,1,1,'spotlight',14900,'sub_demo_0001','active',NOW() - INTERVAL 12 DAY, NOW() + INTERVAL 18 DAY),
 (2,1,2,'spotlight',14900,'sub_demo_0002','active',NOW() - INTERVAL 5 DAY,  NOW() + INTERVAL 25 DAY),
 (3,1,3,'boost',     4900,'sub_demo_0003','active',NOW() - INTERVAL 20 DAY, NOW() + INTERVAL 10 DAY),
 (4,2,7,'boost',     4900,'sub_demo_0004','active',NOW() - INTERVAL 2 DAY,  NOW() + INTERVAL 28 DAY),
 (5,1,5,'boost',     4900,'sub_demo_0005','past_due',NOW() - INTERVAL 33 DAY, NOW() - INTERVAL 3 DAY);

INSERT INTO payments (market_id, user_id, kind, subscription_id, amount_cents, stripe_payment_intent, status, paid_at) VALUES
 (1,10,'subscription',1,14900,'pi_demo_1001','succeeded',NOW() - INTERVAL 12 DAY),
 (1,11,'subscription',2,14900,'pi_demo_1002','succeeded',NOW() - INTERVAL 5 DAY),
 (1,12,'subscription',3, 4900,'pi_demo_1003','succeeded',NOW() - INTERVAL 20 DAY),
 (2,16,'subscription',4, 4900,'pi_demo_1004','succeeded',NOW() - INTERVAL 2 DAY);

-- Creatives: Ray overrides all three fields and runs an A/B test. Teresa
-- overrides nothing — variant A with every column NULL is a complete, live ad
-- assembled entirely from her profile, which is the point of the design.
INSERT INTO ad_creatives (id, market_id, pro_id, variant, headline, offer_line, cta_label, hero_photo_id, status, reviewed_by, reviewed_at) VALUES
 (1,1,1,'A','Master Plumber, 18 years','Diagnosis before quote, in writing, every time.','See Ray''s work',1,'approved',2,NOW() - INTERVAL 12 DAY),
 (2,1,1,'B','Slab leaks found same day','Licensed master plumber. Pressure-tested before I leave.','Get a quote',3,'approved',2,NOW() - INTERVAL 4 DAY),
 (3,1,2,'A',NULL,NULL,NULL,NULL,'approved',2,NOW() - INTERVAL 5 DAY),
 (4,1,3,'A','Your whole honey-do list, one visit',NULL,NULL,NULL,'approved',2,NOW() - INTERVAL 20 DAY),
 (5,2,7,'A',NULL,'Cedar fences and deck rebuilds. Every post concreted.',NULL,6,'pending_review',NULL,NULL);

INSERT INTO ad_placements (id, market_id, pro_id, subscription_id, slot, trade_id, position, status) VALUES
 (1,1,1,1,'directory_top',NULL,1,'active'),
 (2,1,1,1,'home_featured',NULL,1,'active'),
 (3,1,1,1,'category_top',   1,1,'active'),
 (4,1,1,1,'jobs_native',  NULL,1,'active'),
 (5,1,2,2,'directory_top',NULL,2,'active'),
 (6,1,2,2,'home_featured',NULL,2,'active'),
 (7,1,2,2,'category_top',   3,1,'active'),
 (8,1,3,3,'directory_top',NULL,3,'active'),
 (9,1,3,3,'category_top',   8,1,'active'),
 (10,2,7,4,'directory_top',NULL,1,'active'),
 (11,1,5,5,'directory_top',NULL,4,'paused');   -- past_due subscription pauses the slot

-- 14 days of ad stats for the pro dashboard and the A/B readout.
INSERT INTO ad_stats_daily (market_id, pro_id, placement_id, creative_id, stat_date, impressions, clicks, quotes_sent, jobs_won)
SELECT 1, 1, 1, c.id, d.dt,
       FLOOR(120 + RAND(d.n * c.id) * 90),
       FLOOR(6  + RAND(d.n * c.id + 7) * 12),
       FLOOR(RAND(d.n * c.id + 3) * 3),
       FLOOR(RAND(d.n * c.id + 5) * 1.4)
FROM (SELECT 1 AS id UNION SELECT 2) c
CROSS JOIN (
  SELECT 0 n, CURDATE() dt UNION SELECT 1, CURDATE()-INTERVAL 1 DAY UNION SELECT 2, CURDATE()-INTERVAL 2 DAY
  UNION SELECT 3, CURDATE()-INTERVAL 3 DAY UNION SELECT 4, CURDATE()-INTERVAL 4 DAY
  UNION SELECT 5, CURDATE()-INTERVAL 5 DAY UNION SELECT 6, CURDATE()-INTERVAL 6 DAY
  UNION SELECT 7, CURDATE()-INTERVAL 7 DAY UNION SELECT 8, CURDATE()-INTERVAL 8 DAY
  UNION SELECT 9, CURDATE()-INTERVAL 9 DAY UNION SELECT 10, CURDATE()-INTERVAL 10 DAY
  UNION SELECT 11, CURDATE()-INTERVAL 11 DAY UNION SELECT 12, CURDATE()-INTERVAL 12 DAY
  UNION SELECT 13, CURDATE()-INTERVAL 13 DAY
) d;

-- --- moderation queue ------------------------------------------------------
INSERT INTO moderation_items (market_id, subject_type, subject_id, source, reason, reported_by, status, created_at) VALUES
 (1,'job',9,'auto','Possible duplicate post — third from this user today',NULL,'open',NOW() - INTERVAL 11 MINUTE),
 (2,'pro_profile',8,'auto','Claims electrical work with no licence on file',NULL,'open',NOW() - INTERVAL 1 HOUR),
 (1,'ad_creative',2,'admin','Headline needs a licence-claim check',1,'open',NOW() - INTERVAL 3 HOUR);
