-- ---------------------------------------------------------------------------
-- Fix Listed — demo seed data (Northwest Illinois)
--
-- Safe to re-run: truncates the tenant tables first.
-- Every demo account uses the password:  demo-password
-- Delete these before the site goes public.
--
--   mysql -u USER -p DBNAME < database/seed.sql
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE ad_stats_daily;  TRUNCATE TABLE ad_events;    TRUNCATE TABLE ad_placements;
TRUNCATE TABLE ad_creatives;    TRUNCATE TABLE subscriptions; TRUNCATE TABLE payments;
TRUNCATE TABLE reviews;         TRUNCATE TABLE messages;     TRUNCATE TABLE quotes;
TRUNCATE TABLE job_photos;      TRUNCATE TABLE jobs;         TRUNCATE TABLE pro_photos;
TRUNCATE TABLE pro_county_areas; TRUNCATE TABLE pro_skills;  TRUNCATE TABLE pro_trades;
TRUNCATE TABLE pro_profiles;    TRUNCATE TABLE moderation_items; TRUNCATE TABLE audit_log;
TRUNCATE TABLE notifications;   TRUNCATE TABLE users;        TRUNCATE TABLE trades;
TRUNCATE TABLE zip_counties;    TRUNCATE TABLE cities;       TRUNCATE TABLE market_counties;
TRUNCATE TABLE counties;        TRUNCATE TABLE markets;
SET FOREIGN_KEY_CHECKS = 1;

-- --- markets ---------------------------------------------------------------
-- Market 1 is live. The other two are staged: real counties assigned, no
-- listings yet — the expansion path, visible in the superadmin panel.
INSERT INTO markets (id, slug, name, code, city, state, status, listing_fee_cents, boost_slots, spotlight_slots, launched_at) VALUES
 (1,'northwest-illinois','Northwest Illinois','NWI','Rockford',   'IL','live',  1000, 12, 3, '2026-09-01 09:00:00'),
 (2,'quad-cities',       'Quad Cities',       'QCA','Rock Island','IL','staged',   0,  8, 2, NULL),
 (3,'southern-wisconsin','Southern Wisconsin','SWI','Janesville', 'WI','staged',   0,  8, 2, NULL);

-- --- counties --------------------------------------------------------------
-- FIPS codes verified against the Census county file. They are the key, not
-- the names: Boone exists in both Illinois and Iowa, and Winnebago in three
-- states, so matching on name alone eventually puts a Freeport plumber in
-- Iowa.
INSERT INTO counties (id, fips, name, short_name, slug, state) VALUES
 -- Northwest Illinois
 (1,'17177','Stephenson County','Stephenson','stephenson-il','IL'),
 (2,'17201','Winnebago County', 'Winnebago', 'winnebago-il', 'IL'),
 (3,'17141','Ogle County',      'Ogle',      'ogle-il',      'IL'),
 (4,'17007','Boone County',     'Boone',     'boone-il',     'IL'),
 (5,'17015','Carroll County',   'Carroll',   'carroll-il',   'IL'),
 (6,'17085','Jo Daviess County','Jo Daviess','jo-daviess-il','IL'),
 -- Quad Cities (staged)
 (7,'17161','Rock Island County','Rock Island','rock-island-il','IL'),
 (8,'17073','Henry County',      'Henry',      'henry-il',      'IL'),
 (9,'17131','Mercer County',     'Mercer',     'mercer-il',     'IL'),
 (10,'17195','Whiteside County', 'Whiteside',  'whiteside-il',  'IL'),
 -- Southern Wisconsin (staged)
 (11,'55105','Rock County',     'Rock',     'rock-wi',     'WI'),
 (12,'55045','Green County',    'Green',    'green-wi',    'WI'),
 (13,'55065','Lafayette County','Lafayette','lafayette-wi','WI');

INSERT INTO market_counties (market_id, county_id) VALUES
 (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),
 (2,7),(2,8),(2,9),(2,10),
 (3,11),(3,12),(3,13);

