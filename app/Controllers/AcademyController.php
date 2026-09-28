<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Academy;
use FixListed\Core\Controller;
use FixListed\Core\NotFound;
use FixListed\Core\Response;
use FixListed\Core\Seo;
use FixListed\Repositories\AcademyRepository;

/**
 * The public academy — /academy.
 *
 * Two audiences on one shelf: a homeowner who has a broken thing and has
 * never used this site, and a tradesperson deciding whether it is worth their
 * time. Both get answers to questions they would actually type, which is also
 * why these pages earn search traffic the directory pages cannot. "How do I
 * check a plumber's licence in Illinois" is a real search; a page listing
 * plumbers is not an answer to it.
 *
 * The staff handbook shares the lesson store and is refused here outright.
 * Not hidden, not unlinked — refused, in the one method that could serve it,
 * because "nobody will guess the URL" is not access control.
 */
final class AcademyController extends Controller
{
    public function index(): Response
    {
        $repo = new AcademyRepository($this->db);

        return $this->page('site/academy', [
            'title'       => 'The Fix Listed Academy',
            'description' => 'Short, plain guides to hiring a tradesperson in Northwest Illinois '
                           . 'and to getting work as one. Free, and no sign-up.',
            'homeowners'  => $repo->track(Academy::TRACK_HOMEOWNER),
            'pros'        => $repo->track(Academy::TRACK_PRO),
            'crumbs'      => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Academy'],
            ],
            'analytics'   => $this->analytics('site/academy'),
        ]);
    }

    public function lesson(string $slug): Response
    {
        $lesson = (new AcademyRepository($this->db))->lesson($slug);

        // A staff lesson has no public URL. Refused here rather than left
        // unlinked, because an unlinked page is a published page.
        if ($lesson === null || !Academy::isPublicTrack((string) $lesson['track'])) {
            throw new NotFound('academy/' . $slug);
        }

        $siblings = (new AcademyRepository($this->db))->track((string) $lesson['track']);

        return $this->page('site/academy_lesson', [
            'title'       => $lesson['title'] . ' — Fix Listed',
            'description' => $lesson['summary'],
            'lesson'      => $lesson,
            'siblings'    => $siblings,
            'crumbs'      => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Academy', 'href' => '/academy'],
                ['label' => $lesson['nav']],
            ],
            'analytics'   => $this->analytics('site/academy_lesson', ['lesson' => $slug]),
            /*
             * Marked up as an Article rather than a HowTo or an FAQPage.
             *
             * HowTo was retired as a rich result and FAQPage is now shown for
             * almost nobody, so claiming either buys nothing and risks a
             * manual action if the shape does not match. Article is what
             * these are, and the fields below are all things visibly on the
             * page — which is the rule this site holds everywhere.
             */
            'jsonLd'      => [[
                '@type'            => 'Article',
                '@id'              => abs_url('/academy/' . $slug) . '#article',
                'headline'         => $lesson['title'],
                'description'      => $lesson['summary'],
                'url'              => abs_url('/academy/' . $slug),
                'inLanguage'       => 'en-US',
                'isAccessibleForFree' => true,
                'publisher'        => ['@id' => Seo::organizationId()],
                'about'            => $lesson['track'] === Academy::TRACK_HOMEOWNER
                    ? 'Hiring a tradesperson'
                    : 'Running a trade business',
            ]],
        ]);
    }
}
