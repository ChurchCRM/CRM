<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>



<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div id="neighborsMapWrap" class="neighbors-map-wrap">
                    <div id="neighborsMap"></div>

                    <button type="button" id="toggleSearchPanel" class="btn btn-primary neighbors-search-toggle">
                        <i class="fa-solid fa-magnifying-glass me-2"></i><?= gettext('Search Neighbors') ?>
                    </button>

                    <div id="searchPanel" class="neighbors-search-panel">
                        <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
                            <h3 class="card-title m-0"><?= gettext('Search') ?></h3>
                            <button type="button" id="closeSearchPanel" class="btn-close" aria-label="<?= gettext('Close') ?>"></button>
                        </div>
                        <div class="p-3">
                            <form id="neighborsForm">
                                <div class="mb-3">
                                    <label for="familySelect" class="form-label"><?= gettext('Select Family') ?></label>
                                    <select id="familySelect" data-placeholder="<?= gettext('Select a family') ?>" class="form-select choiceSelectBox w-100">
                                        <option></option>
                                        <?php foreach ($families as $family): ?>
                                            <option value="<?= (int) $family->getId() ?>" <?= $iFamily === $family->getId() ? 'selected' : '' ?>>
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
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title mb-0"><?= gettext('Results') ?> <span id="neighborsCount" class="badge bg-secondary-lt text-secondary ms-1 d-none">0</span></h3>
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
                <div class="table-responsive overflow-visible">
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
    window.CRM.mapNeighborsConfig = <?= InputUtils::jsonEncodeForScript([
        'apiUrl'       => $apiUrl,
        'distanceUnit' => $distanceUnit,
        'churchLat'    => $mapConfig['churchLat'],
        'churchLng'    => $mapConfig['churchLng'],
        'churchName'   => $mapConfig['churchName'],
        'zoom'         => $mapConfig['zoom'],
        'hasLocation'  => $mapConfig['hasLocation'],
    ]) ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/people-map-neighbors.min.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