-- --- cities ----------------------------------------------------------------
-- has_page = 1 gets an indexed landing page at /northwest-illinois/{slug}.
-- The villages are selectable when posting a job but have no page of their
-- own: forty near-empty pages read as a content farm to a search engine.
INSERT INTO cities (county_id, market_id, name, slug, state, has_page, sort_order) VALUES
 -- Winnebago (Rockford)
 (2,1,'Rockford','rockford','IL',1,10),
 (2,1,'Loves Park','loves-park','IL',1,20),
 (2,1,'Machesney Park','machesney-park','IL',1,30),
 (2,1,'Roscoe','roscoe','IL',1,40),
 (2,1,'Rockton','rockton','IL',0,0),
 (2,1,'South Beloit','south-beloit','IL',0,0),
 (2,1,'Cherry Valley','cherry-valley','IL',0,0),
 (2,1,'Winnebago','winnebago','IL',0,0),
 (2,1,'Pecatonica','pecatonica','IL',0,0),
 (2,1,'Durand','durand','IL',0,0),
 -- Stephenson (Freeport)
 (1,1,'Freeport','freeport','IL',1,15),
 (1,1,'Lena','lena','IL',0,0),
 (1,1,'Pearl City','pearl-city','IL',0,0),
 (1,1,'Orangeville','orangeville','IL',0,0),
 (1,1,'Dakota','dakota','IL',0,0),
 (1,1,'Cedarville','cedarville','IL',0,0),
 (1,1,'Davis','davis','IL',0,0),
 (1,1,'Winslow','winslow','IL',0,0),
 (1,1,'German Valley','german-valley','IL',0,0),
 -- Ogle
 (3,1,'Rochelle','rochelle','IL',1,50),
 (3,1,'Byron','byron','IL',1,60),
 (3,1,'Oregon','oregon','IL',1,70),
 (3,1,'Mount Morris','mount-morris','IL',0,0),
 (3,1,'Polo','polo','IL',0,0),
 (3,1,'Forreston','forreston','IL',0,0),
 (3,1,'Stillman Valley','stillman-valley','IL',0,0),
 (3,1,'Davis Junction','davis-junction','IL',0,0),
 (3,1,'Leaf River','leaf-river','IL',0,0),
 -- Boone
 (4,1,'Belvidere','belvidere','IL',1,45),
 (4,1,'Poplar Grove','poplar-grove','IL',0,0),
 (4,1,'Capron','capron','IL',0,0),
 (4,1,'Caledonia','caledonia','IL',0,0),
 -- Carroll
 (5,1,'Savanna','savanna','IL',1,80),
 (5,1,'Mount Carroll','mount-carroll','IL',0,0),
 (5,1,'Lanark','lanark','IL',0,0),
 (5,1,'Thomson','thomson','IL',0,0),
 (5,1,'Milledgeville','milledgeville','IL',0,0),
 (5,1,'Chadwick','chadwick','IL',0,0),
 -- Jo Daviess
 (6,1,'Galena','galena','IL',1,75),
 (6,1,'East Dubuque','east-dubuque','IL',0,0),
 (6,1,'Elizabeth','elizabeth','IL',0,0),
 (6,1,'Stockton','stockton','IL',0,0),
 (6,1,'Warren','warren','IL',0,0),
 (6,1,'Hanover','hanover','IL',0,0),
 (6,1,'Scales Mound','scales-mound','IL',0,0),
 (6,1,'Apple River','apple-river','IL',0,0);

-- zip_counties is left EMPTY on purpose. It must be imported from the Census
-- ZCTA-to-county relationship file, never hand-typed: a wrong ZIP silently
-- routes a paid job post into the wrong market. Until it is loaded, homeowners
-- pick their city from the list above, which needs no ZIP data at all.

-- --- trades ----------------------------------------------------------------
-- Roofing & Gutters and Landscaping & Snow matter here in a way they never did
-- in a southern market: ice dams, gutter failure and snow clearing are the
-- seasonal spikes in northern Illinois.
INSERT INTO trades (id, slug, name, icon, sort_order) VALUES
 (1,'plumbing','Plumbing','pipe',10),
 (2,'electrical','Electrical','bolt',20),
 (3,'carpentry','Carpentry','saw',30),
 (4,'drywall-paint','Drywall & Paint','roller',40),
 (5,'appliance','Appliance Repair','appliance',50),
 (6,'hvac','Heating & Cooling','fan',60),
 (7,'roofing-gutters','Roofing & Gutters','roof',70),
 (8,'fencing-decks','Fencing & Decks','fence',80),
 (9,'landscaping-snow','Landscaping & Snow','leaf',90),
 (10,'odd-jobs','Odd Jobs','tools',100);

-- --- users -----------------------------------------------------------------
-- id 1 is the superadmin: market_id NULL, the only row allowed to drop tenant scope.
SET @pw := '$2y$12$EqamHUG6bee9W5b.i8lSi.T4eYxJ8T6VjUzosZShwAd.CiO2AP8kK';  -- 'demo-password'

