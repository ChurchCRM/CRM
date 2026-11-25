<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$groups = [
    gettext('Dates') => [
        'date1X' => gettext('First date, left'), 'date1Y' => gettext('First date, top'),
        'date2X' => gettext('Second date, left'), 'titleX' => gettext('Title, left'),
    ],
    gettext('Ticket') => [
        'leftX' => gettext('Left margin'), 'topY' => gettext('Top margin'),
        'perforationY' => gettext('Perforation, top'), 'amountOffsetX' => gettext('Amount offset, left'),
        'maxX' => gettext('Maximum width'), 'maxY' => gettext('Maximum height'),
    ],
    gettext('Line items') => [
        'lineItemIntervalX' => gettext('Spacing, horizontal'), 'lineItemIntervalY' => gettext('Spacing, vertical'),
        'numberOfItemsX' => gettext('Item count, left'), 'numberOfItemsY' => gettext('Item count, top'),
    ],
    gettext('Totals') => [
        'subTotalX' => gettext('Subtotal, left'), 'subTotalY' => gettext('Subtotal, top'),
        'topTotalX' => gettext('Total, left'), 'topTotalY' => gettext('Total, top'),
    ],
];
?>

<div class="container-fluid">
    <form id="quickbooksForm" class="card" novalidate data-csrf-token="<?= InputUtils::escapeAttribute($csrfToken) ?>">
        <div class="card-body">
            <?php foreach ($groups as $heading => $fields) : ?>
                <h4 class="mb-3"><?= InputUtils::escapeHTML($heading) ?></h4>
                <div class="row mb-3">
                    <?php foreach ($fields as $name => $label) : ?>
                        <div class="col-6 col-lg-3 mb-3">
                            <label class="form-label" for="qb-<?= $name ?>"><?= InputUtils::escapeHTML($label) ?></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="qb-<?= $name ?>" name="<?= $name ?>"
                                       min="0" max="1000" step="any" required
                                       value="<?= InputUtils::escapeAttribute($values[$name]) ?>">
                                <span class="input-group-text"><?= gettext('mm') ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary" id="quickbooksSave"><?= gettext('Save') ?></button>
        </div>
    </form>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/finance-quickbooks.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
