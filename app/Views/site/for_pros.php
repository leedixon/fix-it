<?php
/**
 * @var int   $openJobs
 * @var int   $proCount
 * @var int   $boost
 * @var int   $spotlight
 * @var array $market
 */
require_once __DIR__ . '/../partials/icons.php';
?>

<section class="hero">
  <div class="wrap hero-in">
    <div>
      <div class="eyebrow eyebrow-brass">For tradespeople in <?= e($market['name']) ?></div>
      <h1 style="margin-top:14px">Keep the whole job.<br><em>We take nothing.</em></h1>
      <p class="lede">
        No commission. No per-lead charge. No reselling your customer to three competitors an hour
        later. A profile is free, quoting is free, and what the homeowner pays you is yours.
      </p>
      <div class="hero-cta">
        <a class="btn btn-primary btn-lg" href="<?= e(url('/contact')) ?>">List your business</a>
        <a class="btn btn-onink btn-lg" href="<?= e(url('/jobs')) ?>">See open jobs</a>
      </div>
      <div class="proof">
        <div><div class="n mono"><?= $openJobs ?></div><div class="l">Jobs open right now</div></div>
        <div><div class="n mono"><?= $proCount ?></div><div class="l">Businesses listed</div></div>
        <div><div class="n mono">0%</div><div class="l">Taken from your work</div></div>
      </div>
    </div>

    <div class="quote-card">
      <div class="qc-top">
        <div>
          <div class="eyebrow">What it costs you</div>
          <div style="font-family:var(--serif);font-size:20px;margin-top:3px">Nothing</div>
        </div>
        <span class="badge b-live">Free</span>
      </div>
      <div class="qc-body">
        <div class="kv"><span class="k">Profile</span><span>Free</span></div>
        <div class="kv"><span class="k">Quoting a job</span><span>Free</span></div>
        <div class="kv"><span class="k">Commission on work won</span><span>None</span></div>
        <div class="kv"><span class="k">Per-lead charge</span><span>None</span></div>
        <div class="kv"><span class="k">Optional placement</span><span class="mono"><?= e(money($boost)) ?>/mo</span></div>
      </div>
      <div class="qc-foot">
        <?= icon('info', 14) ?>
        <span>Placement moves you up the page. It never changes your rating or your badges.</span>
      </div>
    </div>
  </div>
</section>

<section class="sect">
  <div class="wrap">
    <div class="head">
      <div>
        <div class="eyebrow">How it works</div>
        <h2>Three steps to your first quote</h2>
      </div>
    </div>
    <div class="steps">
      <div class="step active">
        <div class="n">01</div>
        <h3>Send us your details</h3>
        <p class="muted">Business name, trades, the counties you will drive to, your licence and
          insurance. We check them before anything goes live.</p>
      </div>
      <div class="step active">
        <div class="n">02</div>
        <h3>Your profile goes live</h3>
        <p class="muted">You appear in the directory and on the town pages for every county you
          cover. Homeowners find you by trade and by where they live.</p>
      </div>
      <div class="step active">
        <div class="n">03</div>
        <h3>Quote what suits you</h3>
        <p class="muted">Jobs in your counties land on the board. Quote the ones you want, ignore
          the rest. The homeowner pays you directly.</p>
      </div>
    </div>
  </div>
</section>

<hr class="hr">

<section class="sect">
  <div class="wrap">
    <div class="head">
      <div>
        <div class="eyebrow">Straight answers</div>
        <h2>The questions every pro asks first</h2>
      </div>
    </div>
    <div class="grid g2">
      <div class="card" style="padding:22px">
        <h3 style="font-size:19px">Do you sell my lead to anyone else?</h3>
        <p class="muted small" style="margin-top:8px">No. A job is posted once and shown to the
          pros covering that county. We do not sell contact details to anybody, here or elsewhere.</p>
      </div>
      <div class="card" style="padding:22px">
        <h3 style="font-size:19px">What do you take when I win a job?</h3>
        <p class="muted small" style="margin-top:8px">Nothing. The homeowner pays their listing fee
          to us and pays you for the work. We are not in the middle of that second payment at all.</p>
      </div>
      <div class="card" style="padding:22px">
        <h3 style="font-size:19px">Why do you check licences?</h3>
        <p class="muted small" style="margin-top:8px">Because the badge is worth nothing if it is
          not checked, and an unlicensed competitor undercutting you on a licensed job is your
          problem as much as the homeowner's.</p>
      </div>
      <div class="card" style="padding:22px">
        <h3 style="font-size:19px">What is the paid placement for?</h3>
        <p class="muted small" style="margin-top:8px">Position on the page, and nothing else. It is
          built from your existing profile, it is labelled wherever it appears, and slots are
          limited so the top of the page still means something.</p>
      </div>
    </div>
  </div>
</section>

<section class="sect-tight dark">
  <div class="wrap" style="display:flex;gap:20px;align-items:center;justify-content:space-between;flex-wrap:wrap">
    <div>
      <h2 style="font-size:28px">Get listed in <?= e($market['name']) ?></h2>
      <p class="muted" style="margin-top:8px;max-width:52ch">
        We are signing up the first tradespeople now. Send your details and we will get your
        profile checked and live.
      </p>
    </div>
    <a class="btn btn-primary btn-lg" href="<?= e(url('/contact')) ?>">List your business</a>
  </div>
</section>
