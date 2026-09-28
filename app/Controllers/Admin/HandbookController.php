<?php
declare(strict_types=1);

namespace FixListed\Controllers\Admin;

use FixListed\Core\Academy;
use FixListed\Core\AdminController;
use FixListed\Core\NotFound;
use FixListed\Core\Response;
use FixListed\Core\Session;
use FixListed\Repositories\AcademyRepository;

/**
 * The staff handbook, and the manager for the public academy's videos.
 *
 * Two jobs on one controller because they are the same content store seen
 * from two sides: the handbook is the staff track rendered for reading, and
 * the manager is every track listed for editing.
 *
 * The handbook is not a copy of anything. It is written against the screens
 * as they are, names them, and is the answer to "where is that" — which is
 * the question somebody handed a login actually has, and the one no amount
 * of careful interface design answers on its own.
 */
final class HandbookController extends AdminController
{
    public function index(): Response
    {
        if ($denied = $this->guardCan('admin.access')) {
            return $denied;
        }

        return $this->page('admin/handbook', [
            'title'   => 'Handbook — Fix Listed admin',
            'lessons' => (new AcademyRepository($this->db))->track(Academy::TRACK_STAFF, true),
        ]);
    }

    public function lesson(string $slug): Response
    {
        if ($denied = $this->guardCan('admin.access')) {
            return $denied;
        }

        $repo   = new AcademyRepository($this->db);
        $lesson = $repo->lesson($slug, true);

        // Staff track only. A public lesson has a public page; serving it
        // here too would mean two URLs for one thing and two places to
        // notice it is out of date.
        if ($lesson === null || $lesson['track'] !== Academy::TRACK_STAFF) {
            throw new NotFound('admin/handbook/' . $slug);
        }

        return $this->page('admin/handbook_lesson', [
            'title'    => $lesson['title'] . ' — Fix Listed admin',
            'lesson'   => $lesson,
            'siblings' => $repo->track(Academy::TRACK_STAFF, true),
        ]);
    }

    /**
     * Every lesson, with somewhere to put a video and a switch to pull one.
     *
     * The words are not editable here, and that is the point rather than an
     * omission: a lesson about a screen should change in the same commit as
     * the screen. What an owner needs between deploys is a video once it is
     * recorded, and a way to take a wrong lesson down in ten seconds.
     */
    public function manage(): Response
    {
        if ($denied = $this->guardCan('markets.manage')) {
            return $denied;
        }

        $repo = new AcademyRepository($this->db);

        return $this->page('admin/academy', [
            'title'  => 'Academy — Fix Listed admin',
            'tracks' => [
                'Homeowners'   => $repo->track(Academy::TRACK_HOMEOWNER, true),
                'Tradespeople' => $repo->track(Academy::TRACK_PRO, true),
                'Handbook'     => $repo->track(Academy::TRACK_STAFF, true),
            ],
        ]);
    }

    public function save(string $slug): Response
    {
        if ($denied = $this->guardCan('markets.manage')) {
            return $denied;
        }
        if (!$this->checkCsrf()) {
            Session::flash('bad', 'That form expired. Nothing was saved.');
            return Response::redirect('/admin/academy');
        }

        if (Academy::find($slug) === null) {
            Session::flash('bad', 'No such lesson.');
            return Response::redirect('/admin/academy');
        }

        $url  = trim((string) $this->request->input('video_url', ''));
        $repo = new AcademyRepository($this->db);

        // Refused loudly rather than stored and silently ignored at render
        // time, which would look exactly like the video being broken.
        if ($url !== '' && AcademyRepository::embeddable($url) === '') {
            Session::flash('bad', 'That link is not one we can embed. YouTube or Vimeo, '
                . 'pasted straight from the address bar.');
            return Response::redirect('/admin/academy');
        }

        $repo->save(
            $slug,
            $url,
            $this->request->input('published') === null ? false : true,
            $this->auth->id(),
        );

        $this->record('academy.updated', 'lesson', null, ['slug' => $slug]);
        Session::flash('ok', 'Saved.');

        return Response::redirect('/admin/academy');
    }
}
