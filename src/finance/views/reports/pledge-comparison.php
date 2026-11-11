<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$downloadUrl = SystemURLs::getRootPath() . '/finance/reports/pledge-comparison/download';
?>

<form method="get" action="<?= InputUtils::escapeAttribute($downloadUrl) ?>" id="pledgeComparisonForm">
    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= gettext('Fiscal Years') ?></h3></div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ([
                    ['firstFY', gettext('First Fiscal Year'), $criteria['firstFyId']],
                    ['secondFY', gettext('Second Fiscal Year'), $criteria['secondFyId']],
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
        </div>
    </div>

    <div class="d-flex gap-2">
        <a class="btn btn-secondary" href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/finance/reports') ?>"><?= gettext('Back') ?></a>
        <button type="submit" class="btn btn-primary" id="downloadPledgeComparison"><i class="fa-solid fa-file-pdf me-1"></i><?= gettext('Download PDF') ?></button>
    </div>
</form>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
