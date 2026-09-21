<?php require_once __DIR__ . '/../partials/icons.php'; ?>
<div class="empty">
  <div class="ic"><?= icon('user', 24) ?></div>
  <h3>No listing on this account</h3>
  <p>This account is signed in but has no tradesperson listing attached to it. If you meant to
     list a business, the form takes a couple of minutes.</p>
  <a class="btn btn-primary" href="<?= e(url('/list-your-business')) ?>">List your business</a>
</div>
