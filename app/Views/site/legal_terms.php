<?php
/**
 * Terms of use.
 *
 * Written to describe what this site actually does, in plain words, because
 * Stripe's review reads it and so do homeowners deciding whether to pay. It
 * has not been reviewed by a lawyer — that is a launch task, noted in
 * docs/launch.md.
 *
 * @var string $updated
 * @var array  $market
 */
?>
<section class="sect-tight">
  <div class="wrap prose">
    <div class="eyebrow eyebrow-brass">Legal</div>
    <h1 style="font-size:clamp(28px,4.4vw,42px);margin-top:10px">Terms of use</h1>
    <p class="muted small">Last updated <?= e($updated) ?>.</p>

    <h2>What Fix Listed is</h2>
    <p>Fix Listed is a directory and a jobs board for <?= e($market['name']) ?>. Homeowners
      describe work they need done. Tradespeople list their business and quote for that work.</p>
    <p><strong>We are not a party to the work itself.</strong> We do not employ the tradespeople
      listed here, we do not supervise their work, and we do not handle the money for a job. When
      you hire someone found here, the agreement is between you and them.</p>

    <h2>The listing fee</h2>
    <p>Posting a job costs a flat fee, shown before you pay, charged once per job. It pays for the
      listing to be published and shown to tradespeople covering your county. It is not a deposit
      against the work and it is not a commission.</p>
    <p>If nobody quotes your job within the refund window shown at checkout, the fee is refunded in
      full, automatically, to the card that paid it. If you take your listing down before that
      window closes, the same applies.</p>
    <p>Payments are processed by Stripe. Fix Listed never receives or stores your card number.</p>

    <h2>Using the site</h2>
    <p>You agree to post accurate information, to post only work you genuinely intend to have done,
      and not to use the site to advertise unrelated goods or services. We may remove a listing or
      a profile that is inaccurate, abusive, duplicated, or posted in bad faith, and we may refund
      or decline to refund accordingly.</p>

    <h2>Tradespeople and verification</h2>
    <p>Where a profile shows a licence, insurance or background check as verified, it means we saw
      a document and checked it against the issuing authority where one is publicly searchable. It
      is a check at a point in time, not a guarantee, and it is not a substitute for your own
      judgement. <strong>Confirm current licensing and insurance directly with anyone you hire.</strong></p>
    <p>Paid placement changes where a business appears on a page. It never changes a rating, a
      review or a verification badge, and it is labelled wherever it appears.</p>

    <h2>Reviews</h2>
    <p>Reviews come from people who posted the job being reviewed. We remove reviews that are
      abusive, that identify someone personally, or that we have reason to believe were not written
      by the customer. We do not remove a review for being negative, and we do not sell the removal
      of reviews.</p>

    <h2>What we do not promise</h2>
    <p>We do not promise that you will receive quotes, that a quote will be acceptable, or that any
      particular tradesperson will be available. The site is provided as it is. To the extent the
      law allows, Fix Listed is not liable for the quality, timing, safety or cost of work arranged
      through it, and our liability for the service itself is limited to the fee you paid us.</p>
    <p>Nothing here limits rights you have under Illinois or United States consumer law that cannot
      be limited by agreement.</p>

    <h2>Changes</h2>
    <p>If these terms change, the date at the top changes with them. A change does not apply
      retroactively to a listing already paid for.</p>

    <h2>Contact</h2>
    <p>Questions about these terms: <a href="mailto:hello@fixlisted.com">hello@fixlisted.com</a>.</p>
  </div>
</section>
