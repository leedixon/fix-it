<?php
declare(strict_types=1);

/**
 * Renders every transactional email to storage/cache/email-preview/ so they
 * can be opened in a browser and checked before anyone receives one.
 *
 *   php bin/preview_emails.php
 */

require __DIR__ . '/../app/bootstrap.php';

use FixListed\Core\View;

$view = new View(BASE_PATH . '/app/Views');
$out  = BASE_PATH . '/storage/cache/email-preview';
@mkdir($out, 0775, true);

$samples = [
    'pro' => ['emails.waitlist_pro', [
        'title' => "You're on the founding list",
        'preheader' => 'Your Fix Listed profile will be live the day we open. Nothing to pay.',
        'name' => 'Dale Hochstetler', 'trade' => 'Plumbing',
        'counties' => 'Winnebago, Stephenson', 'replyTo' => 'lee@leedixon.com',
    ]],
    'homeowner' => ['emails.waitlist_homeowner', [
        'title' => "You're on the list",
        'preheader' => 'One email, the day Fix Listed opens near you. Nothing else.',
        'name' => 'Marissa Kessler', 'town' => 'Rockford',
    ]],
    'alert' => ['emails.waitlist_alert', [
        'title' => 'New Fix Listed signup',
        'preheader' => 'Dale Hochstetler - Plumbing',
        'role' => 'pro', 'name' => 'Dale Hochstetler',
        'email' => 'dale@hochstetlerplumbing.com', 'phone' => '(815) 555-0177',
        'trade' => 'Plumbing', 'counties' => 'Winnebago, Stephenson',
        'town' => '', 'note' => '',
    ]],
];

foreach ($samples as $name => [$template, $data]) {
    $html = $view->render($template, $data, 'emails.layout');
    file_put_contents("{$out}/{$name}.html", $html);
    printf("%-10s %6.1f KB\n", $name, strlen($html) / 1024);
}
echo "written to {$out}\n";