INSERT INTO users (id, market_id, role, email, password_hash, first_name, last_name, phone, email_verified_at, sms_opt_in) VALUES
 (1, NULL,'superadmin',  'owner@fixlisted.com',  @pw,'Lee','Dixon','',                NOW(),0),
 (2, 1,   'market_admin','dana@fixlisted.com',   @pw,'Dana','Whitfield','',           NOW(),0),
 -- pros
 (10,1,'pro','marcus@ojoplumbing.com',      @pw,'Marcus','Ojo','(815) 555-0112',      NOW(),1),
 (11,1,'pro','karin@halvorsenwood.com',     @pw,'Karin','Halvorsen','(815) 555-0134', NOW(),1),
 (12,1,'pro','dwayne.pryor@example.com',    @pw,'Dwayne','Pryor','(815) 555-0155',    NOW(),1),
 (13,1,'pro','alma@reyespainting.com',      @pw,'Alma','Reyes','(815) 555-0167',      NOW(),0),
 (14,1,'pro','nolan@pikeelectric.com',      @pw,'Nolan','Pike','(815) 555-0178',      NOW(),1),
 (15,1,'pro','beatrice.lund@example.com',   @pw,'Beatrice','Lund','(815) 555-0189',   NOW(),0),
 (16,1,'pro','curtis@nwosuoutdoor.com',     @pw,'Curtis','Nwosu','(815) 555-0191',    NOW(),1),
 (17,1,'pro','priya.raman@example.com',     @pw,'Priya','Raman','(815) 555-0203',     NOW(),1),
 (18,1,'pro','hal@brennerheating.com',      @pw,'Hal','Brenner','(815) 555-0214',     NOW(),1),
 (19,1,'pro','wes@kuipersroofing.com',      @pw,'Wes','Kuipers','(815) 555-0225',     NOW(),1),
 -- homeowners
 (30,1,'homeowner','marissa.k@example.com', @pw,'Marissa','Kessler','(815) 555-0148', NOW(),1),
 (31,1,'homeowner','ben.t@example.com',     @pw,'Ben','Tran','(815) 555-0159',        NOW(),1),
 (32,1,'homeowner','ashby@example.com',     @pw,'Claire','Ashby','',                  NOW(),0),
 (33,1,'homeowner','grady.p@example.com',   @pw,'Grady','Poole','',                   NOW(),0),
 (34,1,'homeowner','simone.a@example.com',  @pw,'Simone','Adeyemi','',                NOW(),0),
 (35,1,'homeowner','devon.l@example.com',   @pw,'Devon','Little','',                  NOW(),0),
 (36,1,'homeowner','nora.f@example.com',    @pw,'Nora','Fielding','',                 NOW(),0),
 (37,1,'homeowner','rick.m@example.com',    @pw,'Rick','Mendez','',                   NOW(),0);

-- --- pro profiles ----------------------------------------------------------
-- City references go through a slug lookup rather than a guessed id, so the
-- seed stays correct if the cities block above is ever reordered.
INSERT INTO pro_profiles
 (id, market_id, user_id, slug, business_name, headline, bio, hourly_rate_cents, years_experience,
  home_county_id, home_city_id, license_number, license_state, license_verified_at,
  insurance_carrier, insurance_verified_at, background_checked_at,
  rating_avg, rating_count, jobs_completed, response_minutes, status, published_at) VALUES
 (1,1,10,'marcus-ojo','Ojo Plumbing','Master Plumber',
  'Licensed master plumber (IL 058-123456). Sump pumps, frozen lines and water heaters are most of my winter. I diagnose before I quote, in writing, and I do not leave a job without pressure-testing it.',
  9500,18,2,(SELECT id FROM cities WHERE market_id=1 AND slug='rockford'),'058-123456','IL',NOW(),'Travelers',NOW(),NOW(),4.90,187,412,12,'active',NOW()),
 (2,1,11,'karin-halvorsen','Halvorsen Woodwork','Finish Carpenter',
  'Custom built-ins and trim carpentry. Twelve years, mostly referral work through Freeport and Galena. I show up with a shop drawing, not a guess.',
  7800,12,1,(SELECT id FROM cities WHERE market_id=1 AND slug='freeport'),'','IL',NULL,'State Farm',NOW(),NOW(),5.00,96,238,40,'active',NOW()),
 (3,1,12,'dwayne-pryor','','Handyman — General',
  'The everything-list guy. If your honey-do list has nine unrelated items on it, that is a normal Tuesday. Two-hour minimum.',
  6200,9,2,(SELECT id FROM cities WHERE market_id=1 AND slug='loves-park'),'','IL',NULL,'Hiscox',NOW(),NOW(),4.80,143,520,25,'active',NOW()),
 (4,1,13,'alma-reyes','Reyes Painting','Painter & Drywall',
  'Interior repaints and texture matching. I match orange peel and knockdown so you cannot find the patch afterward.',
  5500,15,2,(SELECT id FROM cities WHERE market_id=1 AND slug='rockford'),'','IL',NULL,'Hiscox',NOW(),NULL,4.90,74,301,60,'active',NOW()),
 (5,1,14,'nolan-pike','Pike Electric','Electrician',
  'Journeyman electrician. Panel upgrades, standby generator hookups and EV chargers are most of my week — I pull the permit, you do not chase the county.',
  8800,7,4,(SELECT id FROM cities WHERE market_id=1 AND slug='belvidere'),'196-011204','IL',NOW(),'Travelers',NOW(),NOW(),4.70,58,164,120,'active',NOW()),
 (6,1,15,'beatrice-lund','','Appliance Repair',
  'Twenty-two years on appliances. I carry common parts on the truck, so most repairs are one visit, not three.',
  7000,22,2,(SELECT id FROM cities WHERE market_id=1 AND slug='machesney-park'),'','IL',NULL,'Nationwide',NOW(),NULL,4.60,41,890,180,'active',NOW()),
 (7,1,16,'curtis-nwosu','Nwosu Outdoor','Deck & Fence Builder',
  'Cedar fences and deck rebuilds across Ogle and Winnebago. Post holes dug below the frost line, concrete every post, no exceptions.',
  7200,11,3,(SELECT id FROM cities WHERE market_id=1 AND slug='byron'),'','IL',NULL,'State Farm',NOW(),NOW(),4.90,62,148,30,'active',NOW()),
 (8,1,17,'priya-raman','','Handyman — General',
  'Small-job specialist. Door adjustments, shelving, smart locks and thermostats. Flat rate on anything under an hour.',
  5800,6,3,(SELECT id FROM cities WHERE market_id=1 AND slug='rochelle'),'','IL',NULL,'',NULL,NOW(),4.80,37,203,45,'active',NOW()),
 (9,1,18,'hal-brenner','Brenner Heating & Cooling','Heating & Cooling Technician',
  'EPA-certified, covering Jo Daviess and Carroll. Furnaces and boilers through the winter, and I hold emergency slots open every day from November.',
  9200,14,6,(SELECT id FROM cities WHERE market_id=1 AND slug='galena'),'','IL',NULL,'Travelers',NOW(),NOW(),4.70,24,311,60,'active',NOW()),
 (10,1,19,'wes-kuipers','Kuipers Roofing & Gutter','Roofing & Gutters',
  'Ice dams, gutter runs and tear-offs. If your gutters came down in February you are not the only one — that is half my spring.',
  8000,16,1,(SELECT id FROM cities WHERE market_id=1 AND slug='freeport'),'','IL',NULL,'Nationwide',NOW(),NOW(),4.80,53,276,90,'active',NOW());

