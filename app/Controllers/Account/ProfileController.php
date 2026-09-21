<?php
declare(strict_types=1);

namespace FixListed\Controllers\Account;

use FixListed\Core\AccountController;
use FixListed\Core\Database;
use FixListed\Core\Response;
use FixListed\Core\Session;
use FixListed\Core\Validator;
use FixListed\Repositories\GeographyRepository;
use FixListed\Repositories\TradeRepository;

/**
 * A tradesperson editing their own listing.
 *
 * Everything here is theirs to change freely — wording, rate, counties, trades
 * — with one exception. A licence number is a claim the site verified, so
 * changing it drops the verified badge and puts the profile back in the review
 * queue. Otherwise the badge could be earned with one number and worn with
 * another.
 */
final class ProfileController extends AccountController
{
    public function edit(): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        $profile = $this->profile();
        if ($profile === null) {
            return Response::redirect('/my');
        }

        return $this->page('account/profile', [
            'title'       => 'Your listing — Fix Listed',
            'trades'      => (new TradeRepository($this->db))->all(),
            'counties'    => (new GeographyRepository($this->db, $this->scope))->counties(),
            'myTrades'    => $this->myIds('pro_trades', 'trade_id', (int) $profile['id']),
            'myCounties'  => $this->myIds('pro_county_areas', 'county_id', (int) $profile['id']),
            'old'         => [],
            'errors'      => [],
        ]);
    }

    public function update(): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        $profile = $this->profile();
        if ($profile === null) {
            return Response::redirect('/my');
        }
        if (!$this->checkCsrf()) {
            Session::flash('bad', 'That form expired. Nothing was saved.');
            return Response::redirect('/my/listing');
        }

        $v = new Validator($this->request->body);
        $v->required('headline', 'One line describing you')->max('headline', 160, 'Headline')
          ->required('bio', 'About your business')->min('bio', 60, 'About your business')
          ->max('business_name', 160, 'Business name')
          ->requiredAny('trades', 'At least one trade')
          ->requiredAny('counties', 'At least one county');

        $validTrades   = array_map('intval', array_column((new TradeRepository($this->db))->all(), 'id'));
        $validCounties = array_map('intval', array_column((new GeographyRepository($this->db, $this->scope))->counties(), 'id'));
        $tradeIds      = array_values(array_intersect(array_map('intval', $v->values('trades')), $validTrades));
        $countyIds     = array_values(array_intersect(array_map('intval', $v->values('counties')), $validCounties));

        if ($tradeIds === [])  { $v->fail('trades', 'Choose at least one trade.'); }
        if ($countyIds === []) { $v->fail('counties', 'Choose at least one county.'); }

        if (!$v->passes()) {
            return $this->page('account/profile', [
                'title'      => 'Your listing — Fix Listed',
                'trades'     => (new TradeRepository($this->db))->all(),
                'counties'   => (new GeographyRepository($this->db, $this->scope))->counties(),
                'myTrades'   => $tradeIds,
                'myCounties' => $countyIds,
                'old'        => $this->request->body,
                'errors'     => $v->errors(),
            ], 422);
        }

        $newLicence = $v->value('license_number');
        $licenceChanged = $newLicence !== (string) $profile['license_number'];

        $this->db->transaction(function (Database $db) use ($profile, $v, $tradeIds, $countyIds, $newLicence, $licenceChanged): void {
            $db->affected(
                'UPDATE pro_profiles
                    SET business_name = :business, headline = :headline, bio = :bio,
                        hourly_rate_cents = :rate, years_experience = :years,
                        base_zip = :zip, insurance_carrier = :insurance,
                        license_number = :licence,
                        home_county_id = :home_county,
                        license_verified_at = :lic_verified,
                        status = :status
                  WHERE id = :id AND market_id = :market_id',
                [
                    'business' => $v->value('business_name'),
                    'headline' => $v->value('headline'),
                    'bio' => $v->value('bio'),
                    'rate' => $v->money('hourly_rate'),
                    'years' => $v->int('years_experience') ?? 0,
                    'zip' => $v->value('zip'),
                    'insurance' => $v->value('insurance_carrier'),
                    'licence' => $newLicence,
                    'home_county' => $countyIds[0],
                    // Changing the number drops the badge it was granted for.
                    'lic_verified' => $licenceChanged ? null : $profile['license_verified_at'],
                    'status' => $licenceChanged && $profile['status'] === 'active'
                        ? 'pending_review' : $profile['status'],
                    'id' => $profile['id'], 'market_id' => $this->scope->marketId,
                ],
            );

            // Replaced wholesale rather than diffed: the form sends the full
            // set, and working out which rows to add and remove is more code
            // and more ways to be wrong than deleting and re-inserting six.
            $db->affected('DELETE FROM pro_trades WHERE pro_id = :id', ['id' => $profile['id']]);
            $first = true;
            foreach ($tradeIds as $tradeId) {
                $db->affected(
                    'INSERT IGNORE INTO pro_trades (pro_id, trade_id, is_primary) VALUES (:p, :t, :pr)',
                    ['p' => $profile['id'], 't' => $tradeId, 'pr' => $first ? 1 : 0],
                );
                $first = false;
            }

            $db->affected('DELETE FROM pro_county_areas WHERE pro_id = :id', ['id' => $profile['id']]);
            foreach ($countyIds as $countyId) {
                $db->affected(
                    'INSERT IGNORE INTO pro_county_areas (pro_id, county_id, market_id) VALUES (:p, :c, :m)',
                    ['p' => $profile['id'], 'c' => $countyId, 'm' => $this->scope->marketId],
                );
            }

            if ($licenceChanged) {
                $db->insert(
                    "INSERT INTO moderation_items (market_id, subject_type, subject_id, source, reason)
                     VALUES (:m, 'pro_profile', :p, 'auto', :reason)",
                    [
                        'm' => $this->scope->marketId, 'p' => $profile['id'],
                        'reason' => 'Licence number changed — needs re-checking',
                    ],
                );
            }
        });

        Session::flash('ok', $licenceChanged
            ? 'Saved. Because the licence number changed, your listing goes back for a quick check before the verified badge returns.'
            : 'Saved. Your listing is updated.');

        return Response::redirect('/my/listing');
    }

    /** @return array<int,int> */
    private function myIds(string $table, string $column, int $proId): array
    {
        return array_map('intval', array_column(
            $this->db->all("SELECT {$column} FROM {$table} WHERE pro_id = :id", ['id' => $proId]),
            $column,
        ));
    }
}
