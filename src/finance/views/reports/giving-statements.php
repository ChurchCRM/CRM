<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
require __DIR__ . '/../_charity-details-banner.php';

$selected = static fn (string $key): array => array_map('strval', (array) ($query[$key] ?? []));
$selectedFamilies = array_map('strval', $criteria['familyIds']);
$selectedClasses = $selected('classList');
$selectedFunds = $selected('funds');
$byDeposit = $criteria['byDepositDate'];
$base = SystemURLs::getRootPath() . '/finance/reports/giving-statements';
?>

<?php if ($canadianEnabled) : ?>
<nav class="d-flex gap-2 mb-3" aria-label="<?= InputUtils::escapeAttribute(gettext('Statement Template')) ?>">
    <a class="btn btn-outline-primary" href="<?= InputUtils::escapeAttribute($base) ?>"><?= gettext('Standard Giving Statement') ?></a>
    <a class="btn btn-outline-primary" href="<?= InputUtils::escapeAttribute($base . '?template=canadian') ?>"><?= gettext('Canadian Official Receipt') ?></a>
</nav>
<?php endif; ?>
<?php if ($canadianError !== '') : ?><div class="alert alert-danger" role="alert" id="canadianError"><?= InputUtils::escapeHTML($canadianError) ?></div><?php endif; ?>
<?php if ($canadian) : ?>
<div class="alert alert-info" role="note"><?= gettext('This option formats a Canadian receipt but does not keep a receipt register or retain PDFs. Keep the original PDF and an external receipt log at the charity\'s Canadian records location. Do not reuse a number for a different receipt, receipt the same gifts twice, or regenerate changed information as an unchanged original. This tool has no cancellation/replacement workflow; use your existing CRA-compliant process for corrections/lost-receipt replacements. A signature image is not a secure digital signature.') ?></div>
<p><?= gettext('Historical envelope changes are unsupported. Mixed-donor family payment sets cannot be split here; handle them outside this feature. If a retained original is unavailable or different, use your external CRA cancellation/replacement procedures.') ?></p>
<?php
$answer = static fn (string $key, string $default = ''): string => is_scalar($query[$key] ?? null) ? (string) $query[$key] : $default;
$perFamily = static fn (string $key, int $id): string => is_array($query[$key] ?? null) && is_scalar($query[$key][$id] ?? null) ? (string) $query[$key][$id] : '';
$checked = static fn (bool $on): string => $on ? 'checked' : '';
$isDuplicate = $answer('copyKind') === 'duplicate';
$included = array_map('strval', (array) ($query['family'] ?? []));
// The first review fills in suggestions; Check Again and a failed download keep the treasurer's answers.
$freshReview = empty($query['reviewed']) && ($query['review'] ?? '') === '1';
?>
<form method="post" action="<?= InputUtils::escapeAttribute($base . '?template=canadian') ?>" id="canadianReportForm">
    <?= \ChurchCRM\Utils\CSRFUtils::getTokenInputField() ?>
    <input type="hidden" name="template" value="canadian">
    <?php if ($canadianReview !== []) : ?>
    <input type="hidden" name="calendarYear" value="<?= (int) $query['calendarYear'] ?>">
    <input type="hidden" name="copyKind" value="<?= $isDuplicate ? 'duplicate' : 'original' ?>">
    <?php foreach ($canadianReview as $review) : ?><input type="hidden" name="reviewed[]" value="<?= (int) $review['id'] ?>"><?php endforeach; ?>
    <p><a href="<?= InputUtils::escapeAttribute($base . '?template=canadian') ?>"><?= gettext('Change Selection') ?></a></p>
    <?php endif; ?>
    <div class="card mb-3"><div class="card-body">
        <label class="form-label" for="calendarYear"><?= gettext('Gift Calendar Year') ?></label>
        <input class="form-control mb-3" type="number" id="calendarYear" <?= $canadianReview !== [] ? 'disabled' : 'name="calendarYear"' ?> min="1991" max="<?= (int) DateTimeUtils::getToday()->format('Y') ?>" value="<?= InputUtils::escapeAttribute($answer('calendarYear', (string) ((int) DateTimeUtils::getToday()->format('Y') - 1))) ?>" required>
        <label class="form-label" for="canadianFamily"><?= gettext('Filter by Family') ?></label>
        <select class="form-select mb-3" <?= $canadianReview !== [] ? 'disabled' : 'name="family[]"' ?> id="canadianFamily" multiple size="8" required>
            <?php foreach ($families as $family) : ?>
            <option value="<?= (int) $family['id'] ?>" <?= in_array((string) $family['id'], array_map('strval', (array) ($query['reviewed'] ?? $query['family'] ?? [])), true) ? 'selected' : '' ?>><?= InputUtils::escapeHTML($family['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="form-label" for="copyKind"><?= gettext('Copy Kind') ?></label>
        <select class="form-select mb-3" <?= $canadianReview !== [] ? 'disabled' : 'name="copyKind"' ?> id="copyKind">
            <option value="original" <?= !$isDuplicate ? 'selected' : '' ?>><?= gettext('Original') ?></option>
            <option value="duplicate" <?= $isDuplicate ? 'selected' : '' ?>><?= gettext('Duplicate') ?></option>
        </select>
        <button class="btn btn-primary" type="submit" name="review" value="1" formnovalidate id="canadianReview"><?= $canadianReview !== [] ? gettext('Check Again') : gettext('Review Selected Families') ?></button>
        <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="<?= InputUtils::escapeAttribute($base . '/sample?template=canadian') ?>"><?= gettext('Preview PDF') ?></a>
    </div></div>
    <?php if ($canadianReview !== []) : ?>
    <?php $blocked = array_filter($canadianReview, static fn (array $review): bool => $review['problems'] !== []); ?>
    <?php if ($blocked !== []) : ?>
    <div class="alert alert-warning" role="status" id="canadianBlocked"><?= InputUtils::escapeHTML(sprintf(ngettext('%d family cannot get a receipt yet and is left out. Fix the problem listed under it, then choose Check Again.', '%d families cannot get a receipt yet and are left out. Fix the problems listed under them, then choose Check Again.', count($blocked)), count($blocked))) ?></div>
    <?php endif; ?>
    <div class="card mb-3"><div class="card-body">
        <label class="form-label" for="issueDate"><?= $isDuplicate ? gettext('Original Issue Date (enter from retained original)') : gettext('Issue Date') ?></label>
        <input class="form-control mb-3" type="date" name="issueDate" id="issueDate" max="<?= DateTimeUtils::getTodayDate() ?>" value="<?= InputUtils::escapeAttribute($answer('issueDate', $isDuplicate ? '' : DateTimeUtils::getTodayDate())) ?>" required>
        <?php foreach ($canadianReview as $review) : ?>
        <?php $id = (int) $review['id']; $changed = $perFamily('snapshot', $id) !== '' && $perFamily('snapshot', $id) !== $review['snapshot']; $ok = $review['problems'] === []; $include = $ok && ($freshReview || in_array((string) $id, $included, true)); ?>
        <fieldset class="border rounded p-3 mb-3" data-family-id="<?= $id ?>">
            <legend class="float-none w-auto fs-4"><?= InputUtils::escapeHTML($review['name']) ?><?php if ($review['inactive']) : ?> <span class="badge bg-secondary-lt text-secondary fs-6"><?= gettext('Inactive') ?></span><?php endif; ?></legend>
            <input type="hidden" name="snapshot[<?= $id ?>]" value="<?= InputUtils::escapeAttribute($review['snapshot']) ?>">
            <?php if ($changed) : ?><div class="alert alert-warning" role="status"><?= gettext('The gifts or mailing address changed since you last checked. Review them and confirm the donor again.') ?></div><?php endif; ?>
            <p><?= nl2br(InputUtils::escapeHTML($review['address'])) ?></p>
            <p class="text-secondary"><?= InputUtils::escapeHTML(sprintf(ngettext('%1$d gift, %2$s', '%1$d gifts, %2$s', (int) $review['gifts']), (int) $review['gifts'], $review['total'])) ?></p>
            <?php if (!$ok) : ?>
            <div class="alert alert-danger" role="alert">
                <strong><?= gettext('No receipt can be generated for this family yet') ?>:</strong>
                <ul class="mb-0"><?php foreach ($review['problems'] as $problem) : ?><li><?= InputUtils::escapeHTML($problem) ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>
            <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="family[]" value="<?= $id ?>" <?= $checked($include) ?> <?= $ok ? '' : 'disabled' ?>><span class="form-check-label"><?= gettext('Include this family in the download') ?></span></label>
            <label class="form-label" for="donor-<?= $id ?>"><?= gettext('Verified Full Donor Name(s)') ?></label>
            <input class="form-control mb-1" id="donor-<?= $id ?>" name="donorNames[<?= $id ?>]" maxlength="300" value="<?= InputUtils::escapeAttribute($freshReview ? $review['suggestedName'] : $perFamily('donorNames', $id)) ?>">
            <div class="form-text mb-2"><?= gettext('Filled in from the heads of household and spouses. Edit it to match the donors exactly.') ?></div>
            <label class="form-label" for="mixed-<?= $id ?>"><?= gettext('Do all selected-year gifts belong to the donor(s) named together above?') ?></label>
            <select class="form-select mb-2" id="mixed-<?= $id ?>" name="mixedDonors[<?= $id ?>]">
                <?php $mixed = $perFamily('mixedDonors', $id); ?>
                <option value=""><?= gettext('Select') ?></option><option value="no" <?= $mixed === 'no' ? 'selected' : '' ?>><?= gettext('Yes — one verified donor set') ?></option><option value="yes" <?= $mixed === 'yes' ? 'selected' : '' ?>><?= gettext('No — different donors; handle outside this feature') ?></option>
            </select>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="donorVerified[<?= $id ?>]" value="1" <?= $checked(!$changed && $perFamily('donorVerified', $id) === '1') ?>><span class="form-check-label"><?= gettext('These are the true donors for every gift in this family/calendar year, and the mailing address is correct.') ?></span></label>
        </fieldset>
        <?php endforeach; ?>
        <?php foreach ([
            'signerAuthorized' => gettext('The printed giving-statement signer is authorized to sign these receipts.'),
            'monetaryOnly' => gettext('All included rows are monetary gifts with no advantage.'),
            'receivedDatesVerified' => gettext('Payment dates reflect gift-received dates, including documented mailed-cheque postmark treatment.'),
            'noOverlap' => gettext('No overlapping individual or cumulative receipt exists.'),
            'retainOriginal' => gettext('I will retain the issued PDF and an external receipt log at the charity\'s Canadian records location.'),
        ] as $key => $label) : ?>
        <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="<?= InputUtils::escapeAttribute($key) ?>" value="1" required <?= $checked($answer($key) === '1') ?>><span class="form-check-label"><?= InputUtils::escapeHTML($label) ?></span></label>
        <?php endforeach; ?>
        <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="includeMemo" value="1" <?= $checked($answer('includeMemo') === '1') ?>><span class="form-check-label"><?= gettext('Include payment memos: I reviewed them as suitable for the donor.') ?></span></label>
        <?php if ($isDuplicate) : ?>
        <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="duplicateCompared" value="1" required <?= $checked($answer('duplicateCompared') === '1') ?>><span class="form-check-label"><?= gettext('I compared with the retained original: donor, amount, serial and text are unchanged. This is not a correction or replacement.') ?></span></label>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit" formaction="<?= InputUtils::escapeAttribute($base . '/download') ?>" formtarget="_blank" id="canadianDownload" <?= count($blocked) === count($canadianReview) ? 'disabled' : '' ?>><?= gettext('Download PDF') ?></button>
    </div></div>
    <?php endif; ?>
</form>
<?php else : ?>

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
    <?php $narrowOpen = $summary === null || $summary['gifts'] === 0; ?>
    <?php if (!$narrowOpen) : ?>
    <p><button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#narrowList" aria-expanded="false" aria-controls="narrowList" id="narrowListToggle"><i class="fa-solid fa-sliders me-1"></i><?= gettext('Narrow the list') ?></button>
    <span class="text-body-secondary small ms-2"><?= InputUtils::escapeHTML(DateTimeUtils::formatDate($criteria['dateStart'])) ?> – <?= InputUtils::escapeHTML(DateTimeUtils::formatDate($criteria['dateEnd'])) ?></span></p>
    <?php endif; ?>
    <div id="narrowList" class="collapse <?= $narrowOpen ? 'show' : '' ?>">
    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= gettext('Filters') ?></h3></div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label" for="DateStart"><?= gettext('Report Start Date') ?></label>
                    <input type="text" class="form-control date-picker" name="DateStart" id="DateStart" maxlength="10" value="<?= InputUtils::escapeAttribute(DateTimeUtils::formatForDatePicker($criteria['dateStart'])) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="DateEnd"><?= gettext('Report End Date') ?></label>
                    <input type="text" class="form-control date-picker" name="DateEnd" id="DateEnd" maxlength="10" value="<?= InputUtils::escapeAttribute(DateTimeUtils::formatForDatePicker($criteria['dateEnd'])) ?>">
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
    </div>

    <div class="d-flex gap-2">
        <a class="btn btn-secondary" href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/finance/reports/year-end') ?>"><?= gettext('Back') ?></a>
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
                <form method="post" action="<?= InputUtils::escapeAttribute($base . '/download') ?>" id="modalDownload" class="d-flex gap-2 m-0">
                    <button type="submit" class="btn btn-outline-secondary" id="modalCsv" name="output" value="csv"><i class="fa-solid fa-file-csv me-1"></i><?= gettext('Download CSV') ?></button>
                    <button type="submit" class="btn btn-primary" id="modalPdf" name="output" value="pdf" formtarget="_blank"><i class="fa-solid fa-file-pdf me-1"></i><?= gettext('Download PDF') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/finance-report-options.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
