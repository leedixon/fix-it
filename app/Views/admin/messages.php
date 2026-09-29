<?php
/**
 * Messages sent from the contact form.
 *
 * Expanded in place rather than on their own page: a message is a paragraph,
 * and a list of twenty is read by scanning, not by clicking into twenty
 * screens and back out again. The disclosure is the site's checkbox pattern,
 * so it works with scripting off.
 *
 * Nothing here deletes. Read and unread are the only states, because a record
 * of somebody asking for help outlives the reply.
 *
 * @var array $messages @var int $unread
 */
require_once __DIR__ . '/../partials/icons.php';

$badge = static function (string $audience): string {
    return match ($audience) {
        'homeowner' => '<span class="badge b-flat">Homeowner</span>',
        'pro'       => '<span class="badge b-live">Tradesperson</span>',
        default     => '<span class="badge b-flat">Other</span>',
    };
};
?>
<div class="adm-head">
  <div>
    <h1>Messages</h1>
    <p class="muted small">
      Everything sent from the contact form. Saved here before the email goes out, so a message
      survives a mail problem — a missing "emailed" mark below means nobody was notified.
    </p>
  </div>
</div>

<?php if ($messages === []): ?>
  <div class="card" style="padding:40px;text-align:center">
    <div class="ic" style="width:48px;height:48px;border-radius:99px;background:var(--paper-2);
                           display:grid;place-items:center;margin:0 auto;color:var(--text-3)">
      <?= icon('mail', 22) ?>
    </div>
    <h3 style="margin-top:14px;font-size:19px">No messages yet</h3>
    <p class="muted small" style="margin-top:8px">
      They arrive from <a href="<?= e(url('/contact')) ?>">the contact page</a>.
    </p>
  </div>
<?php else: ?>
  <div class="panel">
    <div class="panel-h">
      <h3>Inbox</h3>
      <span class="tiny muted" style="margin-left:auto">
        <?= (int) $unread ?> unread of <?= count($messages) ?>
      </span>
    </div>

    <?php foreach ($messages as $m): ?>
      <?php $isUnread = $m['read_at'] === null; ?>
      <div class="msg<?= $isUnread ? ' msg-unread' : '' ?>">
        <input class="msg-state" type="checkbox" id="msg-<?= (int) $m['id'] ?>"
               <?= $isUnread ? 'checked' : '' ?>>
        <label class="msg-head" for="msg-<?= (int) $m['id'] ?>">
          <span class="msg-who">
            <?php if ($isUnread): ?><span class="msg-dot" aria-label="Unread"></span><?php endif; ?>
            <b><?= e($m['name']) ?></b>
            <?= $badge((string) $m['audience']) ?>
          </span>
          <span class="msg-sub">
            <?= e($m['subject'] !== '' ? $m['subject'] : excerpt((string) $m['message'], 70)) ?>
          </span>
          <span class="msg-when tiny muted mono"><?= e(ago((string) $m['created_at'])) ?></span>
        </label>

        <div class="msg-body">
          <dl class="msg-facts">
            <dt>Email</dt>
            <dd><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a></dd>
            <?php if ($m['phone'] !== ''): ?>
              <dt>Phone</dt><dd><?= e($m['phone']) ?></dd>
            <?php endif; ?>
            <?php if ($m['job_reference'] !== ''): ?>
              <dt>Job</dt><dd class="mono"><?= e($m['job_reference']) ?></dd>
            <?php endif; ?>
            <?php if ($m['business_name'] !== ''): ?>
              <dt>Business</dt><dd><?= e($m['business_name']) ?></dd>
            <?php endif; ?>
            <dt>Sent</dt>
            <dd><?= e($m['created_at']) ?>
              <?php if ($m['notified_at'] === null): ?>
                <span class="badge b-pending" title="The notification email did not go">not emailed</span>
              <?php endif; ?>
            </dd>
          </dl>

          <p class="msg-text"><?= nl2br(e((string) $m['message'])) ?></p>

          <div class="msg-actions">
            <a class="btn btn-primary btn-sm"
               href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode(
                   $m['subject'] !== '' ? 'Re: ' . $m['subject'] : 'Your message to Fix Listed'
               )) ?>">Reply by email</a>

            <form method="post" action="<?= e(url('/admin/messages/' . (int) $m['id'] . '/read')) ?>">
              <?= \FixListed\Core\Csrf::field() ?>
              <input type="hidden" name="state" value="<?= $isUnread ? 'read' : 'unread' ?>">
              <button class="btn btn-ghost btn-sm" type="submit">
                <?= $isUnread ? 'Mark read' : 'Mark unread' ?>
              </button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
