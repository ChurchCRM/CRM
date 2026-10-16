<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

// Use the page title set by the route; append a setup-required note if location is missing
if (!$mapConfig['hasLocation']) {
    $sPageTitle .= ' — ' . gettext('Setup Required');
}

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<!-- Results container for the "Update All Coordinates" bulk geocode action.
     Populated by map-view.js; always rendered so the button works even when
     the church has no location configured. -->
<div id="geocodeAllResults" class="mt-2" aria-live="polite"></div>



<?php if (!$mapConfig['hasLocation']): ?>
    <div class="alert alert-danger">
        <?= gettext('Unable to display map: church address has not been geocoded yet.') ?>
        <a href="<?= $sRootPath ?>/SystemSettings.php" class="alert-link">
            <?= gettext('Update church address in Settings.') ?>
        </a>
    </div>
<?php else: ?>


<div class="row">
    <div class="col-12">
        <?php if ($mapConfig['hasLocation'] && $mapConfig['churchAddress'] !== ''): ?>
        <div class="text-secondary small mb-2 d-flex align-items-center flex-wrap gap-1">
            <i class="fa-solid fa-location-crosshairs me-1"></i>
            <?= gettext('Map centered on') ?>
            <strong><?= InputUtils::escapeHTML($mapConfig['churchName']) ?></strong>
            <span class="text-body-secondary">&middot; <?= InputUtils::escapeHTML($mapConfig['churchAddress']) ?></span>
            <a href="<?= $sRootPath ?>/admin/system/church-info" class="ms-1"><?= gettext('Change') ?></a>
        </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body p-0">
                <div id="map" class="people-map w-100"></div>
            </div>

            <!-- Desktop legend (injected into map overlay by Leaflet control) -->
            <div id="map-legend" class="d-none d-sm-block">
                <?php if ($mapConfig['hasLocation']): ?>
                <div class="legend-static">
                    <img src="<?= $sRootPath ?>/skin/icons/church.png" width="14" height="14" alt="" class="flex-shrink-0">
                    <span class="legend-label"><?= InputUtils::escapeHTML($mapConfig['churchName']) ?></span>
                </div>
                <?php endif; ?>
                <div class="legend-title"><?= InputUtils::escapeHTML($mapConfig['legendTitle']) ?></div>
                <?php foreach ($mapConfig['legendItems'] as $item): ?>
                    <button type="button" class="legend legend-item d-flex" data-legend-id="<?= (int) $item['id'] ?>"
                            aria-pressed="true" style="--tblr-legend-color: <?= InputUtils::escapeAttribute($item['color']) ?>">
                        <span class="legend-dot"></span>
                        <span class="legend-label"><?= InputUtils::escapeHTML($item['label']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Mobile legend (below the map card) -->
            <div class="card mt-2 d-block d-sm-none">
                <div class="card-header py-2">
                    <strong><?= InputUtils::escapeHTML($mapConfig['legendTitle']) ?></strong>
                </div>
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($mapConfig['legendItems'] as $item): ?>
                            <button type="button" class="legend legend-item legend-pill" data-legend-id="<?= (int) $item['id'] ?>"
                                    aria-pressed="true" style="--tblr-legend-color: <?= InputUtils::escapeAttribute($item['color']) ?>">
                                <span class="legend-dot"></span>
                                <span class="legend-label"><?= InputUtils::escapeHTML($item['label']) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.mapConfig = <?= InputUtils::jsonEncodeForScript($mapConfig) ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/people-map-view.min.js') ?>"></script>

<?php endif; ?>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
