<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * Every lesson in both academies.
 *
 * In code rather than in a table, and that is the same argument TradeCopy
 * makes with an extra edge on it. A lesson about approving a listing
 * application is documentation of a screen. Put the words in a database and
 * the screen changes in March while the lesson still describes February —
 * and nobody finds out until a moderator follows instructions that no longer
 * match what is in front of them. Here, changing the screen and changing the
 * lesson are the same commit or they are an obvious omission in review.
 *
 * What is NOT here is anything the owner needs to change without a deploy:
 * the video for a lesson once it gets recorded, and whether a lesson is
 * published at all. Those live in academy_lessons and override these
 * defaults — see AcademyRepository.
 *
 * BODIES are blocks, not HTML. A lesson cannot introduce a style, a script
 * or a broken tag, and every lesson looks like every other one without
 * anybody maintaining that. The block types are:
 *
 *   ['h'     => 'A heading']
 *   ['p'     => 'A paragraph.']
 *   ['ul'    => ['a point', 'another']]
 *   ['steps' => ['do this', 'then this']]     numbered
 *   ['note'  => 'Something set apart.']
 *   ['warn'  => 'Something that costs money or trust if ignored.']
 *
 * Inline markup is deliberately absent. A lesson that needs bold in the
 * middle of a sentence usually needs a shorter sentence.
 */
final class Academy
{
    public const TRACK_HOMEOWNER = 'homeowners';
    public const TRACK_PRO       = 'tradespeople';
    public const TRACK_STAFF     = 'staff';

    /**
     * @return array<string,array{
     *   track:string, title:string, nav:string, summary:string,
     *   minutes:int, published:bool, body:array<int,array<string,mixed>>
     * }>
     */
    public static function all(): array
    {
        return array_merge(self::homeowners(), self::pros(), self::staff());
    }

    /** @return array<string,mixed>|null */
    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    /** @return array<string,array<string,mixed>> */
    public static function inTrack(string $track): array
    {
        return array_filter(self::all(), static fn (array $l): bool => $l['track'] === $track);
    }

    /** Public tracks only. The staff handbook is never listed on the site. */
    public static function isPublicTrack(string $track): bool
    {
        return in_array($track, [self::TRACK_HOMEOWNER, self::TRACK_PRO], true);
    }

    // =====================================================================
    // Homeowners
    //
    // Written for somebody who has a broken thing and has never used this
    // site. Every lesson answers a question they would actually type, which
    // is also why these earn search traffic the directory pages cannot.
    // =====================================================================