INSERT INTO pro_trades (pro_id, trade_id, is_primary) VALUES
 (1,1,1),(2,3,1),(3,10,1),(3,4,0),(4,4,1),(5,2,1),(6,5,1),(7,8,1),(7,3,0),(8,10,1),(9,6,1),(10,7,1),(10,9,0);

-- Where each pro actually works. This is what the directory reads: Hal covers
-- the western counties and will not drive to Belvidere, and the listing
-- reflects that rather than pretending everyone serves everywhere.
INSERT INTO pro_county_areas (pro_id, county_id, market_id) VALUES
 (1,2,1),(1,4,1),(1,3,1),(1,1,1),
 (2,1,1),(2,6,1),(2,5,1),(2,2,1),
 (3,2,1),(3,4,1),
 (4,2,1),(4,3,1),
 (5,4,1),(5,2,1),
 (6,2,1),(6,4,1),(6,1,1),
 (7,3,1),(7,2,1),(7,5,1),
 (8,3,1),
 (9,6,1),(9,5,1),(9,1,1),
 (10,1,1),(10,6,1),(10,5,1),(10,2,1);

INSERT INTO pro_skills (pro_id, label, sort_order) VALUES
 (1,'Sump pumps',1),(1,'Frozen pipes',2),(1,'Water heaters',3),(1,'Sewer scoping',4),
 (2,'Built-ins',1),(2,'Trim & crown',2),(2,'Stair rebuilds',3),(2,'Cabinet refacing',4),
 (3,'TV mounting',1),(3,'Drywall patch',2),(3,'Faucets',3),(3,'Furniture',4),
 (4,'Interior repaint',1),(4,'Texture match',2),(4,'Popcorn removal',3),(4,'Cabinets',4),
 (5,'Panel upgrades',1),(5,'Generator hookups',2),(5,'EV chargers',3),(5,'Recessed lighting',4),
 (6,'Washers',1),(6,'Refrigeration',2),(6,'Ovens',3),(6,'Dishwashers',4),
 (7,'Cedar fences',1),(7,'Deck rebuilds',2),(7,'Pergolas',3),(7,'Gates',4),
 (8,'Shelving',1),(8,'Door hardware',2),(8,'Caulking',3),(8,'Smart home',4),
 (9,'Furnaces',1),(9,'Boilers',2),(9,'AC tune-ups',3),(9,'Duct sealing',4),
 (10,'Ice dams',1),(10,'Gutter guards',2),(10,'Tear-offs',3),(10,'Soffit & fascia',4);

