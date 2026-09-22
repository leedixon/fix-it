<?php
/**
 * @var array $market
 * @var array $county
 * @var int   $proCount
 * @var int   $jobCount
 * @var int   $fee
 * @var array $featured
 * @var array $latestJobs
 * @var array $tradeTiles
 * @var array $cities
 */
require_once __DIR__ . '/../partials/icons.php';

$where    = $county['short_name'] ?? $market['name'];
$feeLabel = money($fee);
?>

<section class="hero">
  <div class="wrap hero-in">
    <div>
      <div class="eyebrow eyebrow-brass">Now serving <?= e($market['name']) ?></div>
      <h1 style="margin-top:14px">The trades directory<br>that doesn't take <em>a cut of your job</em>.</h1>
      <p class="lede">
        Post what needs fixing for a flat <?= e($feeLabel) ?>. Every licensed tradesperson in
        <?= e($where) ?> sees it, quotes it, and deals with you directly. No commission, no lead
        resale, no percentage off the top.
      </p>

      <div class="hero-cta">
        <a class="btn btn-primary btn-lg" href="<?= e(url('/post-a-job')) ?>">Post a job — <?= e($feeLabel) ?> flat</a>
        <a class="btn btn-onink btn-lg" href="<?= e(url('/pros')) ?>">
          <?= $proCount > 0 ? 'Browse ' . $proCount . ' pro' . ($proCount === 1 ? '' : 's') : 'Browse the directory' ?>
        </a>
      </div>

      <div class="hero-fine">
        <span><?= icon('check', 15) ?> Licence &amp; insurance checked</span>
        <span><?= icon('check', 15) ?> You talk to the tradesperson, not a call centre</span>
        <span><?= icon('check', 15) ?> Free for pros to quote</span>
      </div>

      <div class="proof">
        <div>
          <div class="n mono"><?= $proCount ?></div>
          <div class="l">Tradespeople in <?= e($market['code']) ?></div>
        </div>
        <div>
          <div class="n mono"><?= $jobCount ?></div>
          <div class="l">Jobs open right now</div>
        </div>
        <div>
          <div class="n mono">$0</div>
          <div class="l">Commission, ever</div>
        </div>
      </div>
    </div>

    <div class="quote-card">
      <?php
      /*
       * Two radios and sibling CSS, no JavaScript.
       *
       * The card asks the only question that matters on arrival — which side
       * of the marketplace are you — and answers it differently for each.
       * Sending a tradesperson to a form headed "what needs doing?" is how a
       * two-sided directory loses the half that is harder to recruit.
       */
      ?>
      <input class="role-input" type="radio" name="role" id="role-home" checked>
      <input class="role-input" type="radio" name="role" id="role-pro">

      <div class="qc-top">
        <div class="roletabs" role="group" aria-label="What brings you here">
          <label for="role-home">I need work done</label>
          <label for="role-pro">I'm a tradesperson</label>
        </div>
      </div>

      <div class="qc-body pane pane-home">
        <label class="field">
          <span>What needs doing</span>
          <input type="text" placeholder="e.g. Sump pump failed, water in the basement" disabled>
        </label>
        <label class="field">
          <span>Trade</span>
          <select disabled>
            <?php foreach (array_slice($tradeTiles, 0, 6) as $t): ?>
              <option><?= e($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">
          <span>Where</span>
          <select disabled>
            <?php foreach (array_slice($cities, 0, 8) as $c): ?>
              <option><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <a class="btn btn-primary btn-block btn-lg" href="<?= e(url('/post-a-job')) ?>">
          Get quotes — <?= e($feeLabel) ?> <?= icon('arrow', 15) ?>
        </a>
        <p class="tiny muted" style="margin-top:10px;text-align:center">
          Nothing is charged until you have seen your post and confirmed it.
        </p>
      </div>
      <div class="qc-foot pane pane-home">
        <?= icon('lock', 14) ?>
        <span>Card details are handled by Stripe. Fix Listed never sees them.</span>
      </div>

      <div class="qc-body pane pane-pro">
        <label class="field">
          <span>Your trade</span>
          <select disabled>
            <?php foreach (array_slice($tradeTiles, 0, 6) as $t): ?>
              <option><?= e($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">
          <span>Counties you will drive to</span>
          <select disabled>
            <?php foreach (array_slice($counties, 0, 8) as $c): ?>
              <option><?= e($c['short_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">
          <span>Business name</span>
          <input type="text" placeholder="e.g. Ojo Plumbing — or your own name" disabled>
        </label>
        <a class="btn btn-primary btn-block btn-lg" href="<?= e(url('/list-your-business')) ?>">
          List your business — free <?= icon('arrow', 15) ?>
        </a>
        <p class="tiny muted" style="margin-top:10px;text-align:center">
          No commission, no per-lead charge. You keep the whole job.
        </p>
      </div>
      <div class="qc-foot pane pane-pro">
        <?= icon('shield', 14) ?>
        <span>We check your licence and insurance before your profile goes live.</span>
      </div>

    </div>
  </div>
</section>

<div class="strip">
  <div class="wrap strip-in">
    <div class="strip-item"><?= icon('shield', 16) ?> Licence and insurance checked before a profile goes live</div>
    <div class="strip-item"><?= icon('bolt', 16) ?> Most jobs get their first quote the same day</div>
    <div class="strip-item"><?= icon('lock', 16) ?> Your phone number stays private until you share it</div>
  </div>
</div>

<section class="sect">
  <div class="wrap">
    <div class="head">
      <div>
        <div class="eyebrow">Browse by trade</div>
        <h2>What needs fixing?</h2>
        <p>Ten trades covering the work that actually comes up — the emergency at 6am and the
           project you have been putting off since spring.</p>
      </div>
    </div>

    <div class="grid g4">
      <?php foreach ($tradeTiles as $t): ?>
        <a class="cat" href="<?= e(url_q('/pros', ['trade' => $t['slug'], 'county' => $county['slug'] ?? null])) ?>">
          <span class="ic"><?= icon((string) $t['icon'], 19) ?></span>
          <span style="min-width:0">
            <span class="t" style="display:block"><?= e($t['name']) ?></span>
            <span class="c">
              <?php $n = (int) $t['pro_count']; ?>
              <?= $n > 0 ? $n . ' pro' . ($n === 1 ? '' : 's') : 'Accepting listings' ?>
            </span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<hr class="hr">

<section class="sect">
  <div class="wrap">
    <div class="head">
      <div>
        <div class="eyebrow">The directory</div>
        <h2>Tradespeople in <?= e($where) ?></h2>
        <p>Ranked by rating and review count. Paid placement is always labelled, because the
           badges beside it are only worth something if you believe they were not bought.</p>
      </div>
      <a class="btn btn-ghost" href="<?= e(url('/pros')) ?>">See all <?= icon('arrow', 15) ?></a>
    </div>

    <?php if ($featured === []): ?>
      <?php
      $emptyTitle   = 'No tradespeople listed here yet';
      $emptyBody    = $county !== null
          ? 'Nobody covering ' . $county['short_name'] . ' has listed with us yet. Tradespeople in the '
            . 'area can claim a free profile, and homeowners can still post a job — it reaches every '
            . 'pro in ' . $market['name'] . '.'
          : 'The directory is open for listings. If you run a trade business in ' . $market['name']
            . ', a profile is free and takes a few minutes.';
      $emptyCta     = 'List your business — free';
      $emptyCtaHref = url('/list-your-business');
      require __DIR__ . '/../partials/empty.php';
      ?>
    <?php else: ?>
      <div class="grid g3">
        <?php foreach ($featured as $pro): ?>
          <?php require __DIR__ . '/../partials/pro_card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="sect dark">
  <div class="wrap">
    <div class="head">
      <div>
        <div class="eyebrow">How it works</div>
        <h2>Three steps, one flat fee</h2>
      </div>
    </div>
    <div class="steps">
      <div class="step active">
        <div class="n">01</div>
        <h3>Describe the job</h3>
        <p class="muted">What is wrong, roughly what you want to spend, and how soon. A minute of
          typing. Photos help but are not required.</p>
      </div>
      <div class="step active">
        <div class="n">02</div>
        <h3>Pay <?= e($feeLabel) ?> and it goes live</h3>
        <p class="muted">One flat fee, once, whatever the job is worth. Every tradesperson covering
          your county sees it immediately.</p>
      </div>
      <div class="step active">
        <div class="n">03</div>
        <h3>Deal with them directly</h3>
        <p class="muted">Quotes come to you. You pick who you like, agree the price with them, and
          pay them — not us. We take nothing from the job.</p>
      </div>
    </div>
  </div>
</section>

<section class="sect">
  <div class="wrap">
    <div class="head">
      <div>
        <div class="eyebrow">Jobs board</div>
        <h2>Open right now</h2>
        <p>What homeowners in <?= e($market['name']) ?> are asking for today. Free for tradespeople
           to quote.</p>
      </div>
      <a class="btn btn-ghost" href="<?= e(url('/jobs')) ?>">All jobs <?= icon('arrow', 15) ?></a>
    </div>

    <?php if ($latestJobs === []): ?>
      <?php
      $emptyTitle   = 'No jobs open yet';
      $emptyBody    = 'This is where homeowners\' jobs appear the moment they are posted. Be the first.';
      $emptyCta     = 'Post a job — ' . money($fee);
      $emptyCtaHref = url('/post-a-job');
      require __DIR__ . '/../partials/empty.php';
      ?>
    <?php else: ?>
      <div class="jobs-grid">
        <?php foreach ($latestJobs as $job): ?>
          <?php require __DIR__ . '/../partials/job_card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($cities !== []): ?>
<hr class="hr">
<section class="sect-tight">
  <div class="wrap">
    <div class="eyebrow" style="margin-bottom:12px">Towns we cover</div>
    <div class="chipset">
      <?php foreach ($cities as $c): ?>
        <a class="chip" href="<?= e(city_url($c)) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
