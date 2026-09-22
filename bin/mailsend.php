<?php
declare(strict_types=1);

/**
 * Sends a real transactional email to an address you control.
 *
 *   php bin/mailsend.php you@example.com                  list what can be sent
 *   php bin/mailsend.php you@example.com pro_approved
 *   php bin/mailsend.php you@example.com pro_rejected
 *   php bin/mailsend.php you@example.com team_invite
 *
 * bin/mailtest.php proves the transport works. This proves a *particular*
 * message works: that it renders, that it sends, and — the part nothing else
 * checks — that the link inside it goes somewhere real and can be clicked.
 *
 * Nothing in the database is touched. The links are built the same way the
 * live code builds them, against a throwaway token where one is needed, so
 * what lands in your inbox is what an applicant or a colleague would get.
 */

require __DIR__ . '/../app/bootstrap.php';

use FixListed\Core\Auth;
use FixListed\Core\Config;
use FixListed\Core\Mailer;
use FixListed\Core\PasswordReset;
use FixListed\Core\View;

$to       = $argv[1] ?? '';
$template = $argv[2] ?? '';

if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    exit("Usage: php bin/mailsend.php you@example.com [template]\n\n"
       . "Templates: pro_approved, pro_rejected, team_invite\n\n");
}

$view   = new View(BASE_PATH . '/app/Views');
$mailer = Mailer::fromConfig();

// A token that resolves for real, so the link in the email can be clicked and
// the whole path — email, link, set-password page — is exercised, not just
// the sending. Issued against your own account, which is the only one the
// person running this is entitled to receive a link for.
$tokenFor = static function (string $email) use ($to): string {
    $db   = \FixListed\Core\Database::fromConfig();
    $user = $db->one('SELECT id FROM users WHERE email = :e LIMIT 1', ['e' => $email]);

    if ($user === null) {
        echo "  note: no account for {$email}, so the link is illustrative only\n";
        return str_repeat('0', 64);
    }
    return (new PasswordReset($db))->issue((int) $user['id'], true, true);
};

$templates = [
    'pro_approved' => static function () use ($view, $to, $tokenFor): array {
        $token = $tokenFor($to);
        return [
            'You are live on Fix Listed',
            $view->render('emails.pro_approved', [
                'title'      => 'You are live',
                'preheader'  => 'Your profile is published and homeowners can find you.',
                'name'       => 'Sample',
                'business'   => 'Sample Plumbing',
                'profileUrl' => abs_url('/pros/sample'),
                'jobsUrl'    => abs_url('/jobs'),
                'setUpUrl'   => abs_url('/set-password/' . $token),
                'market'     => 'Northwest Illinois',
            ], 'emails.layout'),
            "Your Fix Listed profile is live.\nSet your password: " . abs_url('/set-password/' . $token) . "\n",
        ];
    },
    'pro_rejected' => static fn (): array => [
        'About your Fix Listed application',
        (new View(BASE_PATH . '/app/Views'))->render('emails.pro_rejected', [
            'title'     => 'About your application',
            'preheader' => 'We could not list you just yet.',
            'name'      => 'Sample',
            'note'      => 'This is a test of the decline email. The licence number '
                         . 'did not match the state register.',
            'replyTo'   => (string) Config::get('mail.reply_to', ''),
        ], 'emails.layout'),
        "We could not list you just yet.\n",
    ],
    'team_invite' => static function () use ($view, $to, $tokenFor): array {
        $token = $tokenFor($to);
        return [
            'Your Fix Listed admin account',
            $view->render('emails.team_invite', [
                'title'     => 'You have been added to Fix Listed',
                'preheader' => 'Set your password to get in.',
                'name'      => 'Sample',
                'inviter'   => 'Lee Dixon',
                'roleLabel' => Auth::roleLabel(Auth::ROLE_MODERATOR),
                'roleBlurb' => Auth::roleBlurb(Auth::ROLE_MODERATOR),
                'market'    => 'Northwest Illinois',
                'days'      => PasswordReset::STAFF_INVITE_DAYS,
                'setUpUrl'  => abs_url('/set-password/' . $token),
            ], 'emails.layout'),
            "You have been given an admin account.\nSet your password: "
            . abs_url('/set-password/' . $token) . "\n",
        ];
    },
];

if ($template === '' || !isset($templates[$template])) {
    echo "\nTemplates you can send:\n\n";
    foreach (array_keys($templates) as $name) {
        echo "  php bin/mailsend.php {$to} {$name}\n";
    }
    echo "\n";
    exit($template === '' ? 0 : 1);
}

echo "\nTransport: " . $mailer->transport() . "\n";
echo "Sending {$template} to {$to} …\n";

[$subject, $html, $text] = $templates[$template]();

if ($mailer->send($to, '[test] ' . $subject, $html, $text)) {
    echo "\nSent. Check the inbox — and click the link in it, which is live.\n\n";
    exit(0);
}

$why = $mailer->lastError();
fwrite(STDERR, "\nIt did not send." . ($why !== '' ? "\n" . $why : '') . "\n");
fwrite(STDERR, "Run php bin/check.php to see the mail configuration.\n\n");
exit(1);
