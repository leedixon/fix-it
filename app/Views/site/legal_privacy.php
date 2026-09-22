<?php
/**
 * Privacy.
 *
 * Describes what the application actually collects — the columns in the
 * schema, not a template downloaded from somewhere. Not reviewed by a lawyer;
 * see docs/launch.md.
 *
 * @var string $updated
 */
?>
<?php require __DIR__ . '/../partials/crumbs.php'; ?>

<section class="sect-tight">
  <div class="wrap prose">
    <div class="eyebrow eyebrow-brass">Legal</div>
    <h1 style="font-size:clamp(28px,4.4vw,42px);margin-top:10px">Privacy</h1>
    <p class="muted small">Last updated <?= e($updated) ?>.</p>

    <h2>What we collect</h2>
    <ul>
      <li><strong>When you post a job:</strong> your name, email, phone number, the town and ZIP
        code of the work, and what you wrote about the job.</li>
      <li><strong>When you list a business:</strong> the above, plus your trades, the counties you
        cover, your licence and insurance details, and anything you put on your profile.</li>
      <li><strong>When you join the waiting list:</strong> your name, email, role, and the counties
        you chose.</li>
      <li><strong>Automatically:</strong> your IP address and the time, kept for spam and abuse
        prevention.</li>
    </ul>

    <h2>What is published, and what is not</h2>
    <p>A job listing shows the work, the town, the ZIP code, the timing and the budget.
      <strong>Your name, street address, email and phone number are not published</strong> and are
      not shown to tradespeople browsing the board. They are shared with a tradesperson when you
      choose to share them.</p>
    <p>A business profile is public by design — that is what a directory is. Do not put anything on
      a profile you would not want on a public web page.</p>

    <h2>Payments</h2>
    <p>Card payments are processed by Stripe. Card numbers go to Stripe directly and never reach
      our server. We keep a record that a payment happened, what it was for, and Stripe's reference
      for it.</p>

    <h2>Email</h2>
    <p>We send transactional email — confirmations, quote notifications, receipts — through a
      transactional email provider. We do not sell email addresses, and we do not add you to a
      marketing list because you posted a job.</p>

    <h2>Who else sees your data</h2>
    <p>Our hosting provider, our database, our email provider and Stripe, each only as far as they
      need to in order to do their part. We do not sell personal information to anyone, and we do
      not pass your details to lead-generation companies. If we are ever required by law to hand
      something over, we will.</p>

    <h2>How long we keep it</h2>
    <p>Job listings and profiles stay while they are live and for a period afterwards, so that
      reviews and receipts still make sense. Payment records are kept as long as tax and accounting
      rules require.</p>

    <h2>Your choices</h2>
    <p>Ask us for a copy of what we hold about you, ask us to correct it, or ask us to delete it:
      <a href="mailto:hello@fixlisted.com">hello@fixlisted.com</a>. Deleting an account does not
      remove a payment record we are required to keep, and it does not retract a review you left
      about someone else's work.</p>

    <h2>Cookies and measurement</h2>
    <p>One session cookie, so the site can remember you between pages while you are posting a job.
      That one is necessary for the site to work and cannot be turned off.</p>
    <p>We also use Google Tag Manager to load Google Analytics, which sets cookies to count
      visits and work out which pages people find useful. It tells us how many people looked at a
      county page or started a job posting. It does not tell us who you are, and we do not sell
      or share it with anyone.</p>
    <p>You can refuse these in your browser, or install Google's opt-out add-on, and the site
      will work exactly the same. Most browsers also honour Do Not Track and Global Privacy
      Control signals for this kind of measurement.</p>
    <p>We set no advertising or cross-site retargeting cookies. Paid placement on this site is
      sold directly to tradespeople and is labelled where it appears; it does not follow you
      around the internet. If that ever changes, this page will say so before it does.</p>

    <h2>Children</h2>
    <p>The site is not intended for anyone under 18 and we do not knowingly collect their
      information.</p>
  </div>
</section>
