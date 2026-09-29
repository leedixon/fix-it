<?php
/**
 * The contact form.
 *
 * The tabs are radio buttons. Checking one reveals its own fields through a
 * sibling selector and nothing else changes — so the page works with
 * scripting off, the choice survives a validation failure because it is a
 * submitted value rather than the state of a widget, and a screen reader is
 * told it is a radio group, which is what it is.
 *
 * A hidden tab's fields are display:none, not clipped. The disclosure pattern
 * elsewhere on this site clips, so a control stays reachable by keyboard —
 * right for a thing somebody chose to collapse, wrong here. A job reference
 * does not apply to a tradesperson, and a field that does not apply should
 * not be in their tab order at all.
 *
 * Nothing is required conditionally. The server decides what matters, because
 * a required attribute on a field nobody can see is a form that cannot be
 * submitted and will not say why.
 *
 * @var array $market @var array $old @var array $errors @var array $crumbs
 */
require_once __DIR__ . '/../partials/icons.php';

$old = $old ?? [];
$errors = $errors ?? [];
$v   = static fn (string $k): string => (string) ($old[$k] ?? '');
$err = static fn (string $k): string => (string) ($errors[$k] ?? '');
$audience = in_array($v('audience'), ['homeowner', 'pro', 'other'], true)
    ? $v('audience')
    : 'homeowner';
?>
<?php require __DIR__ . '/../partials/crumbs.php'; ?>

<section class="sect-tight">
  <div class="wrap" style="max-width:720px">
    <div class="eyebrow eyebrow-brass">Contact</div>
    <h1 style="font-size:clamp(30px,4.6vw,44px);margin-top:12px">Talk to a person</h1>
    <p class="muted" style="margin-top:14px;font-size:17px">
      Fix Listed is small and local. Every message is read by somebody who can actually do
      something about it, usually the same day.
    </p>

    <?php if ($err('_form') !== ''): ?>
      <div class="flash flash-bad" style="margin-top:22px">
        <?= icon('alert', 17) ?><span><?= e($err('_form')) ?></span>
      </div>
    <?php endif; ?>

    <form class="panel contact-form" method="post" action="<?= e(url('/contact')) ?>"
          style="margin-top:26px">
      <?= \FixListed\Core\Csrf::field() ?>

      <?php
      /*
       * The honeypot. Clipped rather than hidden with display:none, because
       * the bots worth catching check for that — and labelled and
       * aria-hidden so a screen reader is not asked to fill in a trap.
       */
      ?>
      <div class="vh" aria-hidden="true">
        <label for="website">Leave this empty</label>
        <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
      </div>

      <div class="tabs" role="radiogroup" aria-label="Who is getting in touch">
        <?php foreach ([
          'homeowner' => ['user',  'I need work doing'],
          'pro'       => ['tools', 'I do the work'],
          'other'     => ['info',  'Something else'],
        ] as $key => [$ico, $label]): ?>
          <input class="tab-state" type="radio" name="audience" id="aud-<?= e($key) ?>"
                 value="<?= e($key) ?>"<?= $audience === $key ? ' checked' : '' ?>>
          <label class="tab" for="aud-<?= e($key) ?>">
            <?= icon($ico, 15) ?><span><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div style="padding:22px">
        <div class="g2" style="display:grid;gap:14px;grid-template-columns:1fr 1fr">
          <label class="field">
            <span>Your name</span>
            <input type="text" name="name" maxlength="120" required value="<?= e($v('name')) ?>">
            <?php if ($err('name') !== ''): ?><em class="bad"><?= e($err('name')) ?></em><?php endif; ?>
          </label>
          <label class="field">
            <span>Email</span>
            <input type="email" name="email" maxlength="191" required value="<?= e($v('email')) ?>">
            <?php if ($err('email') !== ''): ?><em class="bad"><?= e($err('email')) ?></em><?php endif; ?>
          </label>
        </div>

        <label class="field">
          <span>Phone <i class="muted">optional</i></span>
          <input type="tel" name="phone" maxlength="32" value="<?= e($v('phone')) ?>">
        </label>

        <?php /* Shown when "I need work doing" is the chosen tab. */ ?>
        <div class="tab-pane" data-for="homeowner">
          <label class="field">
            <span>Job reference <i class="muted">optional</i></span>
            <input type="text" name="job_reference" maxlength="32"
                   placeholder="<?= e($market['code']) ?>-4K2P9M" value="<?= e($v('job_reference')) ?>">
            <em class="hint">On the receipt we emailed you, if your message is about a job you posted.</em>
          </label>
        </div>

        <div class="tab-pane" data-for="pro">
          <label class="field">
            <span>Business name <i class="muted">optional</i></span>
            <input type="text" name="business_name" maxlength="160" value="<?= e($v('business_name')) ?>">
            <em class="hint">
              Want to be listed? You can fill the whole thing in at
              <a href="<?= e(url('/list-your-business')) ?>">list your business</a> — it is free,
              and it asks the questions we would only email you about anyway.
            </em>
          </label>
        </div>

        <label class="field">
          <span>Subject <i class="muted">optional</i></span>
          <input type="text" name="subject" maxlength="200" value="<?= e($v('subject')) ?>">
        </label>

        <label class="field">
          <span>Message</span>
          <textarea name="message" rows="7" required minlength="20"
                    placeholder="Tell us what is going on. The more detail, the better the reply."
          ><?= e($v('message')) ?></textarea>
          <?php if ($err('message') !== ''): ?><em class="bad"><?= e($err('message')) ?></em><?php endif; ?>
        </label>

        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:18px">
          <button class="btn btn-primary" type="submit">Send message</button>
          <span class="tiny muted">We reply by email, usually the same day.</span>
        </div>
      </div>
    </form>

    <div class="grid g2" style="margin-top:26px">
      <div class="card" style="padding:20px">
        <h2 style="font-size:17px"><?= icon('mail', 16) ?> Prefer email?</h2>
        <p class="muted small" style="margin-top:8px">
          <a href="mailto:hello@fixlisted.com">hello@fixlisted.com</a> reaches the same place.
          Include a job reference if your message is about one.
        </p>
      </div>
      <div class="card" style="padding:20px">
        <h2 style="font-size:17px"><?= icon('facebook', 16) ?> On Facebook</h2>
        <p class="muted small" style="margin-top:8px">
          <a href="https://www.facebook.com/fixlisted" target="_blank" rel="noopener me">
            facebook.com/fixlisted</a> — news, and new jobs as they are posted.
        </p>
      </div>
    </div>

    <div class="seal" style="margin-top:20px;border:1px solid var(--line);border-radius:4px">
      <?= icon('shield', 16) ?>
      <span>We never ask for card details, passwords or bank information by email or on Facebook.
      If something claiming to be us does, it is not us.</span>
    </div>
  </div>
</section>
