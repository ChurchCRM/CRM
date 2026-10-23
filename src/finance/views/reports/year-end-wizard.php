<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$steps = [
    1 => gettext('Close the books'),
    2 => gettext('Review'),
    3 => gettext('Generate statements'),
    4 => gettext('Deliver'),
];
$step = max(1, min(4, (int) ($step ?? 1)));
?>

<div class="card mb-3">
  <div class="card-body py-3">
    <div class="d-flex flex-wrap gap-3">
<?php foreach ($steps as $n => $label) : ?>
      <div class="<?= $n === $step ? 'fw-bold text-primary' : 'text-secondary' ?>">
        <span class="badge <?= $n === $step ? 'bg-primary' : 'bg-secondary-lt' ?> me-1"><?= $n ?></span>
        <?= InputUtils::escapeHTML($label) ?>
      </div>
<?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= InputUtils::escapeHTML($steps[$step]) ?></h3>
  </div>
  <div class="card-body">
<?php if ($step === 1) : ?>
    <p><?= gettext('Before you generate statements, make sure every gift that belongs in this year is recorded.') ?></p>
    <ul>
      <li><?= gettext('Enter any December deposits that arrived in January but were postmarked by December 31.') ?></li>
      <li><?= gettext('Close open deposits for the year.') ?></li>
      <li><?= gettext('Confirm fund names and categories look right for the statements.') ?></li>
    </ul>
    <p class="text-secondary"><?= gettext('Fiscal year') ?>: <strong><?= InputUtils::escapeHTML($fyLabel) ?></strong> (<?= InputUtils::escapeHTML($fyStart) ?> – <?= InputUtils::escapeHTML($fyEnd) ?>)</p>
    <div class="d-flex gap-2 mt-4">
      <a class="btn btn-secondary" href="<?= $sRootPath ?>/finance/deposit/search"><?= gettext('Review deposits') ?></a>
      <a class="btn btn-primary" href="<?= $sRootPath ?>/finance/reports/year-end?step=2"><?= gettext('Books are closed') ?></a>
    </div>

<?php elseif ($step === 2) : ?>
    <p><?= gettext('Spot-check a few families before you generate the full set.') ?></p>
    <ul>
      <li><?= gettext('Open a family that gave regularly and confirm the total looks right.') ?></li>
      <li><?= gettext('Check one large gift and one small recurring gift.') ?></li>
      <li><?= gettext('Confirm the “no goods or services” wording is correct for your church.') ?></li>
    </ul>
    <div class="d-flex gap-2 mt-4">
      <a class="btn btn-secondary" href="<?= $sRootPath ?>/finance/reports/year-end?step=1"><?= gettext('Back') ?></a>
      <a class="btn btn-primary" href="<?= $sRootPath ?>/finance/reports/year-end?step=3"><?= gettext('Ready to generate') ?></a>
    </div>

<?php elseif ($step === 3) : ?>
    <p><?= gettext('Generate the full PDF of statements for the fiscal year. You can print it or keep it for your records.') ?></p>
    <form method="post" action="<?= InputUtils::escapeAttribute($sRootPath . '/finance/reports/year-end/generate') ?>">
      <input type="hidden" name="ReportType" value="Giving Report">
      <input type="hidden" name="DateStart" value="<?= InputUtils::escapeAttribute($fyStart) ?>">
      <input type="hidden" name="DateEnd" value="<?= InputUtils::escapeAttribute($fyEnd) ?>">
      <input type="hidden" name="datetype" value="Deposit">
      <input type="hidden" name="letterhead" value="address">
      <input type="hidden" name="remittance" value="no">
      <input type="hidden" name="minimum" value="0">
      <input type="hidden" name="output" value="pdf">
      <div class="d-flex gap-2 mt-4">
        <a class="btn btn-secondary" href="<?= $sRootPath ?>/finance/reports/year-end?step=2"><?= gettext('Back') ?></a>
        <button type="submit" class="btn btn-primary"><?= gettext('Generate statements') ?></button>
        <a class="btn btn-outline-primary" href="<?= $sRootPath ?>/finance/reports/year-end?step=4"><?= gettext('Continue to delivery') ?></a>
      </div>
    </form>

<?php else : ?>
    <p><?= gettext('Deliver the statements.') ?></p>
    <ul>
      <li><?= gettext('Email statements to the head of household and spouse. Children are not included.') ?></li>
      <li><?= gettext('Print and mail the PDF for families without email.') ?></li>
      <li><?= gettext('Keep a copy for your records.') ?></li>
    </ul>
    <p class="text-secondary"><?= gettext('Individual family email is available from each family page. Bulk email of the full statement set is a follow-up.') ?></p>
    <div class="d-flex gap-2 mt-4">
      <a class="btn btn-secondary" href="<?= $sRootPath ?>/finance/reports/year-end?step=3"><?= gettext('Back') ?></a>
      <a class="btn btn-primary" href="<?= $sRootPath ?>/finance/reports"><?= gettext('Done') ?></a>
    </div>
<?php endif; ?>
  </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
