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
        'from_address' => 'lee@leedixon.com',
        'from_name'    => 'Fix Listed',
        // Where signup alerts go. Same address is fine.
        'alert_to'     => 'lee@leedixon.com',

        // 'smtp' or 'mail'.
        //
        // Use 'smtp' whenever the from_address belongs to a domain whose email
        // is hosted somewhere else — Google Workspace, Microsoft 365. Mail sent
        // by this web server claiming to come from such a domain fails SPF and
        // DKIM, and a domain with a DMARC policy has it rejected silently: no
        // bounce, nothing in spam, and no way to tell from here that it
        // happened. Sending through the provider fixes it properly.
        //
        // 'mail' is fine only when the from_address is a mailbox on this
        // same server.
        'transport' => 'smtp',

        'smtp' => [
            // Google Workspace / Gmail
            'host'       => 'smtp.gmail.com',
            'port'       => 587,
            'encryption' => 'tls',             // tls (587) or ssl (465)
            'username'   => 'lee@leedixon.com',
            // NOT your normal password. Google requires an App Password:
            // Google Account > Security > 2-Step Verification > App passwords.
            // It is 16 characters; spaces in it are ignored.
            'password'   => '',
        ],
    ],
];
