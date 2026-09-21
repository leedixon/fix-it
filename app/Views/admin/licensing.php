<?php
/**
 * @var array $rules @var array $trades @var array|null $edit
 */
require_once __DIR__ . '/../partials/icons.php';
$e2 = static fn (?string $v): string => e((string) ($v ?? ''));
?>
<div class="adm-h">
  <div>
    <h1>Licensing</h1>
    <p>Who licenses which trade, in which state. The review screen reads this when you open an
       application.</p>
  </div>
</div>

<div class="flash flash-note">
  <?= icon('info', 17) ?>
  <span>
    <strong style="color:var(--text)">No row means "we do not know", not "no licence needed".</strong>
    A state with nothing entered shows the reviewer an explicit warning rather than a reassuring
    default — a verified badge should never come from a gap in this table. Adding a state is a few
    rows here, not a code change.
  </span>
</div>

<div class="adm-review">
  <div class="panel">
    <div class="panel-h"><h3>What is entered</h3></div>
    <?php if ($rules === []): ?>
      <div class="adm-empty">Nothing yet. Every application will say guidance is missing.</div>
    <?php else: ?>
      <div class="tablewrap">
        <table>
          <thead><tr><th>State</th><th>Trade</th><th>Licensed</th><th>Authority</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($rules as $r): ?>
            <tr>
              <td class="mono"><?= $e2($r['state']) ?></td>
              <td class="wrap-cell">
                <?= $e2($r['trade_name']) ?>
                <?php if ((int) $r['trade_id'] === 0): ?>
                  <span class="tiny muted">(fallback)</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?= (int) $r['licensed'] === 1 ? 'b-verified' : 'b-flat' ?>">
                  <?= (int) $r['licensed'] === 1 ? 'state' : 'not state' ?>
                </span>
              </td>
              <td class="wrap-cell tiny">
                <?= $e2($r['authority']) ?>
                <?php if (!empty($r['number_format'])): ?>
                  <br><span class="mono muted"><?= $e2($r['number_format']) ?></span>
                <?php endif; ?>
              </td>
              <td style="display:flex;gap:6px">
                <a class="btn btn-ghost btn-sm" href="<?= e(url_q('/admin/licensing', ['edit' => $r['id']])) ?>">Edit</a>
                <form method="post" action="<?= e(url('/admin/licensing/' . $r['id'] . '/delete')) ?>">
                  <?= \FixListed\Core\Csrf::field() ?>
                  <button class="btn btn-ghost btn-sm" type="submit">Remove</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <form class="panel" method="post" action="<?= e(url('/admin/licensing')) ?>">
    <?= \FixListed\Core\Csrf::field() ?>
    <?php if ($edit !== null): ?>
      <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
    <?php endif; ?>

    <div class="panel-h">
      <h3><?= $edit !== null ? 'Edit this rule' : 'Add a rule' ?></h3>
      <?php if ($edit !== null): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/licensing')) ?>">New instead</a>
      <?php endif; ?>
    </div>

    <div style="padding:18px 20px">
      <div class="grid g2" style="gap:0 14px">
        <label class="field">
          <span>State</span>
          <input type="text" name="state" maxlength="2" required
                 value="<?= $e2($edit['state'] ?? '') ?>" placeholder="IL" style="text-transform:uppercase">
        </label>
        <label class="field">
          <span>Trade</span>
          <select name="trade_id">
            <option value="0" <?= (int) ($edit['trade_id'] ?? 0) === 0 ? 'selected' : '' ?>>
              Every other trade
            </option>
            <?php foreach ($trades as $t): ?>
              <option value="<?= (int) $t['id'] ?>"
                <?= (int) ($edit['trade_id'] ?? -1) === (int) $t['id'] ? 'selected' : '' ?>>
                <?= e($t['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <label class="radio" style="margin-bottom:14px">
        <input type="checkbox" name="licensed" value="1" <?= (int) ($edit['licensed'] ?? 0) === 1 ? 'checked' : '' ?>>
        <span><b>The state licenses this trade</b><br>
          <span class="tiny muted">Leave unticked where licensing is municipal or does not exist —
          that is a real answer, not a missing one.</span></span>
      </label>

      <label class="field">
        <span>Who licenses it</span>
        <input type="text" name="authority" maxlength="120"
               value="<?= $e2($edit['authority'] ?? '') ?>" placeholder="Illinois Department of Public Health">
      </label>

      <label class="field">
        <span>Lookup link <span class="hint">— https only, blank if there is no register</span></span>
        <input type="url" name="lookup_url" maxlength="255" value="<?= $e2($edit['lookup_url'] ?? '') ?>"
               placeholder="https://…">
      </label>

      <label class="field">
        <span>What the numbers look like</span>
        <input type="text" name="number_format" maxlength="60"
               value="<?= $e2($edit['number_format'] ?? '') ?>" placeholder="058-xxxxxx">
      </label>

      <label class="field">
        <span>What to tell the reviewer</span>
        <textarea name="guidance" rows="6" maxlength="600"
          placeholder="The thing somebody checking this needs to know — including what would be wrong to assume."><?= $e2($edit['guidance'] ?? '') ?></textarea>
      </label>

      <button class="btn btn-primary btn-block" type="submit">
        <?= $edit !== null ? 'Save this rule' : 'Add it' ?>
      </button>
      <p class="tiny muted" style="margin-top:10px">
        Entering a state and trade that already exists updates it rather than adding a duplicate.
      </p>
    </div>
  </form>
</div>
