<?php
declare(strict_types=1);

namespace FixListed\Repositories;

use FixListed\Core\Database;
use FixListed\Core\Repository;
use RuntimeException;

/**
 * Tradespeople applying to be listed, and the queue that reviews them.
 *
 * An application writes across four tables and must not half-land: a user row
 * with no profile is an account nobody can use, and a profile with no counties
 * is invisible to the directory that is supposed to show it. So the whole
 * thing is one transaction.
 *
 * Profiles are created as 'pending_review' and are not visible anywhere public
 * until an administrator approves them. That is what makes the site's
 * "licence and insurance checked" claim true rather than decorative.
 */
final class ProApplicationRepository extends Repository
{
    protected function table(): string
    {
        return 'pro_profiles';
    }

    /**
     * @param array<string,mixed> $data
     * @return array{pro_id:int,user_id:int,slug:string}
     */
    public function create(array $data): array
    {
        return $this->db->transaction(function (Database $db) use ($data): array {
            $marketId = $this->scope->marketId;

            // An applicant who already has an account keeps it. Re-applying
            // must not create a second user with the same address, which the
            // unique index would reject anyway — with a fatal error rather
            // than a sentence the applicant can act on.
            $existing = $db->one(
                'SELECT id, role FROM users WHERE email = :email LIMIT 1',
                ['email' => $data['email']],
            );

            if ($existing !== null) {
                $userId = (int) $existing['id'];
                $hasProfile = $db->value(
                    'SELECT COUNT(*) FROM pro_profiles WHERE user_id = :id',
                    ['id' => $userId],
                );
                if ((int) $hasProfile > 0) {
                    throw new RuntimeException('already_applied');
                }
                // Promote to 'pro', never demote. An administrator who lists
                // their own business was being dropped to 'pro' by this
                // update, which locked them out of /admin on their very next
                // request — every admin page 404s for a non-admin, so it
                // looked like the site was broken rather than like a
                // privilege change. Roles only ever go up here.
                $db->affected(
                    "UPDATE users
                        SET role = IF(role = 'homeowner', 'pro', role),
                            first_name = :first, last_name = :last, phone = :phone,
                            market_id = COALESCE(market_id, :market_id)
                      WHERE id = :id",
                    [
                        'first' => $data['first_name'], 'last' => $data['last_name'],
                        'phone' => $data['phone'], 'market_id' => $marketId, 'id' => $userId,
                    ],
                );
            } else {
                $userId = $db->insert(
                    "INSERT INTO users (market_id, role, email, first_name, last_name, phone, status)
                     VALUES (:market_id, 'pro', :email, :first, :last, :phone, 'active')",
                    [
                        'market_id' => $marketId, 'email' => $data['email'],
                        'first' => $data['first_name'], 'last' => $data['last_name'],
                        'phone' => $data['phone'],
                    ],
                );
            }

            $slug = $this->uniqueSlug(
                $db,
                $data['business_name'] !== '' ? $data['business_name'] : $data['first_name'] . ' ' . $data['last_name'],
                (int) $marketId,
            );

            $proId = $db->insert(
                "INSERT INTO pro_profiles
                    (market_id, user_id, slug, business_name, headline, bio, hourly_rate_cents,
                     years_experience, home_county_id, base_zip, license_number, license_state,
                     insurance_carrier, status)
                 VALUES
                    (:market_id, :user_id, :slug, :business_name, :headline, :bio, :rate,
                     :years, :county_id, :zip, :license_number, :license_state,
                     :insurance_carrier, 'pending_review')",
                [
                    'market_id' => $marketId, 'user_id' => $userId, 'slug' => $slug,
                    'business_name' => $data['business_name'], 'headline' => $data['headline'],
                    'bio' => $data['bio'], 'rate' => $data['hourly_rate_cents'],
                    'years' => $data['years_experience'], 'county_id' => $data['home_county_id'],
                    'zip' => $data['zip'], 'license_number' => $data['license_number'],
                    'license_state' => $data['license_state'],
                    'insurance_carrier' => $data['insurance_carrier'],
                ],
            );

            $first = true;
            foreach ($data['trade_ids'] as $tradeId) {
                $db->affected(
                    'INSERT IGNORE INTO pro_trades (pro_id, trade_id, is_primary) VALUES (:pro, :trade, :primary)',
                    ['pro' => $proId, 'trade' => (int) $tradeId, 'primary' => $first ? 1 : 0],
                );
                $first = false;
            }

            foreach ($data['county_ids'] as $countyId) {
                $db->affected(
                    'INSERT IGNORE INTO pro_county_areas (pro_id, county_id, market_id)
                     VALUES (:pro, :county, :market_id)',
                    ['pro' => $proId, 'county' => (int) $countyId, 'market_id' => $marketId],
                );
            }

            $db->insert(
                "INSERT INTO moderation_items (market_id, subject_type, subject_id, source, reason)
                 VALUES (:market_id, 'pro_profile', :pro, 'auto', :reason)",
                [
                    'market_id' => $marketId, 'pro' => $proId,
                    'reason' => 'New listing application — licence and insurance need checking',
                ],
            );

            return ['pro_id' => (int) $proId, 'user_id' => $userId, 'slug' => $slug];
        });
    }

