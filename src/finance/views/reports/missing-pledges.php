<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$downloadUrl = SystemURLs::getRootPath() . '/finance/reports/missing-pledges/download';
?>

<?php if ($message !== '') : ?>
    <div class="alert alert-warning" role="alert" id="missingPledgesMessage"><?= InputUtils::escapeHTML($message) ?></div>
<?php endif; ?>

<form method="get" action="<?= InputUtils::escapeAttribute($downloadUrl) ?>" id="missingPledgesForm">
    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= gettext('Fiscal Years') ?></h3></div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ([
                    ['pledgedFY', gettext('Pledged for'), $criteria['pledgedFyId']],
                    ['missingFY', gettext('No pledge for'), $criteria['missingFyId']],
                ] as [$name, $label, $selectedId]) : ?>
                    <div class="col-md-6">
                        <label class="form-label" for="<?= $name ?>"><?= InputUtils::escapeHTML($label) ?></label>
                        <select class="form-select" name="<?= $name ?>" id="<?= $name ?>">
                            <?php foreach ($fiscalYears as $fiscalYear) : ?>
                                <option value="<?= (int) $fiscalYear['id'] ?>" <?= $selectedId === $fiscalYear['id'] ? 'selected' : '' ?>><?= InputUtils::escapeHTML($fiscalYear['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="form-text mb-0 mt-3"><?= gettext('Inactive families are left out. Payments do not count as pledges.') ?></p>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-secondary" href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/finance/reports') ?>"><?= gettext('Back') ?></a>
        <button type="submit" class="btn btn-primary" name="output" value="pdf" id="downloadMissingPledges"><i class="fa-solid fa-file-pdf me-1"></i><?= gettext('Download PDF') ?></button>
        <button type="submit" class="btn btn-outline-primary" name="output" value="csv" id="downloadMissingPledgesCsv"><i class="fa-solid fa-file-csv me-1"></i><?= gettext('Download CSV') ?></button>
    </div>
</form>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
