<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Controller;
use FixListed\Core\NotFound;
use FixListed\Core\Response;
use FixListed\Repositories\PhotoRepository;

/**
 * Serves an uploaded image — /img/{id}.
 *
 * The files live in storage/uploads, outside the document root, and are read
 * out by PHP rather than served by Apache. That costs a process per image and
 * buys the thing that matters: the status is checked on every single request.
 *
 * A photo waiting on a moderator has no URL that works. Reject it and the URL
 * stops working. Suspend the whole listing and every photo on it stops with
 * it. If the files sat in public/ instead, each of those would be a delete
 * somebody has to remember, and the failure mode is a rejected photograph of
 * somebody's kitchen staying reachable to anyone who saved the link.
 *
 * The volume here is a few dozen images on a directory nobody is hammering.
 * When that stops being true, the answer is a cache in front, not moving the
 * files somewhere they cannot be withdrawn.
 */
final class ImageController extends Controller
{
    public function show(string $id): Response
    {
        $photos = new PhotoRepository($this->db, $this->scope);
        $photo  = $photos->find((int) $id);

        // The owner may look at their own while it waits for a moderator —
        // a gallery that shows nothing until approval is one people upload
        // to twice, assuming the first attempt failed.
        if ($photo === null) {
            $photo = $photos->find((int) $id, false);
            $me    = $this->auth->user();
            $mine  = $photo !== null
                && $me !== null
                && (int) $photo['user_id'] === (int) $me['id'];

            if (!$mine && !$this->auth->isStaff()) {
                throw new NotFound('img/' . $id);
            }
        }

        $file = self::dir((int) $photo['pro_id']) . '/' . basename((string) $photo['path']);
        if (!is_file($file)) {
            throw new NotFound('img/' . $id);
        }

        return Response::file(
            $file,
            str_ends_with($file, '.png') ? 'image/png' : 'image/jpeg',
            // Private, because an approved photo can become a rejected one.
            // A year in a shared cache would outlive the moderation that is
            // the entire reason this route exists.
            'private, max-age=86400',
        );
    }

    /**
     * A business's logo — /img/logo/{proId}.
     *
     * Separate from show() because a logo is not a row in pro_photos: it is
     * a column on the profile, it is not moderated, and it is reachable for
     * exactly as long as the listing is live.
     */
    public function logo(string $proId): Response
    {
        $row = $this->db->one(
            "SELECT id, logo_path, status FROM pro_profiles
              WHERE id = :id AND market_id = :market LIMIT 1",
            ['id' => (int) $proId, 'market' => $this->scope->marketId],
        );

        if ($row === null || empty($row['logo_path'])) {
            throw new NotFound('img/logo/' . $proId);
        }

        // A suspended listing shows nothing, its logo included — unless you
        // are the person whose listing it is, looking at your own editor.
        if ((string) $row['status'] !== 'active') {
            $me = $this->auth->user();
            $mine = $me !== null && (int) $this->db->value(
                'SELECT user_id FROM pro_profiles WHERE id = :id', ['id' => (int) $row['id']]
            ) === (int) $me['id'];
            if (!$mine && !$this->auth->isStaff()) {
                throw new NotFound('img/logo/' . $proId);
            }
        }

        $file = self::dir((int) $row['id']) . '/' . basename((string) $row['logo_path']);
        if (!is_file($file)) {
            throw new NotFound('img/logo/' . $proId);
        }

        return Response::file($file, 'image/png');
    }

    /** Where a business's files live. One folder each, so a purge is a folder. */
    public static function dir(int $proId): string
    {
        return BASE_PATH . '/storage/uploads/pro/' . $proId;
    }
}
