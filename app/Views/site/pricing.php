<?php
/**
 * @var int   $fee
 * @var int   $boost
 * @var int   $spotlight
 * @var int   $listingDays
 * @var int   $refundHours
 * @var array $market
 */
require_once __DIR__ . '/../partials/icons.php';
$tick = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';
?>

<section class="sect-tight">
  <div class="wrap" style="text-align:center">
    <div class="eyebrow eyebrow-brass">Pricing</div>
    <h1 style="font-size:clamp(32px,5vw,52px);margin-top:12px">One fee. No percentage.</h1>
    <p class="muted" style="margin:16px auto 0;max-width:54ch;font-size:17px">
      Most directories take a cut of the job or sell the same lead to four people. Fix Listed
      charges the homeowner once, up front, and takes nothing else from anybody.
    </p>
  </div>
</section>

<section class="sect-tight">
  <div class="wrap grid g3">
    <div class="tier best">
      <span class="ribbon">Homeowners</span>
      <div>
        <div class="eyebrow">Post a job</div>
        <div class="price"><?= e(money($fee)) ?><small>once</small></div>
      </div>
      <ul>
        <li><?= $tick ?> Seen by every tradesperson covering your county</li>
        <li><?= $tick ?> Stays live for <?= $listingDays ?> days</li>
        <li><?= $tick ?> Unlimited quotes, no extra charge</li>
        <li><?= $tick ?> Your number stays private until you share it</li>
        <li><?= $tick ?> Refunded in full if nobody quotes within <?= $refundHours ?> hours</li>
      </ul>
      <a class="btn btn-primary btn-block" href="<?= e(url('/post-a-job')) ?>">Post a job</a>
      <p class="tiny muted" style="text-align:center">
        Card handled by Stripe. Fix Listed never sees the number.
      </p>
    </div>

    <div class="tier">
      <div>
        <div class="eyebrow">Tradespeople</div>
        <div class="price">$0<small>always</small></div>
      </div>
      <ul>
        <li><?= $tick ?> A full profile with photos and reviews</li>
        <li><?= $tick ?> Quote any job on the board</li>
        <li><?= $tick ?> No commission on work you win</li>
        <li><?= $tick ?> No per-lead charge, ever</li>
        <li><?= $tick ?> You own the customer relationship</li>
      </ul>
      <a class="btn btn-dark btn-block" href="<?= e(url('/for-pros')) ?>">List your business</a>
      <p class="tiny muted" style="text-align:center">Licence and insurance checked before you go live.</p>
    </div>

    <div class="tier">
      <div>
        <div class="eyebrow">Optional placement</div>
        <div class="price"><?= e(money($boost)) ?><small>per month</small></div>
      </div>
      <ul>
        <li><?= $tick ?> Boost — above the standard listings in your trade</li>
        <li><?= $tick ?> Spotlight, <?= e(money($spotlight)) ?>/mo — top of the county, limited slots</li>
        <li><?= $tick ?> Built from your profile, so there is no ad to write</li>
        <li><?= $tick ?> Always labelled as paid placement</li>
        <li><?= $tick ?> Cancel any month</li>
      </ul>
      <a class="btn btn-ghost btn-block" href="<?= e(url('/for-pros')) ?>">How placement works</a>
      <p class="tiny muted" style="text-align:center">Placement changes order. It never changes a rating or a badge.</p>
    </div>
  </div>
</section>

<section class="sect-tight dark">
  <div class="wrap">
    <div class="head"><div><h2 style="font-size:30px">Why a fee at all?</h2></div></div>
    <div class="grid g3">
      <div>
        <h3 style="font-size:19px">It keeps the board real</h3>
        <p class="muted small" style="margin-top:8px">A free board fills with people who are
          curious rather than committed. <?= e(money($fee)) ?> is small enough for a real job and
          large enough to stop idle posts — which is what makes it worth a tradesperson's time to
          read.</p>
      </div>
      <div>
        <h3 style="font-size:19px">It replaces the commission</h3>
        <p class="muted small" style="margin-top:8px">Charging once up front is how we can take
          nothing from the work itself. The alternative is 15–20% of every job, which the
          tradesperson prices back into your quote anyway.</p>
      </div>
      <div>
        <h3 style="font-size:19px">Your lead is not resold</h3>
        <p class="muted small" style="margin-top:8px">Your job is posted once, to the pros who
          cover your county. It is not sold on to a call centre, and it is not sold four times over
          to whoever pays the most.</p>
      </div>
    </div>
  </div>
</section>