    /** @return array<string,array<string,mixed>> */
    private static function homeowners(): array
    {
        return [
            'how-to-post-a-job' => [
                'track'   => self::TRACK_HOMEOWNER,
                'title'   => 'How to post a job on Fix Listed',
                'nav'     => 'Posting a job',
                'summary' => 'What to write, what it costs, and what happens after you press the button.',
                'minutes' => 4,
                'published' => true,
                'body'    => [
                    ['p' => 'Posting a job puts what you need doing in front of every tradesperson '
                          . 'who covers your county and works in that trade. They come to you. You '
                          . 'are not ringing round and leaving messages.'],
                    ['h' => 'What it costs'],
                    ['p' => 'Ten dollars, once, for the listing. That is the whole charge. '
                          . 'Tradespeople quote for free, you pay them directly for the work, and '
                          . 'Fix Listed takes nothing out of that.'],
                    ['note' => 'If nobody quotes your job inside the refund window, you get the ten '
                             . 'dollars back automatically. You do not have to ask and you do not '
                             . 'have to notice.'],
                    ['h' => 'Writing it'],
                    ['steps' => [
                        'Say what is wrong in a sentence. "Sump pump stopped, water coming up in '
                            . 'the basement" tells a plumber more than "plumbing job".',
                        'Say how urgent it is. Today, this week, this month, or whenever suits — '
                            . 'tradespeople sort by this, and marking everything ASAP stops it '
                            . 'meaning anything.',
                        'Give a budget range if you have one. It is not a commitment. It stops you '
                            . 'getting quotes that were never going to work for either of you.',
                        'Pick the town and the trade. That is what decides who sees it.',
                    ]],
                    ['h' => 'After you post'],
                    ['p' => 'Your job goes on the public board and every matching tradesperson gets '
                          . 'an email about it, usually within a minute. Quotes come back to you by '
                          . 'email. You pick one, or none.'],
                    ['h' => 'What stays private'],
                    ['p' => 'Your phone number and your address are not on the public board and are '
                          . 'not given to anybody who quotes. You share them with the tradesperson '
                          . 'you choose, when you choose.'],
                ],
            ],

            'what-the-fee-covers' => [
                'track'   => self::TRACK_HOMEOWNER,
                'title'   => 'What the $10 fee covers, and when you get it back',
                'nav'     => 'What the fee covers',
                'summary' => 'Why there is a fee at all, what it pays for, and the refund that '
                           . 'happens without you asking.',
                'minutes' => 3,
                'published' => true,
                'body'    => [
                    ['h' => 'Why charge anything'],
                    ['p' => 'Most directories are free to the homeowner and charge the tradesperson '
                          . 'for every lead. That sounds better and works worse: your details get '
                          . 'sold to four businesses who each paid for them, your phone rings all '
                          . 'afternoon, and the cost of those leads is inside the price you are '
                          . 'quoted.'],
                    ['p' => 'Ten dollars from you instead means we are not selling you to anybody. '
                          . 'It is the whole reason the rest of this works the way it does.'],
                    ['h' => 'What it buys'],
                    ['ul' => [
                        'Your job in front of every tradesperson covering your county in that trade',
                        'An email to each of them when it goes live',
                        'A place on the public jobs board for the length of the listing',
                        'Quotes back by email, with no commission taken from the work',
                    ]],
                    ['h' => 'When you get it back'],
                    ['p' => 'If nobody quotes inside the refund window, the fee goes back to the card '
                          . 'that paid it, automatically. No form, no email to send.'],
                    ['warn' => 'A refund means we did not have enough tradespeople in your county '
                             . 'for that kind of work. That is our problem, not yours. Reply to the '
                             . 'refund email and we will go and find somebody.'],
                    ['h' => 'What it does not buy'],
                    ['p' => 'It does not buy a place at the front of the queue, and it does not buy '
                          . 'anybody\'s attention. A tradesperson quotes your job because it suits '
                          . 'them, not because you paid us.'],
                ],
            ],

            'how-to-compare-quotes' => [
                'track'   => self::TRACK_HOMEOWNER,
                'title'   => 'How to compare quotes without just picking the cheapest',
                'nav'     => 'Comparing quotes',
                'summary' => 'What makes two quotes for the same job differ by hundreds, and which '
                           . 'differences matter.',
                'minutes' => 5,
                'published' => true,
                'body'    => [
                    ['p' => 'Three quotes for the same job can be hundreds of dollars apart and all '
                          . 'three be honest. The gap is almost never greed. It is scope.'],
                    ['h' => 'Ask what is included'],
                    ['ul' => [
                        'Materials, or labour only',
                        'Taking the old one away, or leaving it in your garage',
                        'The permit, if the job needs one, and who pulls it',
                        'Making good afterwards — the patch, the paint, the clean-up',
                    ]],
                    ['p' => 'A painter quoting half what the others quoted has usually not included '
                          . 'preparation. Most of the difference between two painting quotes is '
                          . 'sanding and filling, not paint.'],
                    ['h' => 'Ask what happens if it is worse than it looks'],
                    ['p' => 'Ask how a change is handled before anybody starts. A tradesperson who '
                          . 'says "I would ring you before doing anything extra" is telling you '
                          . 'something useful about how the week will go.'],
                    ['h' => 'Check the licence yourself'],
                    ['p' => 'We check licence and insurance before a profile goes live, and you can '
                          . 'check it again. For the trades Illinois licences by state, the register '
                          . 'is public and takes a minute.'],
                    ['note' => 'The cheapest quote and the best quote are sometimes the same one. '
                             . 'The point is to know which of those you are choosing.'],
                    ['h' => 'Then decide on the person'],
                    ['p' => 'Did they turn up when they said they would to look at it. Did the quote '
                          . 'arrive when promised. Did they explain the thing you asked about or '
                          . 'talk past it. That is most of what the next fortnight will feel like.'],
                ],
            ],

            'checking-a-tradespersons-licence-in-illinois' => [
                'track'   => self::TRACK_HOMEOWNER,
                'title'   => 'How to check a tradesperson\'s licence in Illinois',
                'nav'     => 'Checking a licence',
                'summary' => 'Which trades Illinois licenses by state, which are licensed by the '
                           . 'town, and how to look either one up.',
                'minutes' => 5,
                'published' => true,
                'body'    => [
                    ['p' => 'Illinois does not license every trade, which is the part that catches '
                          . 'people out. "Not licensed by the state" does not mean somebody is '
                          . 'working illegally. It means the licence you should be asking for comes '
                          . 'from somewhere else.'],
                    ['h' => 'Licensed by the state'],
                    ['ul' => [
                        'Plumbing — the Illinois Department of Public Health licenses plumbers, and '
                            . 'the licence is held by a person, not a company',
                        'Roofing — IDFPR licenses roofing contractors under the Roofing Industry '
                            . 'Licensing Act, and the register is public',
                    ]],
                    ['p' => 'For these, ask for the number, then look it up. Check the name on the '
                          . 'licence matches the person who will actually do the work.'],
                    ['h' => 'Licensed by the town'],
                    ['p' => 'Electricians are the common one. Illinois has no statewide electrician '
                          . 'licence — municipalities license their own — so the right question is '
                          . 'which town or county issued it, not what the state number is. Somebody '
                          . 'who says they have no state electrical licence is telling you the '
                          . 'truth.'],
                    ['h' => 'Not licensed at all'],
                    ['p' => 'Carpentry, drywall, painting, fencing, landscaping and general handyman '
                          . 'work are not licensed in Illinois. For these, insurance is the thing to '
                          . 'ask for, and photographs of finished work of the same kind.'],
                    ['warn' => 'Liability insurance matters more than most people think. If an '
                             . 'uninsured tradesperson is hurt on your property, or puts a foot '
                             . 'through your ceiling, the question of who pays is one you do not '
                             . 'want to be asking afterwards.'],
                    ['h' => 'What we check'],
                    ['p' => 'Before a profile goes live on Fix Listed, somebody looks at the licence '
                          . 'and the insurance certificate. The verified badge means that happened. '
                          . 'It cannot be bought, at any price, which is the only reason it is worth '
                          . 'anything.'],
                ],
            ],
        ];
    }

