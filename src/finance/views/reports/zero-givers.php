<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$downloadUrl = SystemURLs::getRootPath() . '/finance/reports/zero-givers/download';
?>

<?php if ($message !== '') : ?>
    <div class="alert alert-warning" role="alert" id="zeroGiversMessage">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
        <strong><?= gettext('No Data Found') ?></strong>
        <?= InputUtils::escapeHTML($message) ?>
    </div>
<?php endif; ?>

<form method="get" action="<?= InputUtils::escapeAttribute($downloadUrl) ?>" id="zeroGiversForm">
    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= gettext('Filters') ?></h3></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="DateStart"><?= gettext('Report Start Date') ?></label>
                    <input type="text" class="form-control date-picker" name="DateStart" id="DateStart" maxlength="10" value="<?= InputUtils::escapeAttribute(DateTimeUtils::formatForDatePicker($criteria['dateStart'])) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="DateEnd"><?= gettext('Report End Date') ?></label>
                    <input type="text" class="form-control date-picker" name="DateEnd" id="DateEnd" maxlength="10" value="<?= InputUtils::escapeAttribute(DateTimeUtils::formatForDatePicker($criteria['dateEnd'])) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title" id="letterheadLabel"><?= gettext('Report Heading') ?></h3></div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-3" role="radiogroup" aria-labelledby="letterheadLabel">
                <?php foreach (['graphic' => gettext('Graphic'), 'address' => gettext('Church Address'), 'none' => gettext('Blank')] as $value => $label) : ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="letterhead" value="<?= $value ?>" id="letterhead<?= $value ?>" <?= $criteria['letterhead'] === $value ? 'checked' : '' ?>>
                        <label class="form-check-label" for="letterhead<?= $value ?>"><?= InputUtils::escapeHTML($label) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-secondary" href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/finance/reports') ?>"><?= gettext('Back') ?></a>
        <button type="submit" class="btn btn-primary" name="output" value="pdf" id="downloadZeroGivers"><i class="fa-solid fa-file-pdf me-1"></i><?= gettext('Download PDF') ?></button>
        <button type="submit" class="btn btn-outline-primary" name="output" value="csv" id="downloadZeroGiversCsv"><i class="fa-solid fa-file-csv me-1"></i><?= gettext('Download CSV') ?></button>
    </div>
</form>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