INSERT INTO pro_photos (id, pro_id, market_id, path, caption, status, sort_order) VALUES
 (1,1,1,'uploads/pro/1/sump-install.jpg','Sump pit and backup pump, Rockford','approved',1),
 (2,1,1,'uploads/pro/1/water-heater.jpg','Water heater swap','approved',2),
 (3,1,1,'uploads/pro/1/frozen-line.jpg','Split line in a crawlspace','approved',3),
 (4,2,1,'uploads/pro/2/builtins.jpg','Living room built-ins, Freeport','approved',1),
 (5,2,1,'uploads/pro/2/stairs.jpg','Stair rebuild, white oak','approved',2),
 (6,7,1,'uploads/pro/7/cedar-fence.jpg','92ft cedar privacy fence, Byron','approved',1),
 (7,10,1,'uploads/pro/10/ice-dam.jpg','Ice dam damage, north-facing run','approved',1);
UPDATE pro_profiles SET hero_photo_id=1 WHERE id=1;
UPDATE pro_profiles SET hero_photo_id=4 WHERE id=2;
UPDATE pro_profiles SET hero_photo_id=6 WHERE id=7;
UPDATE pro_profiles SET hero_photo_id=7 WHERE id=10;

-- --- jobs ------------------------------------------------------------------
-- NWI-1D6G3Z is deliberately left in pending_payment: it must never appear on
-- the public board, and it is what the abandoned-checkout report counts.
INSERT INTO jobs (id, market_id, user_id, trade_id, county_id, city_id, reference, title, description,
                  zip, urgency, budget_min_cents, budget_max_cents, status, quote_count, published_at, expires_at) VALUES
 (1,1,30,1,2,(SELECT id FROM cities WHERE market_id=1 AND slug='rockford'),'NWI-4K2P9M',
  'Sump pump failed, water in the basement',
  'Woke up to two inches at the bottom of the stairs. Pump hums but does not move water. Need it dealt with today, and I would like a battery backup quoted at the same time.',
  '61103','asap',30000,90000,'active',7,NOW() - INTERVAL 2 HOUR, NOW() + INTERVAL 30 DAY),
 (2,1,31,6,4,(SELECT id FROM cities WHERE market_id=1 AND slug='belvidere'),'NWI-7Q1B4T',
  'Furnace short-cycling, kicks off after two minutes',
  'Runs for about two minutes, shuts down, starts again ten minutes later. Filter is new. House is not holding temperature overnight.',
  '61008','asap',15000,60000,'active',5,NOW() - INTERVAL 5 HOUR, NOW() + INTERVAL 30 DAY),
 (3,1,32,7,1,(SELECT id FROM cities WHERE market_id=1 AND slug='freeport'),'NWI-2M8X5R',
  'Ice dam pulled down a gutter run',
  'About 30 feet along the north side came away with the thaw, taking some fascia with it. Want it replaced properly before next winter, not patched.',
  '61032','this_week',80000,200000,'active',4,NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 30 DAY),
 (4,1,33,3,3,(SELECT id FROM cities WHERE market_id=1 AND slug='byron'),'NWI-9F3H6W',
  'Replace rotted deck boards (approx 40 sq ft)',
  'Back corner has about eight soft boards. Frame underneath looks solid. Cedar preferred to match, pressure-treated fine if cheaper.',
  '61010','this_month',40000,90000,'active',9,NOW() - INTERVAL 2 DAY, NOW() + INTERVAL 30 DAY),
 (5,1,34,2,2,(SELECT id FROM cities WHERE market_id=1 AND slug='loves-park'),'NWI-5T7J2D',
  'Install EV charger in attached garage',
  'Level 2, 240V. Panel is in the garage about 15 feet from where the charger goes. Need the permit handled.',
  '61111','this_month',60000,120000,'active',4,NOW() - INTERVAL 3 DAY, NOW() + INTERVAL 30 DAY),
 (6,1,35,8,2,(SELECT id FROM cities WHERE market_id=1 AND slug='roscoe'),'NWI-6C4N8V',
  'Build 6ft cedar privacy fence, back line only',
  'About 92 linear feet along the back property line. Old chain link needs hauling off. Subdivision requires cap and trim.',
  '61073','this_month',200000,350000,'active',3,NOW() - INTERVAL 6 HOUR, NOW() + INTERVAL 30 DAY),
 (7,1,36,5,2,(SELECT id FROM cities WHERE market_id=1 AND slug='machesney-park'),'NWI-3B9L1K',
  'Dishwasher will not drain, standing water',
  'Bosch, about six years old. Ran the disposal, cleaned the filter, still holding water at the bottom after every cycle.',
  '61115','asap',9000,20000,'active',2,NOW() - INTERVAL 1 HOUR, NOW() + INTERVAL 30 DAY),
 (8,1,37,4,2,(SELECT id FROM cities WHERE market_id=1 AND slug='rockford'),'NWI-8V2R7P',
  'Repaint two bedrooms, walls and trim',
  'Two 12x13 bedrooms, 9ft ceilings. Walls plus baseboards and door casings. Paint not purchased yet, would like a recommendation.',
  '61107','this_month',80000,140000,'active',5,NOW() - INTERVAL 4 HOUR, NOW() + INTERVAL 30 DAY),
 (9,1,30,7,6,(SELECT id FROM cities WHERE market_id=1 AND slug='galena'),'NWI-4H2K9S',
  'Gutter guards on a single-storey ranch',
  'Roughly 140 feet of gutter. Oaks overhang the back. Would like something that does not need me on a ladder twice a year.',
  '61036','flexible',90000,180000,'active',2,NOW() - INTERVAL 2 DAY, NOW() + INTERVAL 30 DAY),
 (10,1,31,10,1,(SELECT id FROM cities WHERE market_id=1 AND slug='freeport'),'NWI-1D6G3Z',
  'Hang 14 pictures and two mirrors',
  'Moving in. Everything is stacked against the wall in the living room. Mirrors are heavy and need anchors.',
  '61032','flexible',10000,18000,'pending_payment',0,NULL,NULL);