    // =====================================================================
    // Tradespeople
    //
    // Written for somebody deciding whether this site is worth their time,
    // and then for somebody trying to win work on it. The honest version of
    // both, including what placement does and does not do.
    // =====================================================================

    /** @return array<string,array<string,mixed>> */
    private static function pros(): array
    {
        return [
            'getting-listed' => [
                'track'   => self::TRACK_PRO,
                'title'   => 'Getting listed on Fix Listed',
                'nav'     => 'Getting listed',
                'summary' => 'What we ask for, why we check it, and how long it takes.',
                'minutes' => 4,
                'published' => true,
                'body'    => [
                    ['p' => 'A listing is free. Quoting is free. We take nothing out of the work you '
                          . 'win. There is no per-lead charge and we do not sell your details to '
                          . 'anybody.'],
                    ['h' => 'What we ask for'],
                    ['ul' => [
                        'Your business name and what you do',
                        'The trades you work in',
                        'The counties you will drive to',
                        'Your licence number, if your trade is licensed, and who insures you',
                        'Your call-out fee, if you charge one',
                    ]],
                    ['p' => 'No contract, no monthly minimum, no card.'],
                    ['h' => 'Why we check the licence'],
                    ['p' => 'Because a badge nobody checks is worth nothing. If you are carrying a '
                          . 'current licence and real liability cover, you have been losing jobs on '
                          . 'price to somebody carrying neither. Checking is how that stops.'],
                    ['p' => 'It usually takes a day or two. Replying to your application email with '
                          . 'a photo of the licence and the certificate of insurance is the fastest '
                          . 'way through it.'],
                    ['h' => 'Once you are live'],
                    ['p' => 'You appear in the directory and on the town page for every county you '
                          . 'cover. Jobs in your counties and trades land on your board and arrive '
                          . 'by email. You quote the ones that suit you and ignore the rest.'],
                    ['note' => 'Not ready to list yet? You can get the job emails without a profile. '
                             . 'It is free and takes a minute.'],
                ],
            ],

            'writing-a-quote-that-wins' => [
                'track'   => self::TRACK_PRO,
                'title'   => 'Writing a quote that wins the work',
                'nav'     => 'Writing a quote',
                'summary' => 'Why the cheapest quote often loses, and what the winning one usually '
                           . 'has in it.',
                'minutes' => 5,
                'published' => true,
                'body'    => [
                    ['p' => 'A homeowner comparing three quotes for the same job is usually not '
                          . 'trying to spend the least. They are trying to work out which of you '
                          . 'they can trust in the house, and price is the only thing they feel '
                          . 'qualified to judge. Give them something else to judge.'],
                    ['h' => 'Say what is included'],
                    ['p' => 'Materials or not. Removal of the old one or not. The permit, and who '
                          . 'pulls it. Making good afterwards. A quote that lists these beats a '
                          . 'lower number that does not, because the lower number is a question and '
                          . 'yours is an answer.'],
                    ['h' => 'Say what would change it'],
                    ['p' => 'If opening the wall might find something, say so now and say what you '
                          . 'would do about it. Every homeowner has heard a story about a job that '
                          . 'doubled. Being the person who raised it first is worth more than being '
                          . 'the person who was cheapest.'],
                    ['h' => 'Say when'],
                    ['p' => 'Not "soon". A date, or a week. Availability loses more work on this '
                          . 'site than price does.'],
                    ['h' => 'Answer the thing they actually asked'],
                    ['p' => 'If the job says "water coming up in the basement and we have a party '
                          . 'Saturday", the quote that mentions Saturday wins. It costs a sentence.'],
                    ['warn' => 'Quote quickly. The homeowner is getting other quotes today, and the '
                             . 'first sensible one sets what the others are compared against.'],
                ],
            ],

            'photos-of-your-work' => [
                'track'   => self::TRACK_PRO,
                'title'   => 'Adding photos of your work',
                'nav'     => 'Adding photos',
                'summary' => 'What to photograph, what not to, and why we look at each one first.',
                'minutes' => 3,
                'published' => true,
                'body'    => [
                    ['p' => 'A homeowner deciding whether to let you into the house looks at the '
                          . 'work before they read about it. Photographs do more on a profile than '
                          . 'any paragraph you could write.'],
                    ['h' => 'What works'],
                    ['ul' => [
                        'Finished work, not work in progress',
                        'The whole thing, not a close-up of one joint',
                        'Daylight where you can',
                        'A range of jobs rather than five pictures of the same kitchen',
                    ]],
                    ['h' => 'What we take out'],
                    ['p' => 'Location data is stripped from every photograph automatically, before '
                          . 'it is stored. A phone writes the coordinates of where a picture was '
                          . 'taken into the file, and that would be your customer\'s address. It '
                          . 'never reaches our server in a form anybody could read.'],
                    ['h' => 'Why we check them'],
                    ['p' => 'Because these are pictures of other people\'s houses. We look at the '
                          . 'whole frame — a house number, a van plate, a child, a neighbour\'s '
                          . 'door — before anything goes on a public page. It is usually the same '
                          . 'day.'],
                    ['note' => 'Ask the homeowner before photographing their house. Most say yes. '
                             . 'The ones who would have minded are the reason to ask.'],
                ],
            ],

            'answering-a-review' => [
                'track'   => self::TRACK_PRO,
                'title'   => 'Answering a review, including a bad one',
                'nav'     => 'Answering a review',
                'summary' => 'You cannot remove a review and neither can we. Here is what to do '
                           . 'instead.',
                'minutes' => 4,
                'published' => true,
                'body'    => [
                    ['p' => 'Reviews on Fix Listed come from homeowners who posted a job and hired '
                          . 'somebody through it. They are read before they go up. Once one is '
                          . 'published, you cannot remove it, and neither can we unless it breaks '
                          . 'the rules in our terms.'],
                    ['p' => 'What you can do is answer it, in public, under the review. That is on '
                          . 'your account page.'],
                    ['h' => 'A calm reply persuades more people than the review put off'],
                    ['p' => 'Whoever reads that review next is not deciding whether you were right. '
                          . 'They are deciding what you are like to deal with when something goes '
                          . 'wrong. The reply is the only evidence they have.'],
                    ['h' => 'What works'],
                    ['ul' => [
                        'Answer the point, not the person',
                        'Say what you did, or what you would do differently',
                        'Keep it shorter than the review',
                        'Offer to sort it out, if it can be sorted out',
                    ]],
                    ['h' => 'What does not'],
                    ['ul' => [
                        'Arguing the facts line by line',
                        'Anything about the customer personally',
                        'Explaining that they are the difficult one',
                    ]],
                    ['warn' => 'Write it, then leave it an hour before posting. Nobody has ever '
                             . 'regretted that hour.'],
                    ['h' => 'If a review is untrue'],
                    ['p' => 'Reply to your alert email and tell us. If it breaks the rules we take '
                          . 'it down. If it is simply harsh, it stays — a directory that removes '
                          . 'unflattering reviews is one nobody believes, which would cost you more '
                          . 'than the review does.'],
                ],
            ],

            'getting-seen-first' => [
                'track'   => self::TRACK_PRO,
                'title'   => 'Paid placement: what it does and what it cannot buy',
                'nav'     => 'Paid placement',
                'summary' => 'What Spotlight and Boost actually change, said plainly.',
                'minutes' => 3,
                'published' => true,
                'body'    => [
                    ['p' => 'Placement moves your listing up the page. That is all it does, and '
                          . 'saying so plainly is the point of this lesson.'],
                    ['h' => 'What it changes'],
                    ['ul' => [
                        'Where you sit in the directory, on the home page, and on the town pages '
                            . 'for your counties',
                        'A label on your card saying the position is paid for',
                    ]],
                    ['h' => 'What it does not change'],
                    ['ul' => [
                        'Your rating',
                        'Your reviews',
                        'The verified badge',
                        'Which jobs you can quote — everybody quotes the same jobs',
                    ]],
                    ['p' => 'None of those can be bought at any price. That is the whole reason '
                          . 'anybody trusts the list, including the part of it you would be paying '
                          . 'to sit at the top of.'],
                    ['note' => 'Slots are limited on purpose. If everyone can buy the top spot then '
                             . 'nobody\'s top spot is worth anything.'],
                    ['h' => 'Whether it is worth it'],
                    ['p' => 'Your account page counts how many times your listing was shown and how '
                          . 'many people opened it. Look at that before you buy placement and again '
                          . 'a month after. If it did not move, cancel it — you keep the rest of '
                          . 'the month you paid for.'],
                ],
            ],
        ];
    }

