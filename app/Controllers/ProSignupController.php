<?php
declare(strict_types=1);

namespace FixListed\Controllers;

use FixListed\Core\Controller;
use FixListed\Core\Csrf;
use FixListed\Core\Response;
use FixListed\Core\Session;
use FixListed\Core\Validator;
use FixListed\Core\View;
use FixListed\Repositories\ProApplicationRepository;
use FixListed\Repositories\TradeRepository;
use RuntimeException;

/**
 * A tradesperson applying to be listed.
 *
 * The form is long because a directory listing is worth filling in properly,
 * and short where it can be: no password, no email verification loop, no
 * account to create before they have seen whether it is worth it. They submit
 * once, an administrator checks the licence, and the profile goes live.
 */
final class ProSignupController extends Controller
{
    private const FORM_KEY = 'pro_form_opened_at';

    public function form(): Response
    {
        // Stamped now, read on submit. A form completed in under three
        // seconds was filled by a script, not a person with a licence number
        // to look up.
        Session::put(self::FORM_KEY, time());

        return $this->page('site/list_business', [
            'title'       => 'List your trade business — Fix Listed',
            'description' => 'Get listed in ' . $this->market['name']
                . '. A profile is free, quoting is free, and you keep the whole job.',
            'trades'      => (new TradeRepository($this->db))->all(),
            'crumbs'      => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'For tradespeople', 'href' => '/for-pros'],
                ['label' => 'List your business'],
            ],
            'old'         => [],
            'errors'      => [],
        ]);
    }

    public function submit(): Response
    {
        $body = $this->request->body;

        if (!Csrf::check($this->request->input('_csrf'))) {
            // Almost always an expired session rather than an attack, so it
            // gets a sentence a person can act on instead of a bare 403.
            return $this->reject($body, ['_form' => 'That form had been open a long time. Please send it again.']);
        }

        // Honeypot: a field no person sees and every naive bot fills.
        if ($this->request->input('website', '') !== '') {
            return $this->thanks();   // Answer as if it worked. Bots learn from failures.
        }

        $opened = Session::get(self::FORM_KEY);
        if (is_int($opened) && time() - $opened < 3) {
            return $this->thanks();
        }

        $v = new Validator($body);
        $v->required('first_name', 'First name')->max('first_name', 80, 'First name')
          ->required('last_name', 'Last name')->max('last_name', 80, 'Last name')
          ->required('email', 'Email')->email('email')->max('email', 191, 'Email')
          ->required('phone', 'Phone')->max('phone', 32, 'Phone')
          ->max('business_name', 160, 'Business name')
          ->required('headline', 'What you do')->max('headline', 160, 'What you do')
          ->required('bio', 'About your business')->min('bio', 60, 'About your business')
          ->requiredAny('trades', 'At least one trade')
          ->requiredAny('counties', 'At least one county');

        $tradeIds  = array_map('intval', $v->values('trades'));
        $countyIds = array_map('intval', $v->values('counties'));

        // Ids come from a form, so they are checked against this market's own
        // lists rather than trusted. Otherwise a edited value assigns a pro to
        // a county in somebody else's market.
        $validTrades   = array_column((new TradeRepository($this->db))->all(), 'id');
        $validCounties = array_column($this->counties(), 'id');
        $tradeIds  = array_values(array_intersect($tradeIds, array_map('intval', $validTrades)));
        $countyIds = array_values(array_intersect($countyIds, array_map('intval', $validCounties)));

        if ($tradeIds === []) {
            $v->fail('trades', 'Choose at least one trade.');
        }
        if ($countyIds === []) {
            $v->fail('counties', 'Choose at least one county.');
        }

        if (!$v->passes()) {
            return $this->reject($body, $v->errors());
        }

        $repo = new ProApplicationRepository($this->db, $this->scope);

        try {
            $created = $repo->create([
                'first_name'        => $v->value('first_name'),
                'last_name'         => $v->value('last_name'),
                'email'             => mb_strtolower($v->value('email')),
                'phone'             => $v->value('phone'),
                'business_name'     => $v->value('business_name'),
                'headline'          => $v->value('headline'),
                'bio'               => $v->value('bio'),
                'hourly_rate_cents' => $v->money('hourly_rate'),
                'years_experience'  => $v->int('years_experience') ?? 0,
                'home_county_id'    => $countyIds[0],
                'zip'               => $v->value('zip'),
                'license_number'    => $v->value('license_number'),
                'license_state'     => mb_strtoupper(mb_substr($v->value('license_state'), 0, 2)),
                'insurance_carrier' => $v->value('insurance_carrier'),
                'trade_ids'         => $tradeIds,
                'county_ids'        => $countyIds,
            ]);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'already_applied') {
                return $this->reject($body, [
                    'email' => 'There is already a listing for this email address. '
                             . 'Email hello@fixlisted.com and we will sort it out.',
                ]);
            }
            throw $e;
        }

        $this->notify($created, $v);

        return $this->thanks();
    }

    /** @param array<string,string> $errors */
    private function reject(array $old, array $errors): Response
    {
        return $this->page('site/list_business', [
            'title'  => 'List your trade business — Fix Listed',
            'trades' => (new TradeRepository($this->db))->all(),
            'crumbs' => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'For tradespeople', 'href' => '/for-pros'],
                ['label' => 'List your business'],
            ],
            'old'    => $old,
            'errors' => $errors,
        ], 422);
    }

    private function thanks(): Response
    {
        return Response::redirect('/list-your-business/received');
    }

    public function received(): Response
    {
        return $this->page('site/application_received', [
            'title'   => 'Application received — Fix Listed',
            'noindex' => true,
            /*
             * A tradesperson applied. GA4's own 'sign_up' rather than a
             * custom name, so it lands in the reports that already exist.
             *
             * No transaction_id and so no deduplication — nothing was
             * charged, so there is nothing for GA4 to dedupe against. A
             * refresh of this page counts twice. That is a known and small
             * inaccuracy on a soft conversion, and the number that actually
             * matters is the count of applications in the admin queue, which
             * is exact.
             */
            'analytics' => $this->analytics('site/application_received', [
                'event'  => 'sign_up',
                'method' => 'pro_application',
            ]),
        ]);
    }

    /**
     * Confirmation to the applicant, alert to the administrator.
     *
     * Failure here must not fail the application: the row is already
     * committed, and telling a tradesperson their application did not go
     * through because an SMTP call timed out would be a lie.
     * @param array{pro_id:int,user_id:int,slug:string} $created
     */
    private function notify(array $created, Validator $v): void
    {
        try {
            $mailer = \FixListed\Core\Mailer::fromConfig();
            $view   = new View(BASE_PATH . '/app/Views');
            $name   = $v->value('business_name') !== '' ? $v->value('business_name') : $v->value('first_name');

            $mailer->send(
                mb_strtolower($v->value('email')),
                'We have your listing application — Fix Listed',
                $view->render('emails.pro_application', [
                    'title'     => 'Application received',
                    'preheader' => 'We are checking your licence and insurance now.',
                    'name'      => $v->value('first_name'),
                    'business'  => $name,
                    'market'    => (string) $this->market['name'],
                ], 'emails.layout'),
                "Thanks for applying to be listed on Fix Listed.\n\n"
                . "We are checking your licence and insurance now. You will hear from us "
                . "within two working days.\n\nReply to this email if anything changes.\n",
            );

            $alertTo = (string) \FixListed\Core\Config::get('mail.alert_to', '');
            if ($alertTo !== '') {
                $mailer->send(
                    $alertTo,
                    'New listing application: ' . $name,
                    $view->render('emails.pro_application_alert', [
                        'title'     => 'New listing application',
                        'preheader' => $name . ' applied to be listed.',
                        'business'  => $name,
                        'person'    => $v->value('first_name') . ' ' . $v->value('last_name'),
                        'email'     => mb_strtolower($v->value('email')),
                        'phone'     => $v->value('phone'),
                        'licence'   => trim($v->value('license_state') . ' ' . $v->value('license_number')),
                        'reviewUrl' => abs_url('/admin/applications/' . $created['pro_id']),
                    ], 'emails.layout'),
                    "New listing application from {$name}.\n\nReview it: "
                    . abs_url('/admin/applications/' . $created['pro_id']) . "\n",
                );
            }
        } catch (\Throwable $e) {
            error_log('Pro application mail failed for pro ' . $created['pro_id'] . ': ' . $e->getMessage());
        }
    }
}
