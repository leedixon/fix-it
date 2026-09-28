<?php
declare(strict_types=1);

namespace FixListed\Core;

use FixListed\Repositories\TeamRepository;

/**
 * Tells the people who run the site that something happened.
 *
 * Staff alerts used to go to mail.alert_to — one address, in config. That
 * meant inviting a colleague gave them an account and nothing to react to:
 * a moderator could sign in and work the queue, but only if somebody
 * happened to tell them there was anything in it. The owner was the alerting
 * system.
 *
 * So the recipient list is the team table, filtered by what the alert is
 * about. A moderator who cannot see money is not told a placement sold; a
 * manager who runs the queue is told about applications and jobs. The
 * permission that governs the screen governs the email about the screen,
 * because two lists of who-should-know drift apart and only one of them is
 * enforced.
 *
 * mail.alert_to is still honoured and still gets everything. It is the
 * address that works when nobody is signed in and the one an owner can point
 * at a phone.
 */
final class TeamAlert
{
    /**
     * @param array<string,string> $facts  short label => value, shown as a list
     */
    public static function send(
        Database $db,
        View $view,
        int $marketId,
        string $capability,
        string $subject,
        string $lede,
        array $facts = [],
        string $actionUrl = '',
        string $actionLabel = 'Open the admin',
    ): int {
        $sent = [];

        foreach (self::recipients($db, $marketId, $capability) as $email => $name) {
            if (isset($sent[$email])) {
                continue;
            }
            $sent[$email] = true;

            try {
                Mailer::fromConfig()->send(
                    $email,
                    $subject,
                    $view->render('emails.team_alert', [
                        'title'       => $subject,
                        'preheader'   => $lede,
                        'name'        => $name,
                        'lede'        => $lede,
                        'facts'       => $facts,
                        'actionUrl'   => $actionUrl,
                        'actionLabel' => $actionLabel,
                    ], 'emails.layout'),
                    $lede . "\n\n"
                    . implode("\n", array_map(
                        static fn (string $k, string $v): string => $k . ': ' . $v,
                        array_keys($facts),
                        array_values($facts),
                    ))
                    . ($actionUrl !== '' ? "\n\n" . $actionUrl . "\n" : "\n"),
                );
            } catch (\Throwable $e) {
                // An alert that fails to send must never unwind the thing it
                // was reporting — by the time this runs, the job is live and
                // the money is taken.
                error_log('Team alert to ' . $email . ' failed: ' . $e->getMessage());
            }
        }

        return count($sent);
    }

    /**
     * Active staff who hold the capability, plus the configured fallback.
     *
     * Suspended accounts are left out: somebody whose access was taken away
     * this morning should not keep getting the site's internal post.
     *
     * @return array<string,string> email => first name
     */
    private static function recipients(Database $db, int $marketId, string $capability): array
    {
        $out = [];

        foreach ((new TeamRepository($db))->all() as $member) {
            if ((string) $member['status'] !== 'active') {
                continue;
            }
            if (!Auth::roleCan((string) $member['role'], $capability)) {
                continue;
            }
            $out[mb_strtolower((string) $member['email'])] = (string) $member['first_name'];
        }

        $fallback = mb_strtolower(trim((string) Config::get('mail.alert_to', '')));
        if ($fallback !== '' && !isset($out[$fallback])) {
            $out[$fallback] = '';
        }

        return $out;
    }
}