INSERT INTO quotes (market_id, job_id, pro_id, amount_cents, amount_type, amount_max_cents, message, status, created_at) VALUES
 (1,1,1,52000,'fixed',NULL,'Sounds like the float switch rather than the pump itself, but I will know in ten minutes. I can be there within the hour, and I will quote the battery backup separately so you can decide after the emergency is dealt with.','viewed',NOW() - INTERVAL 90 MINUTE),
 (1,2,9,28000,'range',45000,'Short-cycling that fast is usually the flame sensor or a blocked exhaust. Both are same-visit fixes. I have a slot tomorrow morning.','sent',NOW() - INTERVAL 3 HOUR),
 (1,3,10,164000,'fixed',NULL,'Thirty feet of seamless aluminium plus the fascia behind it. While I am up there I will check the rest of the north run, because ice dams rarely damage one section only.','sent',NOW() - INTERVAL 20 HOUR),
 (1,4,7,68000,'range',82000,'Cedar to match, and I will sister any joists that are soft once the boards are up.','accepted',NOW() - INTERVAL 1 DAY),
 (1,6,7,285000,'fixed',NULL,'92ft of 6ft cedar with cap and trim, old chain link hauled off. Two days on site.','sent',NOW() - INTERVAL 3 HOUR);

INSERT INTO reviews (market_id, pro_id, job_id, author_user_id, rating, body, job_value_cents, status, created_at) VALUES
 (1,1,1,30,5,'He found the split line in twenty minutes after another company spent a day and quoted me a whole re-pipe. The bill was a third of what I expected.',64000,'published',NOW() - INTERVAL 21 DAY),
 (1,2,4,33,5,'Karin sent a drawing before she cut a single board. The built-ins look like they came with the house.',390000,'published',NOW() - INTERVAL 40 DAY),
 (1,10,3,32,5,'Came out in February when the gutter was hanging off the house, tarped it, and came back in April to do it properly. Never tried to sell me a new roof.',164000,'published',NOW() - INTERVAL 12 DAY);

-- --- payments --------------------------------------------------------------
INSERT INTO payments (market_id, user_id, kind, job_id, amount_cents, stripe_payment_intent, status, paid_at) VALUES
 (1,30,'job_listing',1,1000,'pi_demo_0001','succeeded',NOW() - INTERVAL 2 HOUR),
 (1,31,'job_listing',2,1000,'pi_demo_0002','succeeded',NOW() - INTERVAL 5 HOUR),
 (1,32,'job_listing',3,1000,'pi_demo_0003','succeeded',NOW() - INTERVAL 1 DAY),
 (1,33,'job_listing',4,1000,'pi_demo_0004','succeeded',NOW() - INTERVAL 2 DAY),
 (1,34,'job_listing',5,1000,'pi_demo_0005','succeeded',NOW() - INTERVAL 3 DAY),
 (1,35,'job_listing',6,1000,'pi_demo_0006','succeeded',NOW() - INTERVAL 6 HOUR),
 (1,36,'job_listing',7,1000,'pi_demo_0007','succeeded',NOW() - INTERVAL 1 HOUR),
 (1,37,'job_listing',8,1000,'pi_demo_0008','succeeded',NOW() - INTERVAL 4 HOUR),
 (1,30,'job_listing',9,1000,'pi_demo_0009','succeeded',NOW() - INTERVAL 2 DAY);
