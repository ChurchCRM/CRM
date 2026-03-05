<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = $mapConfig['hasLocation']
    ? gettext('Congregation Map')
    : gettext('Congregation Map — Setup Required');

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/external/leaflet/leaflet.css') ?>">

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
        <div class="alert alert-info d-flex align-items-center py-2">
            <a href="<?= $sRootPath ?>/UpdateAllLatLon.php" class="btn btn-sm btn-secondary mr-2">
                <i class="fa-solid fa-location-dot"></i>
            </a>
            <span><?= gettext('Missing families? Update coordinates to include them on the map.') ?></span>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div id="map" style="height: 600px; width: 100%;"></div>
            </div>

            <!-- Desktop legend (injected into map by Leaflet control) -->
            <div id="map-legend" class="d-none d-sm-block">
                <strong><?= gettext('Legend') ?></strong>
                <?php foreach ($mapConfig['legendItems'] as $item): ?>
                    <div class="legend-row" data-classification="<?= (int) $item['id'] ?>">
                        <label>
                            <input type="checkbox" class="legend-cb" checked>
                            <span class="legend-dot" style="background:<?= htmlspecialchars($item['color']) ?>"></span>
                            <?= htmlspecialchars($item['label']) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Mobile legend (below the map card) -->
            <div class="card mt-2 d-block d-sm-none">
                <div class="card-header py-1">
                    <strong><?= gettext('Legend') ?></strong>
                </div>
                <div class="card-body py-2">
                    <div class="row">
                        <?php foreach ($mapConfig['legendItems'] as $item): ?>
                            <div class="col-6 legend-row" data-classification="<?= (int) $item['id'] ?>">
                                <label>
                                    <input type="checkbox" class="legend-cb" checked>
                                    <span class="legend-dot" style="background:<?= htmlspecialchars($item['color']) ?>"></span>
                                    <?= htmlspecialchars($item['label']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/external/leaflet/leaflet.js') ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.mapConfig = <?= json_encode($mapConfig, JSON_THROW_ON_ERROR) ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/map-view.js') ?>"></script>

<style nonce="<?= SystemURLs::getCSPNonce() ?>">
    .legend-dot {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin: 0 4px 0 2px;
        vertical-align: middle;
        border: 1px solid rgba(0, 0, 0, .2);
    }
    #map-legend {
        padding: 8px 12px;
        background: white;
        border-radius: 4px;
        box-shadow: 0 1px 5px rgba(0,0,0,.3);
        line-height: 1.8;
        min-width: 140px;
    }
    .legend-row label { cursor: pointer; margin: 0; }
</style>

<?php endif; ?>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
