<?php
/**
 * The fixed action bar on a phone.
 *
 * At 700px and below the stylesheet hides the "Post a job — $10" button from
 * the header, on the assumption that this bar is carrying it. This file did
 * not exist, so the assumption was wrong and the site's primary call to
 * action simply disappeared on every phone. The stylesheet has described this
 * bar since the prototype; only the markup was missing.
 *
 * What it offers depends on who is looking, because the same two buttons for
 * everybody is two buttons wrong for most people. A superadmin was being
 * invited to list a business; a tradesperson who already has a listing was
 * being invited to create one.
 *
 * It is left out on the pages it would be absurd on — a "Post a job" button
 * fixed to the bottom of the post-a-job form — and in the admin and account
 * areas, which use their own layouts and never include it.
 *
 * @var string     $path
 * @var array|null $me
 */
$hideOn = ['/post-a-job', '/list-your-business', '/sign-in', '/forgot-password', '/set-password'];

foreach ($hideOn as $prefix) {
    if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
        return;
    }
}

$role = $me['role'] ?? '';

/*
 * Staff get nothing.
 *
 * An administrator reading the public site is checking it, not converting on
 * it, and a bar pinned over the bottom of every page is in the way of exactly
 * that. They have the account menu in the header.
 */
if (in_array($role, ['superadmin', 'market_admin', 'moderator'], true)) {
    return;
}

/** @var array<int,array{href:string,label:string,primary:bool}> $actions */
$actions = $role === 'pro'
    // A tradesperson is here to find work, not to be sold a listing they
    // already have.
    ? [
        ['href' => '/jobs',   'label' => 'Open jobs',  'primary' => true],
        ['href' => '/my',     'label' => 'My account', 'primary' => false],
    ]
    : [
        ['href' => '/list-your-business', 'label' => 'List your business', 'primary' => false],
        ['href' => '/post-a-job',         'label' => 'Post a job — $10',   'primary' => true],
    ];
?>
<div class="mobilebar">
  <?php foreach ($actions as $a): ?>
    <a class="btn btn-block <?= $a['primary'] ? 'btn-primary' : 'btn-ghost' ?>"
       href="<?= e(url($a['href'])) ?>"><?= e($a['label']) ?></a>
  <?php endforeach; ?>
</div>
