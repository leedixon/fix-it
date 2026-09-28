<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Academy;
use FixListed\Core\Database;

/**
 * Merges the lessons defined in code with the two things an owner can change
 * without a deploy: the video, and whether a lesson is published at all.
 *
 * Not a Repository subclass, because academy_lessons has no market_id. A
 * lesson is the same lesson in every market — the tenant scope exists to stop
 * one market's data leaking into another's, and there is no data here to
 * leak.
 */
final class AcademyRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Every lesson in a track, with overrides applied, published only.
     *
     * @return array<string,array<string,mixed>>
     */
    public function track(string $track, bool $includeUnpublished = false): array
    {
        $out = [];
        foreach (Academy::inTrack($track) as $slug => $lesson) {
            $merged = $this->apply($slug, $lesson);
            if ($includeUnpublished || $merged['published']) {
                $out[$slug] = $merged;
            }
        }

        return $out;
    }

    /** @return array<string,mixed>|null */
    public function lesson(string $slug, bool $includeUnpublished = false): ?array
    {
        $lesson = Academy::find($slug);
        if ($lesson === null) {
            return null;
        }

        $merged = $this->apply($slug, $lesson);

        return ($includeUnpublished || $merged['published']) ? $merged : null;
    }

    /**
     * @param array<string,mixed> $lesson
     * @return array<string,mixed>
     */
    private function apply(string $slug, array $lesson): array
    {
        $row = $this->overrides()[$slug] ?? null;

        $lesson['slug']  = $slug;
        $lesson['video'] = self::embeddable((string) ($row['video_url'] ?? ''));
        // NULL means "whatever the code says". Only an explicit 0 or 1 wins,
        // so a deploy cannot silently republish something somebody pulled.
        if ($row !== null && $row['published'] !== null) {
            $lesson['published'] = (bool) $row['published'];
        }

        return $lesson;
    }

    /** @return array<string,array<string,mixed>> */
    private function overrides(): array
    {
        static $rows = null;
        if ($rows === null) {
            $rows = [];
            foreach ($this->db->all('SELECT slug, video_url, published FROM academy_lessons') as $r) {
                $rows[(string) $r['slug']] = $r;
            }
        }

        return $rows;
    }

    /**
     * Turns a pasted video link into an embed URL, or nothing.
     *
     * An allowlist of hosts, and a URL this method builds itself rather than
     * one it was handed. The alternative is putting a stranger's string into
     * an iframe src on a public page, and "the only person who can paste it
     * is the owner" stops being true the first time somebody's admin
     * password is guessed.
     */
    public static function embeddable(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        $host  = strtolower((string) ($parts['host'] ?? ''));
        $host  = preg_replace('/^www\./', '', $host) ?? $host;

        // YouTube, in its three shapes.
        if ($host === 'youtu.be') {
            $id = trim((string) ($parts['path'] ?? ''), '/');
            return self::youtube($id);
        }
        if ($host === 'youtube.com' || $host === 'm.youtube.com') {
            parse_str((string) ($parts['query'] ?? ''), $q);
            $id = (string) ($q['v'] ?? '');
            if ($id === '' && str_starts_with((string) ($parts['path'] ?? ''), '/embed/')) {
                $id = substr((string) $parts['path'], 7);
            }
            return self::youtube($id);
        }

        // Vimeo.
        if ($host === 'vimeo.com' || $host === 'player.vimeo.com') {
            if (preg_match('#(\d{6,})#', (string) ($parts['path'] ?? ''), $m)) {
                return 'https://player.vimeo.com/video/' . $m[1];
            }
        }

        return '';
    }

    private static function youtube(string $id): string
    {
        // Rebuilt from a strict id, never from the string that was pasted.
        return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) === 1
            ? 'https://www.youtube-nocookie.com/embed/' . $id
            : '';
    }

    /** Saves what an owner changed. A blank video clears it. */
    public function save(string $slug, string $videoUrl, ?bool $published, ?int $byUserId): void
    {
        if (Academy::find($slug) === null) {
            return;
        }

        $this->db->affected(
            'INSERT INTO academy_lessons (slug, video_url, published, updated_by)
             VALUES (:slug, :video, :pub, :by)
             ON DUPLICATE KEY UPDATE video_url = :video2, published = :pub2, updated_by = :by2',
            [
                'slug'   => $slug,
                'video'  => $videoUrl === '' ? null : $videoUrl,
                'video2' => $videoUrl === '' ? null : $videoUrl,
                'pub'    => $published === null ? null : (int) $published,
                'pub2'   => $published === null ? null : (int) $published,
                'by'     => $byUserId,
                'by2'    => $byUserId,
            ],
        );
    }
}