    // =====================================================================
    // Staff
    //
    // The handbook. Never rendered on the public site and never in the
    // sitemap — AcademyController refuses this track outright rather than
    // relying on nobody linking to it.
    //
    // Written for somebody who was handed a login and told to help. It names
    // screens and paths, because "where is that" is the actual question.
    // =====================================================================

    /** @return array<string,array<string,mixed>> */
    private static function staff(): array
    {
        return [
            'where-everything-lives' => [
                'track'   => self::TRACK_STAFF,
                'title'   => 'Where everything lives',
                'nav'     => 'Where everything lives',
                'summary' => 'A map of the admin, and which screen answers which question.',
                'minutes' => 4,
                'published' => true,
                'body'    => [
                    ['p' => 'The admin is the left-hand menu and nothing else. What you can see '
                          . 'depends on your role, so if a screen named here is not in your menu, '
                          . 'you do not have that permission rather than the screen being missing.'],
                    ['h' => 'The queues — things waiting for a person'],
                    ['ul' => [
                        'Applications — tradespeople asking to be listed. Licence and insurance '
                            . 'need checking before anything goes live.',
                        'Reviews — what homeowners wrote. Nothing appears on a profile until it is '
                            . 'published here.',
                        'Photos — pictures of work. Nothing appears until approved.',
                    ]],
                    ['p' => 'The Dashboard\'s "Waiting on you" counts applications and reviews '
                          . 'together. The menu badges count each queue separately.'],
                    ['h' => 'The lists — things that already exist'],
                    ['ul' => [
                        'Tradespeople — every listing, whatever its state. Suspend and reinstate '
                            . 'from here.',
                        'Jobs — everything posted, paid or not. Remove a job from here.',
                        'People — every account, and the waiting list from the holding page.',
                        'Job alerts — tradespeople who asked for job emails without listing. This '
                            . 'is a call list more than a mailing list.',
                    ]],
                    ['h' => 'The settings'],
                    ['ul' => [
                        'Licensing — the guidance shown on each trade page, per state',
                        'Advertising — placement inventory and what has sold',
                        'Team — who has a login and what they can reach',
                        'Maintenance — takes the site down and puts it back up',
                        'Activity log — who did what, and when',
                    ]],
                    ['note' => 'Anything that changes something is recorded in the activity log with '
                             . 'the name of whoever did it. That is there to protect you as much as '
                             . 'anything.'],
                ],
            ],

            'approving-an-application' => [
                'track'   => self::TRACK_STAFF,
                'title'   => 'Approving a listing application',
                'nav'     => 'Approving applications',
                'summary' => 'What to check before a tradesperson goes live, and what approving '
                           . 'actually does.',
                'minutes' => 5,
                'published' => true,
                'body'    => [
                    ['p' => 'Admin, then Applications. Everything waiting is here, oldest first.'],
                    ['h' => 'What you are checking'],
                    ['steps' => [
                        'Does the business exist. A search for the name and the town usually '
                            . 'settles it in thirty seconds.',
                        'Is the licence current, if the trade is licensed. Plumbing and roofing '
                            . 'have public state registers. Electricians are licensed by the town, '
                            . 'so ask which one — there is no state number to find.',
                        'Is the licence in the name of the person doing the work, or only the '
                            . 'company.',
                        'Is there real liability insurance, with a carrier and a policy that has '
                            . 'not expired.',
                        'Does the bio read like a person wrote it about their own business.',
                    ]],
                    ['h' => 'What approving does'],
                    ['p' => 'The profile goes live immediately, appears in the directory and on the '
                          . 'town pages for their counties, and they start getting job emails. '
                          . 'They get an email telling them so.'],
                    ['warn' => 'Approving puts the verified badge on their profile. That badge is '
                             . 'the site\'s whole promise to homeowners and it cannot be bought. '
                             . 'If you are not sure, do not approve — email them and ask.'],
                    ['h' => 'Rejecting'],
                    ['p' => 'Rejecting sends them an email too. Say why in a sentence. Most '
                          . 'rejections are a missing document rather than a bad business, and '
                          . 'those people come back with the document if the email tells them what '
                          . 'was missing.'],
                    ['h' => 'If a licence changes later'],
                    ['p' => 'A tradesperson editing their licence number loses the badge '
                          . 'automatically and comes back into this queue. That is deliberate and '
                          . 'you do not need to do anything to make it happen.'],
                ],
            ],

            'moderating-reviews-and-photos' => [
                'track'   => self::TRACK_STAFF,
                'title'   => 'Moderating reviews and photos',
                'nav'     => 'Moderating',
                'summary' => 'What to publish, what to pull, and the one thing not to do.',
                'minutes' => 5,
                'published' => true,
                'body'    => [
                    ['h' => 'Reviews'],
                    ['p' => 'Admin, then Reviews. A review only exists if a homeowner posted a job, '
                          . 'somebody quoted it, and they followed a private link we emailed them. '
                          . 'They cannot be left by anyone else.'],
                    ['p' => 'Publish it unless it breaks a rule. A harsh review is not a broken '
                          . 'rule.'],
                    ['ul' => [
                        'Names a person rather than a business — pull it',
                        'Contains an address, a phone number or anything identifying a household — '
                            . 'pull it',
                        'Abusive or discriminatory — pull it',
                        'Obviously about a different business — pull it',
                        'Unfair, one-sided, or written in a temper — publish it',
                    ]],
                    ['warn' => 'Do not pull a review because the tradesperson asked you to. A '
                             . 'directory that removes unflattering reviews is one nobody believes, '
                             . 'and the tradespeople on it lose more from that than they lose from '
                             . 'any single review. Point them at the reply instead — they can '
                             . 'answer it publicly from their own account.'],
                    ['p' => 'Publishing recalculates that business\'s rating immediately. So does '
                          . 'removing one, in the other direction.'],
                    ['h' => 'Photos'],
                    ['p' => 'Admin, then Photos. These are pictures of other people\'s houses, '
                          . 'published under a business name, and the homeowner never agreed to '
                          . 'anything.'],
                    ['p' => 'Look at the whole frame, not the work. Location data is already '
                          . 'stripped automatically — what it cannot strip is what is visible in '
                          . 'the picture.'],
                    ['ul' => [
                        'A house number, a street sign, a van plate — reject',
                        'A person, and especially a child — reject',
                        'Somebody else\'s front door or car — reject',
                        'Work in progress that shows a family\'s belongings — reject',
                        'Finished work, no people, nothing identifying — approve',
                    ]],
                    ['note' => 'Rejecting stops the URL working immediately. The file is not served '
                             . 'to anybody after that, including somebody who saved the link.'],
                ],
            ],

            'money-refunds-and-the-sweep' => [
                'track'   => self::TRACK_STAFF,
                'title'   => 'Money: payments, refunds, and what runs overnight',
                'nav'     => 'Money and refunds',
                'summary' => 'Where the money shows, what refunds itself, and what to do when '
                           . 'somebody asks for one.',
                'minutes' => 5,
                'published' => true,
                'body'    => [
                    ['note' => 'Only a superadmin sees money. If the numbers described here are not '
                             . 'in your menu, that is why.'],
                    ['h' => 'Where it shows'],
                    ['p' => 'The Dashboard\'s "Revenue, 30 days" counts payments that succeeded in '
                          . 'the last thirty days. It excludes sample data and it excludes anything '
                          . 'refunded. Advertising shows what placement has sold and what the '
                          . 'inventory is worth if it sold out.'],
                    ['h' => 'The refund that happens by itself'],
                    ['p' => 'A job that gets no quotes inside the refund window is refunded '
                          . 'automatically overnight, and the homeowner is emailed. Nobody has to '
                          . 'do anything, and nobody has to notice. This is a promise made in '
                          . 'writing on the pricing page and in the terms, so it is not optional.'],
                    ['h' => 'What else runs overnight'],
                    ['ul' => [
                        'Refunding unanswered jobs',
                        'Expiring listings past their run',
                        'Tidying abandoned checkouts',
                        'Pruning old ad statistics',
                        'Ending placements whose subscription lapsed',
                        'Sending "how did it go?" review invitations',
                    ]],
                    ['warn' => 'If those stop happening, the refund promise quietly stops being '
                             . 'true. It is the one background job whose failure costs money and '
                             . 'trust at the same time.'],
                    ['h' => 'When somebody asks for a refund'],
                    ['p' => 'Refund it in Stripe. The site hears about it and updates the payment by '
                          . 'itself — you do not need to change anything in the admin, and you '
                          . 'should not edit payment records by hand. They are what reconciles '
                          . 'against Stripe later.'],
                    ['h' => 'Placement'],
                    ['p' => 'Tradespeople buy and cancel placement themselves, and manage their card '
                          . 'and invoices in Stripe\'s billing portal. There is nothing to do by '
                          . 'hand. If the last slot sells twice in the same moment, the second '
                          . 'payment is cancelled and refunded automatically and you get an email '
                          . 'about it.'],
                ],
            ],

            'running-the-site' => [
                'track'   => self::TRACK_STAFF,
                'title'   => 'Running the site: maintenance, the team, and deploys',
                'nav'     => 'Running the site',
                'summary' => 'Taking the site down safely, adding a colleague, and what happens '
                           . 'when a change ships.',
                'minutes' => 5,
                'published' => true,
                'body'    => [
                    ['h' => 'Maintenance mode'],
                    ['p' => 'Admin, then Maintenance. It puts a holding page up for visitors while '
                          . 'leaving the admin reachable, so you can keep working. Use it for '
                          . 'anything that changes the database.'],
                    ['note' => 'The switch is a file, not a database row. That is on purpose — it '
                             . 'has to work when the database is the thing that is broken.'],
                    ['h' => 'Adding somebody to the team'],
                    ['p' => 'Admin, then Team. Only a superadmin can reach it, because anybody who '
                          . 'can add a colleague can add themselves a second account.'],
                    ['ul' => [
                        'Moderator — works the queues. Applications, listings, jobs, reviews, '
                            . 'photos. Cannot see money or the team.',
                        'Manager — runs the market day to day, plus people and licensing. Still '
                            . 'cannot see money or the team.',
                        'Superadmin — everything, including money, the team, and deleting accounts.',
                    ]],
                    ['p' => 'Give the smallest role that covers the job. It is not distrust; it is '
                          . 'that a mistake made by somebody who cannot reach the money is a '
                          . 'smaller mistake.'],
                    ['p' => 'They get an email with a link to set their own password. Nobody here '
                          . 'ever knows it, including you. Names can be corrected later from the '
                          . 'same screen; email addresses cannot be changed, because changing '
                          . 'somebody\'s address is a change of identity and belongs behind proof '
                          . 'the new one is theirs.'],
                    ['h' => 'Alerts'],
                    ['p' => 'You are emailed about the things your role can act on — applications, '
                          . 'new jobs, reviews and photos waiting. Change somebody\'s role and '
                          . 'their alerts change with it. Nothing to configure.'],
                    ['h' => 'When a change ships'],
                    ['p' => 'Changes are pulled onto the server from the command line. Anything '
                          . 'touching the database wants maintenance mode on first, the migration '
                          . 'run, and the check script after. The check script is the one that '
                          . 'tells you whether the site is healthy; it only ever reads.'],
                ],
            ],

            'the-rules-that-do-not-bend' => [
                'track'   => self::TRACK_STAFF,
                'title'   => 'The rules that do not bend',
                'nav'     => 'The rules',
                'summary' => 'Six things that hold the whole thing up. Everything else is a '
                           . 'judgement call.',
                'minutes' => 3,
                'published' => true,
                'body'    => [
                    ['p' => 'Most of this job is judgement. These six are not.'],
                    ['h' => '1. The verified badge cannot be bought'],
                    ['p' => 'Not with placement, not with a favour, not for a business you know '
                          . 'personally. It means somebody checked a licence and an insurance '
                          . 'certificate. If that did not happen, the badge is a lie and the site '
                          . 'is worth nothing.'],
                    ['h' => '2. Reviews are not removed because a business asked'],
                    ['p' => 'Only if they break a rule in the terms. Point them at the reply.'],
                    ['h' => '3. Paid placement is always labelled'],
                    ['p' => 'Everywhere it appears, without exception. A directory where you cannot '
                          . 'tell what was paid for is an advert pretending to be a recommendation.'],
                    ['h' => '4. Nobody\'s details are sold'],
                    ['p' => 'Not a homeowner\'s, not a tradesperson\'s, not to anybody, ever. It is '
                          . 'the difference between this site and the ones it is competing with.'],
                    ['h' => '5. The refund promise is automatic'],
                    ['p' => 'A job nobody quoted is refunded without being asked. If that stops '
                          . 'working it is an emergency, not a backlog item.'],
                    ['h' => '6. Sample data is always labelled as sample data'],
                    ['p' => 'While the invented listings are on display, every one of them says so. '
                          . 'A visitor must never be unable to tell an example from a real business.'],
                ],
            ],
        ];
    }
}
