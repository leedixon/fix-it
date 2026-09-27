<?php
/**
 * The account control in the public header.
 *
 * One control instead of three. Signed in, the header used to carry "Admin"
 * and "Your account" side by side — two destinations for the same person —
 * and no way to sign out at all without first going to /my or /admin. On a
 * phone those two buttons and the county selector took a whole row to
 * themselves and pushed the header to 138px.
 *
 * Same mechanism as the admin drawer: a checkbox, a label, and a scrim that
 * is a second label bound to the same checkbox so tapping outside closes it.
 * No JavaScript. The input is clipped rather than hidden, so it stays in the
 * tab order and a screen reader announces it as expanded or collapsed.
 *
 * Signed out this renders a plain Sign in link, because a menu holding one
 * item is a menu that should not exist.
 *
 * @var array|null $me
 */
require_once __DIR__ . '/icons.php';

if (empty($me)) {
    ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/sign-in')) ?>">Sign in</a><?php
    return;
}

$name    = trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? ''));
$label   = $name !== '' ? $name : (string) ($me['email'] ?? 'Account');
$isStaff = in_array($me['role'] ?? '', ['superadmin', 'market_admin', 'moderator'], true);
$isAdmin = in_array($me['role'] ?? '', ['superadmin', 'market_admin'], true);
?>
<div class="acct-menu">
  <input type="checkbox" id="acct-menu" class="acct-state" aria-label="Account menu">
  <label class="acct-btn" for="acct-menu">
    <span class="av" aria-hidden="true"><?= e(initials($label)) ?></span>
    <span class="acct-name"><?= e($name !== '' ? ($me['first_name'] ?: $label) : $label) ?></span>
    <?= icon('chev', 13) ?>
  </label>
  <label class="acct-scrim" for="acct-menu" aria-hidden="true"></label>

  <div class="acct-panel">
    <div class="acct-who">
      <b><?= e($label) ?></b>
      <span><?= e(\FixListed\Core\Auth::roleLabel((string) ($me['role'] ?? ''))) ?></span>
    </div>

    <?php if ($isAdmin): ?>
      <a href="<?= e(url('/admin')) ?>"><?= icon('chart', 15) ?> Admin</a>
    <?php endif; ?>
    <?php if (!$isStaff): ?>
      <a href="<?= e(url('/my')) ?>"><?= icon('user', 15) ?> Your account</a>
      <a href="<?= e(url('/my/listing')) ?>"><?= icon('tools', 15) ?> Your listing</a>
    <?php else: ?>
      <a href="<?= e(url('/my')) ?>"><?= icon('user', 15) ?> Your account</a>

      <?php
      /*
       * The two things a visitor can do, for the people who run the place.
       *
       * Neither flow was ever gated — an administrator could always post a
       * job or list a business by typing the URL. What they could not do was
       * find it. The header's "Post a job" button is hidden below 700px on
       * the assumption the mobile action bar is carrying it, and that bar
       * deliberately shows staff nothing. Between the two, a signed-in
       * superadmin on a phone had no route to either flow at all.
       *
       * They belong here rather than in the bar: an administrator reading
       * the public site is usually checking it, not converting on it, and a
       * button pinned over every page is in the way of that. In the menu
       * they are one tap away and nowhere near the thumb.
       *
       * Nothing is special-cased once the link is followed. Staff pay the
       * same $10 and go through the same Stripe checkout, because a flow
       * that behaves differently for the person testing it is a flow nobody
       * has actually tested.
       */
      ?>
      <div class="acct-sep" role="separator"></div>
      <a href="<?= e(url('/post-a-job')) ?>"><?= icon('clipboard', 15) ?> Post a job</a>
      <a href="<?= e(url('/list-your-business')) ?>"><?= icon('tools', 15) ?> List a business</a>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/sign-out')) ?>">
      <?= \FixListed\Core\Csrf::field() ?>
      <button type="submit"><?= icon('back', 15) ?> Sign out</button>
    </form>
  </div>
</div>
