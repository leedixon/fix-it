<?php
/** @var array $pro @var array $trades @var array $proCounties @var array $skills */
require_once __DIR__ . '/../partials/icons.php';
$name = $pro['business_name'] ?: trim($pro['first_name'] . ' ' . $pro['last_name']);
?>
<div class="adm-h">
  <div>
    <p class="eyebrow"><a href="<?= e(url('/admin/applications')) ?>">← Applications</a></p>
    <h1 style="margin-top:8px"><?= e($name) ?></h1>
    <p><?= e($pro['headline']) ?></p>
  </div>
</div>

<div class="adm-review">
  <div class="panel">
    <div class="panel-h"><h3>What they sent</h3></div>
    <div style="padding:18px 20px">
      <div class="kv"><span class="k">Person</span><span><?= e($pro['first_name'] . ' ' . $pro['last_name']) ?></span></div>
      <div class="kv"><span class="k">Email</span><span><a href="mailto:<?= e($pro['email']) ?>"><?= e($pro['email']) ?></a></span></div>
      <div class="kv"><span class="k">Phone</span><span><a href="tel:<?= e($pro['phone']) ?>"><?= e($pro['phone']) ?></a></span></div>
      <div class="kv"><span class="k">Trades</span><span><?= e(implode(', ', $trades) ?: '—') ?></span></div>
      <div class="kv"><span class="k">Counties</span><span><?= e(implode(', ', array_column($proCounties, 'short_name')) ?: '—') ?></span></div>
      <div class="kv"><span class="k">Years</span><span><?= (int) $pro['years_experience'] ?></span></div>
      <div class="kv"><span class="k">Hourly rate</span><span><?= $pro['hourly_rate_cents'] ? e(money((int) $pro['hourly_rate_cents'])) : 'not given' ?></span></div>
      <div class="kv"><span class="k">Base ZIP</span><span class="mono"><?= e($pro['base_zip'] ?: '—') ?></span></div>
      <div class="kv"><span class="k">Applied</span><span><?= e(ago($pro['created_at'])) ?></span></div>

      <h3 style="font-size:16px;margin:22px 0 8px">Their own words</h3>
      <div class="bio"><?php foreach (preg_split('/\n\s*\n/', (string) $pro['bio']) ?: [] as $p): ?>
        <p><?= e(trim($p)) ?></p>
      <?php endforeach; ?></div>
    </div>
  </div>

  <div>
    <div class="panel" style="margin-bottom:18px">
      <div class="panel-h"><h3>Check these</h3></div>
      <div style="padding:18px 20px">
        <div class="kv">
          <span class="k">Licence</span>
          <span class="mono"><?= e(trim($pro['license_state'] . ' ' . $pro['license_number']) ?: 'none given') ?></span>
        </div>
        <div class="kv">
          <span class="k">Insurance</span>
          <span><?= e($pro['insurance_carrier'] ?: 'none given') ?></span>
        </div>

        <?php if ($pro['license_number'] !== '' && $pro['license_state'] === 'IL'): ?>
          <p style="margin-top:14px">
            <a class="btn btn-ghost btn-sm btn-block" target="_blank" rel="noopener"
               href="https://online-dfpr.micropact.com/lookup/licenselookup.aspx">
              Illinois licence lookup <?= icon('arrow', 13) ?>
            </a>
          </p>
        <?php endif; ?>

        <p class="tiny muted" style="margin-top:14px">
          Tick only what you have actually seen. Each tick puts a badge on their public profile
          saying a person verified it.
        </p>
      </div>
    </div>

    <form class="panel" method="post" action="<?= e(url('/admin/applications/' . $pro['id'] . '/approve')) ?>"
          style="margin-bottom:18px">
      <?= \FixListed\Core\Csrf::field() ?>
      <div class="panel-h"><h3>Approve</h3></div>
      <div style="padding:18px 20px">
        <label class="radio" style="margin-bottom:10px">
          <input type="checkbox" name="licence_verified" value="1">
          <span><b>Licence checked</b><br><span class="tiny muted">I looked it up and it is valid.</span></span>
        </label>
        <label class="radio" style="margin-bottom:16px">
          <input type="checkbox" name="insurance_verified" value="1">
          <span><b>Insurance checked</b><br><span class="tiny muted">I have seen a current certificate.</span></span>
        </label>
        <button class="btn btn-primary btn-block" type="submit">Approve and publish</button>
        <p class="tiny muted" style="margin-top:10px">They get an email with a link to their live profile.</p>
      </div>
    </form>

    <form class="panel" method="post" action="<?= e(url('/admin/applications/' . $pro['id'] . '/reject')) ?>">
      <?= \FixListed\Core\Csrf::field() ?>
      <div class="panel-h"><h3>Decline</h3></div>
      <div style="padding:18px 20px">
        <label class="field">
          <span>Why — they may see this</span>
          <textarea name="note" rows="3" placeholder="e.g. Licence number did not match the state register."></textarea>
        </label>
        <label class="radio" style="margin-bottom:14px">
          <input type="checkbox" name="tell_them" value="1" checked>
          <span><b>Email them</b><br><span class="tiny muted">Untick to decline quietly.</span></span>
        </label>
        <button class="btn btn-ghost btn-block" type="submit">Decline this application</button>
      </div>
    </form>
  </div>
</div>
