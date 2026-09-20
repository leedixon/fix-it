<?php
/**
 * Fix Listed — configuration.
 *
 * Copy to config/config.php, fill in, then:  chmod 600 config/config.php
 * config/config.php is gitignored and must never be committed.
 */

return [
    'app' => [
        'name'     => 'Fix Listed',
        'url'      => 'https://fixlisted.com',
        // 'production' hides error detail from visitors. Never ship 'development'.
        'env'      => 'production',
        'timezone' => 'America/Chicago',
        // 32+ random bytes. Generate with:
        //   php -r 'echo bin2hex(random_bytes(32));'
        'key'      => '',
    ],

    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'leedixon_fixlisted',
        'user'     => 'leedixon_fixapp',
        'pass'     => '',
        // Do not change. Without utf8mb4 the client negotiates latin1 and
        // mangles every em-dash and accented name on the way out, even though
        // the columns themselves are correct.
        'charset'  => 'utf8mb4',
    ],

    'stripe' => [
        'publishable_key' => '',
        'secret_key'      => '',
        'webhook_secret'  => '',
    ],

    'mail' => [
        // Sending FROM a domain with no SPF record is the fastest route to a
        // spam folder. fixlisted.com's DNS lives at the registrar, so cPanel's
        // mail records do not apply to it — until an SPF TXT record exists
        // there, send from a domain whose email is already established.
        'from_address' => 'lee@leedixon.com',
        'from_name'    => 'Fix Listed',
        // Where signup alerts go. Same address is fine.
        'alert_to'     => 'lee@leedixon.com',
    ],
];
