<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * What each trade actually does, in words.
 *
 * A service page with nothing on it but a heading and a list of nobody is a
 * page that should not exist. This is the text that makes /services/plumbing
 * worth opening whether or not a single plumber has listed yet: what the trade
 * covers, the jobs people actually ring about, and the question worth asking
 * before you hire one.
 *
 * It lives in code rather than in a table, unlike the licensing guidance next
 * to it, and the difference is deliberate. Licensing changes when a state
 * changes its law and an administrator must be able to correct it the same
 * afternoon; what a carpenter does has not changed and there is no screen to
 * edit it from. A table with no editor is just a slower constant.
 *
 * Nothing here claims anyone is available. Availability is counted from the
 * database on the page itself — see ServiceController.
 */
final class TradeCopy
{
    /**
     * Keyed by trade slug. Each entry:
     *   intro  — one paragraph, what the trade covers round here
     *   jobs   — the calls that actually come in, in plain words
     *   ask    — the one question worth asking before hiring
     *   job    — the phrase that fits "Post a ___ job"
     *
     * `job` exists because trade *names* do not survive being dropped into a
     * sentence. "Post a plumbing job" reads fine; "Post a odd jobs job" is
     * what you get from the same template, and "Post a drywall & paint job"
     * is not much better. A page that cannot say what it is for in English
     * is not a page anybody trusts with their boiler, and the same string
     * ends up in the meta description, so a search result says it too.
     *
     * @var array<string,array{intro:string,jobs:array<int,string>,ask:string,job:string}>
     */
    private const COPY = [
        'plumbing' => [
            'job' => 'plumbing',
            'intro' => 'Plumbers handle everything water moves through: supply lines, drains, '
                . 'water heaters, fixtures and the frozen pipe that finds you every January. In '
                . 'Illinois plumbing is one of the few trades licensed by the state, so this is a '
                . 'job where the licence number is worth asking for.',
            'jobs' => [
                'A blocked drain or a slow one that keeps coming back',
                'Water heater repair, or replacing one that has given up',
                'A burst or frozen pipe — the winter emergency call',
                'Replacing taps, toilets, sinks and shower valves',
                'Sump pump failure after heavy rain',
                'Rough-in plumbing for a new bathroom or kitchen',
            ],
            'ask' => 'Ask for the Illinois plumbing licence number and check it is current and in '
                . 'the name of the person doing the work, not just the company.',
        ],
        'electrical' => [
            'job' => 'electrical',
            'intro' => 'Electricians cover panels, circuits, outlets, lighting and the wiring '
                . 'behind all of it. Illinois has no statewide electrician licence — towns license '
                . 'their own — so ask which municipality issued theirs rather than expecting a '
                . 'state number.',
            'jobs' => [
                'Adding outlets, or replacing ones that no longer hold a plug',
                'Panel upgrades and replacing an old fuse box',
                'Ceiling fans, light fixtures and recessed lighting',
                'A breaker that keeps tripping',
                'Wiring a garage, workshop or EV charger',
                'Fixing aluminium wiring or knob-and-tube in an older house',
            ],
            'ask' => 'Ask which town or county issued the licence, and get proof of liability '
                . 'insurance. A missing state number is normal in Illinois; missing insurance is not.',
        ],
        'carpentry' => [
            'job' => 'carpentry',
            'intro' => 'Carpenters build and repair the wooden parts of a house — framing, trim, '
                . 'stairs, doors, cabinets and the porch that has started to sag. Much of it is '
                . 'work a general handyman will not take on and a specialist contractor will not '
                . 'come out for.',
            'jobs' => [
                'Doors that stick, drag or will not latch',
                'Trim, baseboards, crown moulding and casing',
                'Built-in shelving and cabinets',
                'Stair treads, railings and balusters',
                'Rotten porch boards, posts and soffit',
                'Framing for a remodel or a partition wall',
            ],
            'ask' => 'Ask to see photographs of finished work of the same kind. Carpentry is the '
                . 'trade where the difference between adequate and good is visible from the sofa.',
        ],
        'drywall-paint' => [
            'job' => 'drywall or painting',
            'intro' => 'Drywall and painting go together because the finish is only as good as the '
                . 'surface under it. This covers patching, hanging and taping board, texture '
                . 'matching, and interior and exterior painting.',
            'jobs' => [
                'Patching holes, dents and doorknob damage',
                'Ceiling repair after a leak',
                'Taping and finishing a new wall',
                'Matching an existing texture so the repair disappears',
                'Interior repainting, room by room or whole house',
                'Exterior painting and staining',
            ],
            'ask' => 'Ask how many coats are quoted and whether preparation is included. Most of '
                . 'the price difference between two painting quotes is in preparation, not paint.',
        ],
        'appliance' => [
            'job' => 'appliance repair',
            'intro' => 'Appliance engineers repair what is already in the house: washers, dryers, '
                . 'fridges, ovens, dishwashers and garbage disposals. Often the question worth '
                . 'answering first is whether a repair is worth doing at all.',
            'jobs' => [
                'A washer that will not drain or spin',
                'A dryer taking three cycles to dry a load',
                'A fridge that is not holding temperature',
                'Oven and range elements, igniters and thermostats',
                'Dishwashers that leak or leave dishes dirty',
                'Garbage disposal jams and replacement',
            ],
            'ask' => 'Ask whether the call-out fee comes off the repair, and get the parts cost '
                . 'before agreeing — on an older machine it can exceed a replacement.',
        ],
        'hvac' => [
            'job' => 'heating or cooling',
            'intro' => 'Heating and cooling covers furnaces, air conditioning, heat pumps, '
                . 'ductwork and the annual service that stops a January failure. In this part of '
                . 'Illinois a furnace that fails in a cold snap is an emergency, so it is worth '
                . 'knowing who you would call before you need to.',
            'jobs' => [
                'A furnace that will not fire, or short-cycles',
                'Air conditioning that runs but does not cool',
                'Annual service and safety checks before winter',
                'Thermostat replacement, including smart thermostats',
                'Ductwork repair, sealing and new runs',
                'Replacing a furnace or AC unit at end of life',
            ],
            'ask' => 'Ask for the model and the load calculation before agreeing to a replacement '
                . 'system. An oversized furnace costs more to buy and more to run.',
        ],
        'roofing-gutters' => [
            'job' => 'roofing or gutter',
            'intro' => 'Roofers cover shingles, flashing, valleys, gutters and downspouts. '
                . 'Roofing contractors in Illinois are licensed by IDFPR under the Roofing '
                . 'Industry Licensing Act, so unlike most trades here there is a state register '
                . 'you can check yourself.',
            'jobs' => [
                'Leaks, and finding where the water actually gets in',
                'Missing or lifted shingles after a storm',
                'Flashing around chimneys, vents and valleys',
                'Gutter cleaning, repair and replacement',
                'Ice dam damage and prevention',
                'Full tear-off and re-roof',
            ],
            'ask' => 'Check the IDFPR roofing licence number yourself on the state lookup. This is '
                . 'the trade with the most storm-chasing door-knockers, and the register is the '
                . 'fastest way to tell them from the local firms.',
        ],
        'fencing-decks' => [
            'job' => 'fencing or decking',
            'intro' => 'Fencing and decking is outdoor structural work: posts, rails, boards, '
                . 'gates, stairs and railings. Most of it needs a permit and all of it needs the '
                . 'utilities located before anybody digs.',
            'jobs' => [
                'New fencing — wood, vinyl, chain link or aluminium',
                'Replacing rotten posts and rails',
                'Gates that drag or will not latch',
                'New deck build, and deck stairs',
                'Re-decking over an existing frame',
                'Sanding, sealing and staining',
            ],
            'ask' => 'Ask who is pulling the permit and confirm JULIE has been called for utility '
                . 'locates. In Illinois that call is free, required before digging, and the '
                . 'contractor\'s job to make.',
        ],
        'landscaping-snow' => [
            'job' => 'landscaping or snow-clearing',
            'intro' => 'Grounds work across the year: mowing, planting, beds, drainage and '
                . 'clean-ups in the warm months, and plowing, shovelling and salting once it turns. '
                . 'Many of the same firms do both, which is why they are listed together.',
            'jobs' => [
                'Regular mowing and seasonal clean-ups',
                'Planting, beds, mulch and edging',
                'Drainage and regrading where water sits against the house',
                'Tree and shrub trimming',
                'Driveway plowing and salting on a season contract',
                'Walkway and step clearing after a storm',
            ],
            'ask' => 'For snow, agree the trigger depth and whether it is per visit or per season '
                . 'before the first snowfall. Booking a plow contract in December costs more than '
                . 'booking it in September.',
        ],
        'odd-jobs' => [
            'job' => 'handyman',
            'intro' => 'The general handyman list — the jobs too small for a specialist and too '
                . 'awkward to keep putting off. One visit often clears several at once, which is '
                . 'usually the cheapest way to have them done.',
            'jobs' => [
                'Mounting TVs, shelves, blinds and curtain rails',
                'Furniture assembly',
                'Replacing locks, handles and door hardware',
                'Caulking baths, showers and windows',
                'Hanging pictures and mirrors properly',
                'Small repairs nobody else will come out for',
            ],
            'ask' => 'Make a list before they arrive and send it with the job. Handymen price by '
                . 'the visit as much as by the task, and six small things in one trip beats six trips.',
        ],
    ];

