-- ===========================================================================
-- 007 — landing pages for the eight remaining Tier 1 towns
--
-- The seed gave a page to the eleven largest towns in the market, on the
-- reasoning that forty-six near-empty pages read as a content farm. That
-- reasoning still holds for the villages. It does not hold for these eight:
-- they are the towns Fix Listed is actually trying to win first, and several
-- of them — Lena, Stockton, Pearl City, Orangeville, Cedarville, Dakota,
-- German Valley, Forreston — are where the nearest competitor is a
-- forty-minute drive and a search engine has almost nothing local to show.
--
-- Rockford is bigger. These are winnable.
--
-- sort_order puts Freeport first because it is the core of the market, then
-- the eight in rough order of size, leaving the Rockford-area towns where
-- they were. Order is how the footer and the "nearby towns" chips read, and
-- it is the cheapest signal available about which pages matter.
--
-- A page turned on here is not a page with content in it: the landing page
-- shows what the database actually has, including "nobody covers this town
-- yet" when that is the truth. See app/Views/site/city.php. Turning a page
-- off again is one UPDATE — has_page is a switch, not a deletion.
--
-- Scoped to the market by slug rather than by a literal id. City slugs are
-- unique per market, not globally, so an unscoped UPDATE would reach into the
-- staged markets as soon as one of them has a town by the same name.
-- ===========================================================================

UPDATE cities
   SET has_page = 1,
       sort_order = CASE slug
         WHEN 'lena'          THEN 16
         WHEN 'stockton'      THEN 17
         WHEN 'forreston'     THEN 18
         WHEN 'pearl-city'    THEN 19
         WHEN 'orangeville'   THEN 20
         WHEN 'cedarville'    THEN 21
         WHEN 'dakota'        THEN 22
         WHEN 'german-valley' THEN 23
         ELSE sort_order
       END
 WHERE market_id = (SELECT id FROM markets WHERE slug = 'northwest-illinois')
   AND slug IN ('lena','stockton','forreston','pearl-city',
                'orangeville','cedarville','dakota','german-valley');

-- Freeport ahead of Rockford. Rockford is the bigger city; Freeport is the
-- one this directory can realistically be first in, and the first town in
-- every list is the one most people click.
UPDATE cities SET sort_order = 5
 WHERE slug = 'freeport'
   AND market_id = (SELECT id FROM markets WHERE slug = 'northwest-illinois');

INSERT IGNORE INTO migrations (filename) VALUES ('007_tier1_city_pages.sql');
