<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= gettext('Search') ?></h3>
            </div>
            <div class="card-body">
                <form id="neighborsForm">
                    <div class="mb-3">
                        <label for="familySelect" class="form-label"><?= gettext('Select Family') ?></label>
                        <select id="familySelect" data-placeholder="<?= gettext('Select a family') ?>" class="form-select choiceSelectBox w-100">
                            <option></option>
                            <?php foreach ($families as $family): ?>
                                <option value="<?= $family->getId() ?>" <?= $iFamily === $family->getId() ? 'selected' : '' ?>>
                                    <?= InputUtils::escapeHTML($family->getName() . ' - ' . $family->getAddress()) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label for="maxNeighbors" class="form-label"><?= gettext('Max neighbors') ?></label>
                            <input type="number" min="1" class="form-control" id="maxNeighbors" value="15">
                        </div>
                        <div class="col-6 mb-3">
                            <label for="maxDistance" class="form-label">
                                <?= gettext('Max distance') ?> (<?= InputUtils::escapeHTML($distanceUnit) ?>)
                            </label>
                            <input type="number" min="0" step="0.1" class="form-control" id="maxDistance" value="10">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= gettext('Classifications') ?></label>
                        <div class="row g-2">
                            <?php foreach ($aClassificationName as $key => $value): ?>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="<?= (int) $key ?>"
                                            name="classificationId" id="cls_<?= (int) $key ?>" checked>
                                        <label class="form-check-label" for="cls_<?= (int) $key ?>"><?= InputUtils::escapeHTML($value) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="findNeighborsBtn">
                        <?= gettext('Find Neighbors') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title mb-0"><?= gettext('Results') ?></h3>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary" id="addAllToCart" disabled>
                        <?= gettext('Add All to Cart') ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" id="removeAllFromCart" disabled>
                        <?= gettext('Remove All from Cart') ?>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="neighborsEmpty" class="p-4 text-secondary text-center">
                    <?= gettext('Select a family and click Find Neighbors to see results.') ?>
                </div>
                <div class="table-responsive" style="overflow: visible;">
                    <table id="neighborsTable" class="table table-vcenter card-table d-none">
                        <thead>
                            <tr>
                                <th><?= gettext('Distance') ?></th>
                                <th><?= gettext('Direction') ?></th>
                                <th><?= gettext('Family') ?></th>
                                <th><?= gettext('People') ?></th>
                            </tr>
                        </thead>
                        <tbody id="neighborsTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.mapNeighborsConfig = {
        apiUrl: <?= json_encode($apiUrl, JSON_THROW_ON_ERROR) ?>,
        distanceUnit: <?= json_encode($distanceUnit, JSON_THROW_ON_ERROR) ?>
    };
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/map-neighbors.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