INSERT INTO payments (market_id, user_id, kind, job_id, amount_cents, stripe_payment_intent, status, paid_at, refunded_cents, refunded_at, refund_reason) VALUES
 (1,31,'job_listing',NULL,1000,'pi_demo_0010','refunded',NOW() - INTERVAL 9 DAY,1000,NOW() - INTERVAL 6 DAY,'No quotes within 72 hours — auto-refund');

-- --- subscriptions and advertising -----------------------------------------
INSERT INTO subscriptions (id, market_id, pro_id, plan, price_cents, stripe_subscription_id, status, current_period_start, current_period_end) VALUES
 (1,1,1,'spotlight',14900,'sub_demo_0001','active',NOW() - INTERVAL 12 DAY, NOW() + INTERVAL 18 DAY),
 (2,1,2,'spotlight',14900,'sub_demo_0002','active',NOW() - INTERVAL 5 DAY,  NOW() + INTERVAL 25 DAY),
 (3,1,3,'boost',     4900,'sub_demo_0003','active',NOW() - INTERVAL 20 DAY, NOW() + INTERVAL 10 DAY),
 (4,1,7,'boost',     4900,'sub_demo_0004','active',NOW() - INTERVAL 2 DAY,  NOW() + INTERVAL 28 DAY),
 (5,1,5,'boost',     4900,'sub_demo_0005','past_due',NOW() - INTERVAL 33 DAY, NOW() - INTERVAL 3 DAY);

INSERT INTO payments (market_id, user_id, kind, subscription_id, amount_cents, stripe_payment_intent, status, paid_at) VALUES
 (1,10,'subscription',1,14900,'pi_demo_1001','succeeded',NOW() - INTERVAL 12 DAY),
 (1,11,'subscription',2,14900,'pi_demo_1002','succeeded',NOW() - INTERVAL 5 DAY),
 (1,12,'subscription',3, 4900,'pi_demo_1003','succeeded',NOW() - INTERVAL 20 DAY),
 (1,16,'subscription',4, 4900,'pi_demo_1004','succeeded',NOW() - INTERVAL 2 DAY);

-- Marcus overrides all three fields and runs an A/B test. Karin overrides
-- nothing — variant A with every column NULL is a complete, live ad assembled
-- entirely from her profile, which is the point of the design.
INSERT INTO ad_creatives (id, market_id, pro_id, variant, headline, offer_line, cta_label, hero_photo_id, status, reviewed_by, reviewed_at) VALUES
 (1,1,1,'A','Master Plumber, 18 years','Diagnosis before quote, in writing, every time.','See Marcus''s work',1,'approved',2,NOW() - INTERVAL 12 DAY),
 (2,1,1,'B','Sump pump out? Same day.','Licensed master plumber. Battery backup quoted separately, never bundled.','Get a quote',3,'approved',2,NOW() - INTERVAL 4 DAY),
 (3,1,2,'A',NULL,NULL,NULL,NULL,'approved',2,NOW() - INTERVAL 5 DAY),
 (4,1,3,'A','Your whole honey-do list, one visit',NULL,NULL,NULL,'approved',2,NOW() - INTERVAL 20 DAY),
 (5,1,7,'A',NULL,'Cedar fences and deck rebuilds. Every post below the frost line.',NULL,6,'pending_review',NULL,NULL);

-- One placement per subscription, in the slot the public pages actually read.
-- directory_top is what the directory, the home page and every town page all
-- select from, so buying once lifts a listing everywhere it appears and there
-- is exactly one row to revoke when payment stops. Position orders within a
-- plan by who bought first.
--
-- The past_due one stays active on purpose: Stripe retries a declined card for
-- two weeks and usually wins, and the webhook leaves the placement up for that
-- reason. Seeding it paused would have the sample data contradict the code.
INSERT INTO ad_placements (id, market_id, pro_id, subscription_id, slot, trade_id, position, status) VALUES
 (1,1,1,1,'directory_top',NULL,1,'active'),
 (2,1,2,2,'directory_top',NULL,2,'active'),
 (3,1,3,3,'directory_top',NULL,1,'active'),
 (4,1,7,4,'directory_top',NULL,2,'active'),
 (5,1,5,5,'directory_top',NULL,3,'active');

