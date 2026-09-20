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

        // Every public page carries <meta name="robots" content="noindex">
        // while this is true. Turning it off is a deliberate step in
        // docs/launch.md, not something a template should decide.
        'noindex'   => true,

        // 'label' shows the seeded listings with a Sample badge on every card
        // and a banner on every page. 'hide' filters them out of every query.
        // There is no mode that shows them unlabelled — see app/Core/Demo.php.
        'demo_data' => 'label',

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
        // The brand sends from here. This domain must be verified with the
        // mail provider, or nothing will be delivered.
        'from_address' => 'hello@fixlisted.com',
        'from_name'    => 'Fix Listed',
        // Where replies land. The signup emails ask people to reply, so this
        // has to be an inbox someone actually reads.
        'reply_to'     => 'lee@leedixon.com',
        // Where signup alerts go.
        'alert_to'     => 'lee@leedixon.com',

        // 'api', 'smtp' or 'mail'.
        //
        // Use 'api' or 'smtp' whenever the from_address belongs to a domain
        // whose email is hosted somewhere else — Google Workspace, Microsoft
        // 365, or a sending provider. Mail sent by this web server claiming to
        // come from such a domain fails SPF and DKIM, and a domain with a
        // DMARC policy has it rejected silently: no bounce, nothing in spam,
        // and no way to tell from here that it happened. Sending through the
        // provider fixes it properly.
        //
        // 'api' is the one to use on this install. A2 intercepts outbound SMTP
        // with its own mail filter, which answers the TLS handshake with A2's
        // certificate instead of the provider's, so port 587 and 465 cannot
        // work from here no matter what credentials are set. The API goes out
        // over ordinary HTTPS on port 443, which is not proxied.
        //
        // 'mail' is fine only when the from_address is a mailbox on this
        // same server.
        'transport' => 'api',

        'api' => [
            // resend.com/api-keys — starts with re_, needs Sending access.
            // Not your Resend login password.
            'key' => '',
        ],

        'smtp' => [
            // Resend          smtp.resend.com        username: resend
            // Brevo           smtp-relay.brevo.com
            // MailerSend      smtp.mailersend.net
            // Postmark        smtp.postmarkapp.com
            'host'       => 'smtp.resend.com',
            'port'       => 587,
            'encryption' => 'tls',             // tls (587) or ssl (465)
            'username'   => 'resend',
            // The provider's API key or SMTP password — not your login
            // password for their website.
            'password'   => '',
        ],
    ],
];