    /**
     * A URL-safe slug that is not already taken in this market.
     *
     * Two Rockford plumbers both trading as "Rockford Plumbing" is not a
     * hypothetical, and the unique index would otherwise turn the second
     * application into a 500.
     */
    private function uniqueSlug(Database $db, string $name, int $marketId): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)) ?? '', '-');
        $base = $base === '' ? 'tradesperson' : mb_substr($base, 0, 120);

        $slug = $base;
        for ($n = 2; $n < 100; $n++) {
            $taken = $db->value(
                'SELECT COUNT(*) FROM pro_profiles WHERE market_id = :m AND slug = :s',
                ['m' => $marketId, 's' => $slug],
            );
            if ((int) $taken === 0) {
                return $slug;
            }
            $slug = $base . '-' . $n;
        }
        return $base . '-' . bin2hex(random_bytes(3));
    }

    /** Applications waiting on a decision, oldest first — a queue, not a feed. */
    public function pending(int $limit = 100): array
    {
        return $this->scopedAll(
            "SELECT p.id, p.slug, p.business_name, p.headline, p.years_experience,
                    p.hourly_rate_cents, p.license_number, p.license_state,
                    p.insurance_carrier, p.created_at,
                    u.email, u.phone, u.first_name, u.last_name,
                    co.short_name AS home_county
               FROM pro_profiles p
               JOIN users u ON u.id = p.user_id
               LEFT JOIN counties co ON co.id = p.home_county_id
              WHERE p.market_id = :market_id
                AND p.status = 'pending_review'
              ORDER BY p.created_at ASC
              LIMIT {$limit}"
        );
    }

    public function find(int $proId): ?array
    {
        return $this->scopedOne(
            'SELECT p.*, u.email, u.phone, u.first_name, u.last_name,
                    co.short_name AS home_county
               FROM pro_profiles p
               JOIN users u ON u.id = p.user_id
               LEFT JOIN counties co ON co.id = p.home_county_id
              WHERE p.market_id = :market_id AND p.id = :id
              LIMIT 1',
            ['id' => $proId],
        );
    }

    public function countPending(): int
    {
        return (int) $this->scopedValue(
            "SELECT COUNT(*) FROM pro_profiles
              WHERE market_id = :market_id AND status = 'pending_review'"
        );
    }

    /**
     * Approve, and record what was actually verified.
     *
     * The verified timestamps are set from what the administrator ticked, not
     * automatically: the badge on the profile says a person checked the
     * document, so a person has to say they did.
     */
    public function approve(int $proId, int $adminId, bool $licenceOk, bool $insuranceOk): void
    {
        $this->db->transaction(function (Database $db) use ($proId, $adminId, $licenceOk, $insuranceOk): void {
            $db->affected(
                "UPDATE pro_profiles
                    SET status = 'active',
                        published_at = COALESCE(published_at, NOW()),
                        license_verified_at   = :lic,
                        license_verified_by   = :by,
                        insurance_verified_at = :ins
                  WHERE id = :id AND market_id = :market_id",
                [
                    'lic' => $licenceOk ? date('Y-m-d H:i:s') : null,
                    'by'  => $licenceOk ? $adminId : null,
                    'ins' => $insuranceOk ? date('Y-m-d H:i:s') : null,
                    'id' => $proId, 'market_id' => $this->scope->marketId,
                ],
            );
            $this->resolve($db, $proId, $adminId, 'approved', 'Approved and published');
        });
    }

    public function reject(int $proId, int $adminId, string $note): void
    {
        $this->db->transaction(function (Database $db) use ($proId, $adminId, $note): void {
            $db->affected(
                "UPDATE pro_profiles SET status = 'suspended'
                  WHERE id = :id AND market_id = :market_id",
                ['id' => $proId, 'market_id' => $this->scope->marketId],
            );
            $this->resolve($db, $proId, $adminId, 'removed', $note !== '' ? $note : 'Rejected');
        });
    }

    public function setStatus(int $proId, string $status): void
    {
        $this->scopedAffected(
            'UPDATE pro_profiles SET status = :status WHERE id = :id AND market_id = :market_id',
            ['status' => $status, 'id' => $proId],
        );
    }

    private function resolve(Database $db, int $proId, int $adminId, string $status, string $note): void
    {
        $db->affected(
            "UPDATE moderation_items
                SET status = :status, resolved_by = :by, resolved_at = NOW(), resolution_note = :note
              WHERE subject_type = 'pro_profile' AND subject_id = :id AND status = 'open'",
            ['status' => $status, 'by' => $adminId, 'note' => mb_substr($note, 0, 255), 'id' => $proId],
        );
    }
}