    /**
     * Copy for a trade, or null if there is none.
     *
     * Null rather than a generic paragraph on purpose: a new trade added to
     * the taxonomy should make the page show less, not show boilerplate
     * pretending to be specific. ServiceController decides what to do with
     * that, and the sitemap decides whether the page is worth submitting.
     *
     * @return array{intro:string,jobs:array<int,string>,ask:string,job:string}|null
     */
    public static function for(string $tradeSlug): ?array
    {
        return self::COPY[$tradeSlug] ?? null;
    }

    /**
     * The phrase a button or a sentence uses: "a plumbing job", "an
     * electrical job", "a handyman job".
     *
     * The article is worked out here rather than stored, so the data above
     * stays readable, and it is a plain vowel check rather than anything
     * clever. That is wrong for "an hour" and "a university" — and right for
     * all ten phrases it is ever given, which bin/smoke.php asserts. If a
     * trade is ever added whose phrase starts with a silent h or a long u,
     * the test fails and the article moves into the data.
     */
    public static function jobPhrase(string $tradeSlug): string
    {
        $copy = self::for($tradeSlug);
        if ($copy === null) {
            return 'a job';
        }

        $article = str_contains('aeiou', strtolower($copy['job'][0])) ? 'an' : 'a';

        return $article . ' ' . $copy['job'] . ' job';
    }

    /** @return array<int,string> every trade slug that has copy written */
    public static function slugs(): array
    {
        return array_keys(self::COPY);
    }
}
