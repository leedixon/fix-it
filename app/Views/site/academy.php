<?php
/**
 * The academy index.
 *
 * Two shelves, because the two audiences want opposite things and a single
 * mixed list makes each of them read past half of it.
 *
 * @var array $homeowners @var array $pros @var array $market @var array $crumbs
 */
require_once __DIR__ . '/../partials/icons.php';

$shelf = static function (array $lessons, string $base): void {
    foreach ($lessons as $slug => $l) {
        ?>
        <a class="lesson-card" href="<?= e(url('/academy/' . $slug)) ?>">
          <span class="lesson-card-t"><?= e($l['title']) ?></span>
          <span class="lesson-card-s"><?= e($l['summary']) ?></span>
          <span class="lesson-card-m"><?= icon('clock', 12) ?> <?= (int) $l['minutes'] ?> min read</span>
        </a>
        <?php
    }
};
?>

<?php require __DIR__ . '/../partials/crumbs.php'; ?>

<section class="sect-tight">
  <div class="wrap" style="max-width:820px">
    <div class="eyebrow eyebrow-brass">Free · no sign-up</div>
    <h1 style="font-size:clamp(30px,4.6vw,46px);margin-top:12px">The Fix Listed Academy</h1>
    <p class="muted" style="margin-top:14px;font-size:17px;max-width:60ch">
      Short, plain guides to hiring a tradesperson in <?= e($market['name']) ?> — and to getting
      work as one. Written by the people who run the site, about the things people actually ask.
    </p>
  </div>
</section>

<section class="sect-tight" style="padding-top:8px">
  <div class="wrap" style="max-width:820px">
    <h2 style="font-size:24px">If you need work doing</h2>
    <p class="muted" style="margin-top:6px">Hiring somebody, and not getting caught out.</p>
    <div class="lesson-grid"><?php $shelf($homeowners, '/academy/'); ?></div>

    <h2 style="font-size:24px;margin-top:44px">If you do the work</h2>
    <p class="muted" style="margin-top:6px">Getting listed, winning quotes, and what placement is.</p>
    <div class="lesson-grid"><?php $shelf($pros, '/academy/'); ?></div>
  </div>
</section>

<section class="sect-tight dark">
  <div class="wrap" style="display:flex;gap:20px;align-items:center;justify-content:space-between;flex-wrap:wrap">
    <div>
      <h2 style="font-size:26px">Still stuck?</h2>
      <p class="muted" style="margin-top:8px;max-width:52ch">
        Reply to any email we send, or use the contact page. It reaches a person.
      </p>
    </div>
    <a class="btn btn-primary btn-lg" href="<?= e(url('/contact')) ?>">Ask us</a>
  </div>
</section>
