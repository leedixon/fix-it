<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Config;
use FixListed\Core\Controller;
use FixListed\Core\Csrf;
use FixListed\Core\Mailer;
use FixListed\Core\NotFound;
use FixListed\Core\Response;
use FixListed\Core\Session;
use FixListed\Core\Validator;
use FixListed\Repositories\JobAlertRepository;

/**
 * "Get these by email" — /jobs/alerts.
 *
 * That button used to point at /for-pros. The alerts themselves already
 * existed and worked; what did not exist was any way to receive them without
 * listing first. So the button promised a signup box and delivered a sales
 * page, and a tradesperson who wanted to see the work before committing had
 * nowhere to go.
 *
 * Confirmed opt-in. Not a legal requirement in the US, but this product IS
 * email: if the sending domain's reputation goes, the alerts stop arriving
 * and there is nothing left to sell. One unverified address typed by somebody
 * who mistyped their own is a spam complaint against a domain that has no
 * reputation to spare yet.
 */
final class JobAlertController extends Controller
{
    public function form(): Response
    {
        return $this->page('site/job_alerts', [
            'title'       => 'Get new jobs by email — Fix Listed',
            'description' => 'New jobs in your trade and your county, emailed as they are posted. '
                           . 'Free, and you do not need a listing.',
            'trades'      => $this->trades(),
            'counties'    => $this->counties(),
            'old'         => [],
            'errors'      => [],
            'crumbs'      => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Jobs board', 'href' => '/jobs'],
                ['label' => 'Email alerts'],
            ],
        ]);
    }

    public function subscribe(): Response
    {
        if (!Csrf::check($this->request->input('_csrf'))) {
            return Response::redirect('/jobs/alerts');
        }

        $body = $this->request->body;
        $v    = new Validator($body);

        $v->required('email', 'Email')->email('email')
          ->max('first_name', 80, 'First name')
          ->max('business_name', 160, 'Business name')
          ->requiredAny('trades', 'At least one trade')
          ->requiredAny('counties', 'At least one county');

        // Only ids that exist. A hand-posted form must not create rows
        // pointing at trades and counties this market does not have.
        $tradeIds  = $this->onlyKnown($v->values('trades'), $this->trades());
        $countyIds = $this->onlyKnown($v->values('counties'), $this->counties());

        if ($tradeIds === [] && $v->error('trades') === '') {
            $v->fail('trades', 'Pick at least one trade.');
        }
        if ($countyIds === [] && $v->error('counties') === '') {
            $v->fail('counties', 'Pick at least one county.');
        }

        // The honeypot the rest of the site uses. A bot fills every field.
        if (trim((string) ($body['website'] ?? '')) !== '') {
            return Response::redirect('/jobs/alerts/check-your-email');
        }

        if (!$v->passes()) {
            return $this->page('site/job_alerts', [
                'title'    => 'Get new jobs by email — Fix Listed',
                'trades'   => $this->trades(),
                'counties' => $this->counties(),
                'old'      => $body,
                'errors'   => $v->errors(),
                'crumbs'   => [
                    ['label' => 'Home', 'href' => '/'],
                    ['label' => 'Jobs board', 'href' => '/jobs'],
                    ['label' => 'Email alerts'],
                ],
            ], 422);
        }

        $alerts = new JobAlertRepository($this->db, $this->scope);
        $email  = mb_strtolower(trim((string) $v->value('email')));

        $result = $alerts->subscribe(
            $email,
            trim((string) $v->value('first_name')),
            trim((string) $v->value('business_name')),
            $tradeIds,
            $countyIds,
            $this->request->ip(),
        );

        $this->sendConfirmation($email, (string) $v->value('first_name'), (string) $result['token']);

        /*
         * The same page whether the address was new, already subscribed, or
         * already confirmed. "You are already on the list" tells whoever
         * typed it that the address has an account here, which is a free
         * membership check for anybody who wants one.
         */
        return Response::redirect('/jobs/alerts/check-your-email');
    }

    public function checkYourEmail(): Response
    {
        return $this->page('site/job_alerts_sent', [
            'title'   => 'Check your email — Fix Listed',
            'noindex' => true,
        ]);
    }

    public function confirm(string $token): Response
    {
        $alerts = new JobAlertRepository($this->db, $this->scope);
        $row    = $alerts->confirm($token);

        if ($row === null) {
            throw new NotFound('jobs/alerts/confirm');
        }

        return $this->page('site/job_alerts_done', [
            'title'   => 'You are on the list — Fix Listed',
            'noindex' => true,
            'mode'    => 'confirmed',
            'email'   => (string) $row['email'],
        ]);
    }

    /**
     * Stopping the alerts, for a subscriber or for a listed tradesperson.
     *
     * One route for both, because from the reader's side they are the same
     * message arriving in the same inbox and the difference is ours, not
     * theirs. A subscriber's link carries their row token; a listed pro's
     * carries a signature over their profile id — see Controller::signedFor.
     *
     * GET, and that is a deliberate exception to the rule that a GET must not
     * change anything. An unsubscribe link that needs a form and a button is
     * one people give up on and report as spam instead, which does far more
     * damage than a prefetcher unsubscribing somebody who can resubscribe in
     * one form.
     */
    public function unsubscribe(string $token): Response
    {
        $alerts = new JobAlertRepository($this->db, $this->scope);

        if ($alerts->unsubscribe($token) !== null) {
            return $this->page('site/job_alerts_done', [
                'title'   => 'Unsubscribed — Fix Listed',
                'noindex' => true,
                'mode'    => 'unsubscribed',
                'email'   => '',
            ]);
        }

        // A listed tradesperson: p<id>.<hmac>
        $proId = $this->proFromSignedToken($token);
        if ($proId !== null) {
            $this->db->affected(
                'UPDATE pro_profiles SET job_alerts_off = 1 WHERE id = :id AND market_id = :market',
                ['id' => $proId, 'market' => $this->scope->marketId],
            );

            return $this->page('site/job_alerts_done', [
                'title'   => 'Unsubscribed — Fix Listed',
                'noindex' => true,
                'mode'    => 'pro_unsubscribed',
                'email'   => '',
            ]);
        }

        throw new NotFound('jobs/alerts/unsubscribe');
    }

    /**
     * The signed unsubscribe token for a listed profile.
     *
     * HMAC over the id, keyed on app.key, rather than a stored token: there
     * is nothing to look up, nothing to expire, and no new table for a string
     * that only ever appears in an email footer.
     */
    public static function proToken(int $proId): string
    {
        return 'p' . $proId . '.' . hash_hmac(
            'sha256',
            'job-alerts:' . $proId,
            (string) Config::get('app.key', ''),
        );
    }

    private function proFromSignedToken(string $token): ?int
    {
        if (!preg_match('/^p(\d+)\.([0-9a-f]{64})$/', $token, $m)) {
            return null;
        }
        $proId = (int) $m[1];

        // hash_equals, not ===: a timing-safe compare on a value an attacker
        // supplies and can iterate.
        return hash_equals(self::proToken($proId), $token) ? $proId : null;
    }

    /**
     * @param array<int,string> $given
     * @param array<int,array<string,mixed>> $known
     * @return array<int,int>
     */
    private function onlyKnown(array $given, array $known): array
    {
        $ids = array_map('intval', array_column($known, 'id'));

        return array_values(array_filter(
            array_map('intval', $given),
            static fn (int $id): bool => in_array($id, $ids, true),
        ));
    }

    private function sendConfirmation(string $email, string $firstName, string $token): void
    {
        try {
            Mailer::fromConfig()->send(
                $email,
                'Confirm your Fix Listed job alerts',
                $this->view->render('emails.alert_confirm', [
                    'title'      => 'One click and the alerts start',
                    'preheader'  => 'Confirm this address to start getting jobs as they are posted.',
                    'name'       => $firstName,
                    'market'     => (string) $this->market['name'],
                    'confirmUrl' => abs_url('/jobs/alerts/confirm/' . $token),
                ], 'emails.layout'),
                "Confirm this address and we will email you new jobs in the trades and counties "
                . "you picked:\n\n" . abs_url('/jobs/alerts/confirm/' . $token) . "\n\n"
                . "If you did not ask for this, ignore it — nothing is sent until you confirm.\n",
            );
        } catch (\Throwable $e) {
            error_log('Alert confirmation failed for ' . $email . ': ' . $e->getMessage());
        }
    }
}
