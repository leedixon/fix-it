<?php
/**
 * @var array|null $state @var string $running @var string $switchFile
 */
require_once __DIR__ . '/../partials/icons.php';
$isOn = $state !== null;
?>
<div class="adm-h">
  <div>
    <h1>Maintenance</h1>
    <p>Takes the site off the air for visitors while you work on it. You keep full access throughout.</p>
  </div>
</div>

<?php if ($isOn): ?>

  <div class="panel panel-alert" style="margin-bottom:18px">
    <div class="panel-h">
      <h3><?= icon('alert', 16) ?> The site is down for visitors</h3>
      <span class="tiny muted" style="margin-left:auto">for <?= e($running) ?></span>
    </div>
    <div style="padding:18px 20px">
      <div class="kv"><span class="k">Visitors see</span><span><?= e($state['message']) ?></span></div>
      <div class="kv"><span class="k">Put on by</span><span><?= e($state['by'] !== '' ? $state['by'] : 'unknown') ?></span></div>
      <?php $since = strtotime($state['started_at']); ?>
      <div class="kv"><span class="k">Since</span><span><?= $since !== false
          ? e(date('j M Y, g:ia', $since)) : e($state['started_at']) ?></span></div>
      <div class="kv"><span class="k">They get</span><span>503 Service Unavailable, with Retry-After</span></div>

      <form method="post" action="<?= e(url('/admin/maintenance')) ?>" style="margin-top:20px">
        <?= \FixListed\Core\Csrf::field() ?>
        <input type="hidden" name="mode" value="off">
        <button class="btn btn-dark" type="submit">Put the site back up</button>
      </form>
    </div>
  </div>

<?php else: ?>

  <form class="panel" method="post" action="<?= e(url('/admin/maintenance')) ?>" style="margin-bottom:18px">
    <?= \FixListed\Core\Csrf::field() ?>
    <input type="hidden" name="mode" value="on">
    <div class="panel-h"><h3>Take the site down</h3></div>
    <div style="padding:18px 20px">
      <label class="field">
        <span>What visitors are told</span>
        <input type="text" name="message" maxlength="200"
               placeholder="We are making a quick change to the site. It will be back shortly."
               autocomplete="off">
        <!-- A specific time is worth more than a reassurance. Somebody who
             knows it is back at 3pm comes back at 3pm; somebody told
             "shortly" gives up. -->
        <small class="muted">Optional. A time they can plan around — "back by 3pm" — beats "shortly".</small>
      </label>

      <button class="btn btn-dark" type="submit" style="margin-top:6px">Take the site down</button>
    </div>
  </form>

<?php endif; ?>

<div class="panel">
  <div class="panel-h"><h3>What this actually does</h3></div>
  <div style="padding:18px 20px">
    <ul class="plain-list">
      <li><?= icon('check', 14) ?> <span><strong>You keep working.</strong> Signed-in administrators
          see the whole site exactly as normal — that is the point, so you can check a deploy
          before the public sees it.</span></li>
      <li><?= icon('check', 14) ?> <span><strong>Nothing is lost.</strong> Jobs, quotes, listings and
          payments are untouched. This changes what is served, not what is stored.</span></li>
      <li><?= icon('check', 14) ?> <span><strong>Search rankings survive.</strong> Visitors get a 503
          with Retry-After, which Google reads as "temporary, come back" rather than "this page is
          now a maintenance notice".</span></li>
      <li><?= icon('alert', 14) ?> <span><strong>Stripe is held off too.</strong> Payment webhooks get
          the same 503, so Stripe retries them with backoff instead of losing them. It keeps
          retrying for about three days — a maintenance window longer than that would start
          dropping payments.</span></li>
      <li><?= icon('info', 14) ?> <span><strong>The switch is a file</strong>, not a database row, so
          it still works when the database does not. That file is
          <code class="mono tiny"><?= e($switchFile) ?></code>.</span></li>
    </ul>

    <div class="rule" style="margin:20px 0 16px"></div>

    <p class="tiny muted" style="margin:0">
      When this screen will not load — which is exactly when you will want it most — the same
      switch is available over SSH, and it needs no database:
    </p>
    <pre class="code-block"><code>php bin/maintenance.php on --message="Back by 3pm."
php bin/maintenance.php status
php bin/maintenance.php off</code></pre>
  </div>
</div>
