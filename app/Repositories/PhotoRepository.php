<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Repository;

/**
 * Photographs of a tradesperson's work, and their logo.
 *
 * pro_photos was designed in the first build and never written to. The
 * moderation status it carries is the reason it matters: these are pictures
 * uploaded by strangers and published under a business name on a public
 * directory, and one wrong image is a problem for the tradesperson, the
 * homeowner whose kitchen it is, and Fix Listed at once.
 *
 * Files never sit in the document root. They live in storage/uploads and are
 * served by a route that re-checks the status on every request — so a photo
 * that is rejected, or whose listing is suspended, stops being reachable the
 * moment that happens rather than whenever somebody remembers to delete it.
 */
final class PhotoRepository extends Repository
{
    /** Per business. Enough to show the work, few enough to stay curated. */
    public const MAX_PER_PRO = 12;

    protected function table(): string
    {
        return 'pro_photos';
    }

    public function add(int $proId, string $path, int $width, int $height, int $bytes): int
    {
        return (int) $this->db->insert(
            "INSERT INTO pro_photos (pro_id, market_id, path, width, height, bytes, status, sort_order)
             VALUES (:pro, :market, :path, :w, :h, :bytes, 'pending_review',
                     COALESCE((SELECT m FROM (SELECT MAX(sort_order) + 1 AS m
                                                FROM pro_photos WHERE pro_id = :pro2) t), 0))",
            [
                'pro'    => $proId,
                'pro2'   => $proId,
                'market' => $this->scope->marketId,
                'path'   => $path,
                'w'      => $width,
                'h'      => $height,
                'bytes'  => $bytes,
            ],
        );
    }

    public function countFor(int $proId): int
    {
        return (int) $this->scopedValue(
            "SELECT COUNT(*) FROM pro_photos
              WHERE pro_id = :pro AND market_id = :market_id AND status <> 'rejected'
                AND is_demo = 0",
            ['pro' => $proId],
        );
    }

    /**
     * Everything a pro has uploaded, whatever state it is in.
     *
     * Unlike reviews, a pro does see their own pending photos — it is their
     * picture, they know what it is, and a gallery that silently swallows an
     * upload until a moderator gets to it is one people upload twice.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forOwner(int $proId): array
    {
        return $this->scopedAll(
            "SELECT id, path, caption, width, height, status, sort_order, created_at
               FROM pro_photos
              WHERE pro_id = :pro AND market_id = :market_id AND status <> 'rejected'
                AND is_demo = 0
              ORDER BY sort_order, id",
            ['pro' => $proId],
        );
    }

    /**
     * What the public sees: approved photos on a live listing.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forPublic(int $proId, int $limit = 12): array
    {
        return $this->scopedAll(
            "SELECT p.id, p.path, p.caption, p.width, p.height
               FROM pro_photos p
               JOIN pro_profiles pr ON pr.id = p.pro_id AND pr.status = 'active'
              WHERE p.pro_id = :pro AND p.market_id = :market_id
                AND p.status = 'approved'
                -- The seed writes seven of these pointing at files that have
                -- never existed. Drawing them is three broken images on a
                -- profile that has never had a photo.
                AND p.is_demo = 0
              ORDER BY p.sort_order, p.id
              LIMIT {$limit}",
            ['pro' => $proId],
        );
    }

    /**
     * One photo, for the route that serves the bytes.
     *
     * $publicOnly is what the difference between a visitor and the owner
     * comes down to: a visitor may only ever fetch an approved photo on an
     * active listing, and the check happens on every request rather than
     * once at upload time.
     *
     * @return array<string,mixed>|null
     */
    public function find(int $id, bool $publicOnly = true): ?array
    {
        $gate = $publicOnly
            ? "AND p.status = 'approved' AND pr.status = 'active'"
            : '';

        return $this->scopedOne(
            "SELECT p.*, pr.slug, pr.user_id
               FROM pro_photos p
               JOIN pro_profiles pr ON pr.id = p.pro_id
              WHERE p.id = :id AND p.market_id = :market_id {$gate}
              LIMIT 1",
            ['id' => $id],
        );
    }

    /** Removes a photo the pro owns. Returns the stored path, to delete. */
    public function removeOwned(int $id, int $proId): ?string
    {
        $row = $this->scopedOne(
            'SELECT path FROM pro_photos WHERE id = :id AND pro_id = :pro AND market_id = :market_id',
            ['id' => $id, 'pro' => $proId],
        );
        if ($row === null) {
            return null;
        }

        $this->scopedAffected(
            'DELETE FROM pro_photos WHERE id = :id AND pro_id = :pro AND market_id = :market_id',
            ['id' => $id, 'pro' => $proId],
        );

        return (string) $row['path'];
    }

    /** @return array<int,array<string,mixed>> photos waiting on a moderator */
    public function awaitingModeration(): array
    {
        return $this->scopedAll(
            "SELECT p.id, p.path, p.caption, p.created_at, p.pro_id,
                    pr.business_name, pr.slug
               FROM pro_photos p
               JOIN pro_profiles pr ON pr.id = p.pro_id
              WHERE p.market_id = :market_id AND p.status = 'pending_review'
                AND p.is_demo = 0
              ORDER BY p.created_at ASC",
        );
    }

    public function moderate(int $id, string $status): bool
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return false;
        }

        return $this->scopedAffected(
            'UPDATE pro_photos SET status = :s WHERE id = :id AND market_id = :market_id',
            ['s' => $status, 'id' => $id],
        ) > 0;
    }

    public function pendingCount(): int
    {
        return (int) $this->scopedValue(
            "SELECT COUNT(*) FROM pro_photos
              WHERE market_id = :market_id AND status = 'pending_review' AND is_demo = 0",
        );
    }
}
