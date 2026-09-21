<?php
/**
 * What to check, and where, for a given trade in Illinois.
 *
 * Illinois does not license trades uniformly, and getting this wrong cuts
 * both ways: rejecting a good electrician for having no state licence (there
 * is no such thing) is as bad as passing an unlicensed plumber because the
 * number looked plausible.
 *
 * @var array<int,string> $trades  the trade names on this application
 * @var string $licenceNumber
 * @var string $licenceState
 */
require_once __DIR__ . '/icons.php';

/**
 * @return array{body:string,link:string,label:string,state:bool}
 */
function licence_guidance(array $tradeNames): array
{
    $names = mb_strtolower(implode(' ', $tradeNames));

    // Plumbing is licensed by Public Health in Illinois, not by IDFPR —
    // looking a plumber up in the IDFPR register finds nothing and means
    // nothing.
    if (str_contains($names, 'plumb')) {
        return [
            'body'  => 'Plumbers are licensed by the Illinois Department of Public Health, not IDFPR. '
                     . 'Licence numbers usually look like 058-xxxxxx. Check the number and that it is current.',
            'link'  => 'https://dph.illinois.gov/topics-services/environmental-health-protection/plumbing.html',
            'label' => 'IDPH plumbing licensing',
            'state' => true,
        ];
    }

    if (str_contains($names, 'roof')) {
        return [
            'body'  => 'Roofing contractors are licensed by IDFPR under the Roofing Industry Licensing Act. '
                     . 'Numbers usually look like 104-xxxxxx. Search the register by name or number.',
            'link'  => 'https://idfpr.illinois.gov/licenselookup/licenselookup.asp',
            'label' => 'IDFPR licence lookup',
            'state' => true,
        ];
    }

    // No statewide electrician licence exists in Illinois. Saying "unlicensed"
    // about an electrician here would be wrong.
    if (str_contains($names, 'electric')) {
        return [
            'body'  => 'Illinois has no statewide electrician licence — electricians are licensed by the '
                     . 'city or county. Ask which municipality issued it (Rockford and Freeport both run '
                     . 'their own) and check with that office. An electrician with no state number is normal, '
                     . 'not a red flag.',
            'link'  => '',
            'label' => '',
            'state' => false,
        ];
    }

    return [
        'body'  => 'Illinois does not license this trade at state level. What matters here is the insurance: '
                 . 'ask for a certificate of liability insurance and check it is current and in the business '
                 . 'name. Some towns also register contractors, so it is worth asking.',
        'link'  => '',
        'label' => '',
        'state' => false,
    ];
}

$guide = licence_guidance($trades);
?>
<div style="padding:16px 20px;border-top:1px solid var(--line);background:var(--paper-2)">
  <div class="eyebrow" style="margin-bottom:8px">How to check this trade</div>
  <p class="tiny muted" style="line-height:1.55"><?= e($guide['body']) ?></p>

  <?php if ($guide['link'] !== ''): ?>
    <p style="margin-top:12px">
      <a class="btn btn-ghost btn-sm btn-block" target="_blank" rel="noopener noreferrer"
         href="<?= e($guide['link']) ?>"><?= e($guide['label']) ?> <?= icon('arrow', 13) ?></a>
    </p>
  <?php endif; ?>

  <?php if (!$guide['state'] && $licenceNumber !== ''): ?>
    <p class="tiny muted" style="margin-top:10px">
      They gave a number anyway (<span class="mono"><?= e(trim($licenceState . ' ' . $licenceNumber)) ?></span>) —
      ask what issued it before ticking the licence box.
    </p>
  <?php endif; ?>

  <p class="tiny muted" style="margin-top:12px">
    <strong style="color:var(--text)">Insurance is the one to insist on.</strong> Ask for a certificate of
    liability insurance, check the expiry date and that the business name matches. That is what protects a
    homeowner when something goes wrong, whatever the licensing rules say.
  </p>
</div>