-- Fourteen days of history, one row per placement per day — the shape the
-- live counter writes, so the admin screens are exercised against data that
-- looks like production rather than a shape nothing produces.
--
-- creative_id stays NULL: ads here are assembled from the pro's own profile.
-- It must not go in the unique key either — MySQL treats NULLs as distinct,
-- so a key containing it never matches and the daily rollup inserts a row per
-- impression instead of incrementing one. See migration 005.
INSERT INTO ad_stats_daily (market_id, pro_id, placement_id, creative_id, stat_date, impressions, clicks, quotes_sent, jobs_won)
SELECT 1, pl.pro_id, pl.id, NULL, d.dt,
       FLOOR(40 + RAND(d.n * pl.id + 11) * (CASE WHEN s.plan = 'spotlight' THEN 150 ELSE 60 END)),
       FLOOR(2  + RAND(d.n * pl.id + 7)  * (CASE WHEN s.plan = 'spotlight' THEN 12  ELSE 5  END)),
       FLOOR(RAND(d.n * pl.id + 3) * 3),
       FLOOR(RAND(d.n * pl.id + 5) * 1.4)
FROM ad_placements pl
JOIN subscriptions s ON s.id = pl.subscription_id
CROSS JOIN (
  SELECT 0 n, CURDATE() dt UNION SELECT 1, CURDATE()-INTERVAL 1 DAY UNION SELECT 2, CURDATE()-INTERVAL 2 DAY
  UNION SELECT 3, CURDATE()-INTERVAL 3 DAY UNION SELECT 4, CURDATE()-INTERVAL 4 DAY
  UNION SELECT 5, CURDATE()-INTERVAL 5 DAY UNION SELECT 6, CURDATE()-INTERVAL 6 DAY
  UNION SELECT 7, CURDATE()-INTERVAL 7 DAY UNION SELECT 8, CURDATE()-INTERVAL 8 DAY
  UNION SELECT 9, CURDATE()-INTERVAL 9 DAY UNION SELECT 10, CURDATE()-INTERVAL 10 DAY
  UNION SELECT 11, CURDATE()-INTERVAL 11 DAY UNION SELECT 12, CURDATE()-INTERVAL 12 DAY
  UNION SELECT 13, CURDATE()-INTERVAL 13 DAY
) d
WHERE pl.market_id = 1;

INSERT INTO moderation_items (market_id, subject_type, subject_id, source, reason, reported_by, status, created_at) VALUES
 (1,'job',10,'auto','Possible duplicate post — second from this user today',NULL,'open',NOW() - INTERVAL 11 MINUTE),
 (1,'pro_profile',8,'auto','Lists smart-home electrical with no licence on file',NULL,'open',NOW() - INTERVAL 1 HOUR),
 (1,'ad_creative',2,'admin','Headline needs a licence-claim check',1,'open',NOW() - INTERVAL 3 HOUR);

-- --- who licenses what, in Illinois ----------------------------------------
-- Real reference data, not demonstration data: it is licensing law rather
-- than invented listings, so it is not flagged is_demo and bin/demo.php
-- leaves it alone.

INSERT INTO licence_authorities (state, trade_id, licensed, authority, lookup_url, number_format, guidance)
VALUES
 ('IL', 1, 1, 'Illinois Department of Public Health',
  'https://dph.illinois.gov/topics-services/environmental-health-protection/plumbing.html',
  '058-xxxxxx',
  'Plumbers are licensed by Public Health, not IDFPR. Searching the IDFPR register for a plumber finds nothing and means nothing. Check the number is current and in this person''s name.'),
 ('IL', 7, 1, 'IDFPR — Division of Professional Regulation',
  'https://idfpr.illinois.gov/licenselookup/licenselookup.asp',
  '104-xxxxxx',
  'Roofing contractors are licensed by IDFPR under the Roofing Industry Licensing Act. Use the Professional Regulation lookup and search by business name or licence number.'),
 ('IL', 2, 0, 'The city or county', '', '',
  'Illinois has no statewide electrician licence. Electricians are licensed municipally — Rockford and Freeport each run their own. Ask which municipality issued it and check with that office. No state number is normal here, not a red flag.'),
 ('IL', 0, 0, 'Not licensed at state level', '', '',
  'Illinois does not license this trade at state level. Insurance is what matters: ask for a certificate of liability insurance, check it is current and in the business name. Some towns register contractors, so it is worth asking which.')
ON DUPLICATE KEY UPDATE
  licensed = VALUES(licensed), authority = VALUES(authority),
  lookup_url = VALUES(lookup_url), number_format = VALUES(number_format),
  guidance = VALUES(guidance);

-- --- mark all of it as demonstration data ----------------------------------
--
-- Unqualified on purpose. This file truncates every tenant table at the top,
-- so once it has run, every row in these four tables came from this file and
-- every one of them is invented. A WHERE clause here could only be wrong.
--
-- This is what makes the "Sample" labels appear in the UI, and what
-- bin/demo.php purges. Never run this file against a database holding real
-- signups — the truncates above would take them with it.

UPDATE users        SET is_demo = 1;
UPDATE pro_profiles SET is_demo = 1;
UPDATE jobs         SET is_demo = 1;
UPDATE reviews      SET is_demo = 1;
