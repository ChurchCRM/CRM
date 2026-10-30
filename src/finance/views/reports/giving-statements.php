<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$selected = static fn (string $key): array => array_map('strval', (array) ($query[$key] ?? []));
$selectedFamilies = array_map('strval', $criteria['familyIds']);
$selectedClasses = $selected('classList');
$selectedFunds = $selected('funds');
$byDeposit = $criteria['byDepositDate'];
$base = SystemURLs::getRootPath() . '/finance/reports/giving-statements';
?>

<?php if ($summary !== null && $summary['gifts'] === 0) : ?>
<div class="alert alert-warning" role="alert">
    <i class="fa-solid fa-triangle-exclamation me-1"></i>
    <strong><?= gettext('No Data Found') ?></strong>
    <?= gettext('No records were returned from the previous report. Please adjust your filters or date range and try again.') ?>
</div>
<?php elseif ($summary !== null) : ?>
<div class="card mb-3" id="givingPreview">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-6 col-md-2">
                <div class="text-secondary"><?= gettext('Families') ?></div>
                <div class="h2 mb-0" id="previewFamilies"><?= (int) $summary['families'] ?></div>
            </div>
            <div class="col-6 col-md-2">
                <div class="text-secondary"><?= gettext('Payments') ?></div>
                <div class="h2 mb-0" id="previewGifts"><?= (int) $summary['gifts'] ?></div>
            </div>
            <div class="col-12 col-md-4">
                <div class="text-secondary"><?= gettext('Total') ?></div>
                <div class="h2 mb-0" id="previewTotal"><?= InputUtils::escapeHTML($summary['total']) ?></div>
            </div>
            <div class="col-12 col-md-4 d-flex flex-wrap gap-2 justify-content-md-end">
                <a class="btn btn-primary" id="downloadPdf" target="_blank" rel="noopener" href="<?= InputUtils::escapeAttribute($base . '/download?output=pdf&' . $downloadQuery) ?>">
                    <i class="fa-solid fa-file-pdf me-1"></i><?= gettext('Download PDF') ?>
                </a>
                <a class="btn btn-outline-secondary" id="downloadCsv" href="<?= InputUtils::escapeAttribute($base . '/download?output=csv&' . $downloadQuery) ?>">
                    <i class="fa-solid fa-file-csv me-1"></i><?= gettext('Download CSV') ?>
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<form method="get" action="<?= InputUtils::escapeAttribute($base) ?>" id="givingReportForm" data-preview-url="<?= InputUtils::escapeAttribute($base . '/preview') ?>">
    <input type="hidden" name="preview" value="1">
    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= gettext('Filters') ?></h3></div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label" for="DateStart"><?= gettext('Report Start Date') ?></label>
                    <input type="text" class="form-control date-picker" name="DateStart" id="DateStart" maxlength="10" value="<?= InputUtils::escapeAttribute($criteria['dateStart']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="DateEnd"><?= gettext('Report End Date') ?></label>
                    <input type="text" class="form-control date-picker" name="DateEnd" id="DateEnd" maxlength="10" value="<?= InputUtils::escapeAttribute($criteria['dateEnd']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= gettext('Apply Report Dates To') ?></label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="datetype" value="Payment" id="datetypePayment" <?= $byDeposit ? '' : 'checked' ?>>
                            <label class="form-check-label" for="datetypePayment"><?= gettext('Payment Date') ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="datetype" value="Deposit" id="datetypeDeposit" <?= $byDeposit ? 'checked' : '' ?>>
                            <label class="form-check-label" for="datetypeDeposit"><?= gettext('Deposit Date') ?></label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="deposit"><?= gettext('Filter by Deposit') ?></label>
                <select class="form-select" name="deposit" id="deposit">
                    <option value="0"><?= gettext('All deposits within date range') ?></option>
                    <?php foreach ($deposits as $deposit) : ?>
                        <option value="<?= (int) $deposit['id'] ?>" <?= $criteria['depositId'] === $deposit['id'] ? 'selected' : '' ?>><?= InputUtils::escapeHTML($deposit['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><?= gettext('If deposit is selected, date criteria will be ignored.') ?></div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="classList"><?= gettext('Classification') ?></label>
                <select name="classList[]" id="classList" class="form-select" multiple data-report-select>
                    <?php foreach ($classifications as $classification) : ?>
                        <option value="<?= (int) $classification['id'] ?>" <?= in_array((string) $classification['id'], $selectedClasses, true) ? 'selected' : '' ?>><?= InputUtils::escapeHTML($classification['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-select-all="classList"><?= gettext('Add All Classes') ?></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-clear-all="classList"><?= gettext('Clear All Classes') ?></button>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="family"><?= gettext('Filter by Family') ?></label>
                <select name="family[]" id="family" class="form-select" multiple data-report-select>
                    <?php foreach ($families as $family) : ?>
                        <option value="<?= (int) $family['id'] ?>" <?= in_array((string) $family['id'], $selectedFamilies, true) ? 'selected' : '' ?>><?= InputUtils::escapeHTML($family['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-select-all="family"><?= gettext('Add All Families') ?></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-clear-all="family"><?= gettext('Clear All Families') ?></button>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="fundsList"><?= gettext('Filter by Fund') ?></label>
                <select name="funds[]" id="fundsList" class="form-select" multiple data-report-select>
                    <?php $hasCategories = array_keys($fundGroups) !== ['']; ?>
                    <?php foreach ($fundGroups as $category => $funds) : ?>
                        <?php if ($hasCategories) : ?><optgroup label="<?= InputUtils::escapeAttribute($category !== '' ? $category : gettext('Uncategorized')) ?>"><?php endif; ?>
                        <?php foreach ($funds as $fund) : ?>
                            <option value="<?= (int) $fund['id'] ?>" <?= in_array((string) $fund['id'], $selectedFunds, true) ? 'selected' : '' ?>><?= InputUtils::escapeHTML($fund['name']) ?><?= $fund['inactive'] ? ' — ' . gettext('INACTIVE') : '' ?></option>
                        <?php endforeach; ?>
                        <?php if ($hasCategories) : ?></optgroup><?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-select-all="fundsList"><?= gettext('Add All Funds') ?></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-clear-all="fundsList"><?= gettext('Clear All Funds') ?></button>
                </div>
            </div>

            <div class="mb-0">
                <label class="form-label" for="minimum"><?= gettext('Minimum Total Amount') ?></label>
                <input class="form-control field-w-120" name="minimum" id="minimum" type="text" inputmode="decimal" value="<?= InputUtils::escapeAttribute($criteria['minimumCents'] > 0 ? (string) ($criteria['minimumCents'] / 100) : '0') ?>">
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= gettext('Other Settings') ?></h3></div>
        <div class="card-body">
            <div class="mb-0">
                <label class="form-label"><?= gettext('Remittance Slip') ?></label>
                <div class="d-flex gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="remittance" value="yes" id="remittanceYes" <?= $criteria['remittance'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="remittanceYes"><?= gettext('Yes') ?></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="remittance" value="no" id="remittanceNo" <?= $criteria['remittance'] ? '' : 'checked' ?>>
                        <label class="form-check-label" for="remittanceNo"><?= gettext('No') ?></label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <a class="btn btn-secondary" href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/finance/reports') ?>"><?= gettext('Back') ?></a>
        <button type="submit" class="btn btn-primary" id="previewReport"><i class="fa-solid fa-eye me-1"></i><?= gettext('Preview') ?></button>
    </div>
</form>

<div class="modal fade" id="givingPreviewModal" tabindex="-1" aria-labelledby="givingPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="givingPreviewTitle"><?= gettext('Preview') ?></h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3" id="modalSummary">
                    <div class="col-4">
                        <div class="text-secondary"><?= gettext('Families') ?></div>
                        <div class="h2 mb-0" id="modalFamilies"></div>
                    </div>
                    <div class="col-4">
                        <div class="text-secondary"><?= gettext('Payments') ?></div>
                        <div class="h2 mb-0" id="modalGifts"></div>
                    </div>
                    <div class="col-4">
                        <div class="text-secondary"><?= gettext('Total') ?></div>
                        <div class="h2 mb-0" id="modalTotal"></div>
                    </div>
                </div>
                <div class="alert alert-warning mb-0 d-none" id="modalEmpty" role="alert">
                    <strong><?= gettext('No Data Found') ?></strong>
                    <?= gettext('No records were returned from the previous report. Please adjust your filters or date range and try again.') ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Close') ?></button>
                <a class="btn btn-outline-secondary" id="modalCsv" href="#"><i class="fa-solid fa-file-csv me-1"></i><?= gettext('Download CSV') ?></a>
                <a class="btn btn-primary" id="modalPdf" href="#" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf me-1"></i><?= gettext('Download PDF') ?></a>
            </div>
        </div>
    </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/finance-report-options.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
