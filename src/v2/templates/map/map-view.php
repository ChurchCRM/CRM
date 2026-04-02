<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

// Use the page title set by the route; append a setup-required note if location is missing
if (!$mapConfig['hasLocation']) {
    $sPageTitle .= ' — ' . gettext('Setup Required');
}

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
        <div class="card">
            <div class="card-body p-0">
                <div id="map" style="height: 600px; width: 100%;"></div>
            </div>

            <!-- Desktop legend (injected into map overlay by Leaflet control) -->
            <div id="map-legend" class="d-none d-sm-block">
                <div class="legend-title"><?= htmlspecialchars($mapConfig['legendTitle']) ?></div>
                <?php foreach ($mapConfig['legendItems'] as $item): ?>
                    <div class="legend-item active" data-legend-id="<?= (int) $item['id'] ?>"
                         role="button" tabindex="0" aria-pressed="true">
                        <span class="legend-dot" style="background:<?= htmlspecialchars($item['color']) ?>"></span>
                        <span class="legend-label"><?= htmlspecialchars($item['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Mobile legend (below the map card) -->
            <div class="card mt-2 d-block d-sm-none">
                <div class="card-header py-2">
                    <strong><?= htmlspecialchars($mapConfig['legendTitle']) ?></strong>
                </div>
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($mapConfig['legendItems'] as $item): ?>
                            <div class="legend-item active legend-pill" data-legend-id="<?= (int) $item['id'] ?>">
                                <span class="legend-dot" style="background:<?= htmlspecialchars($item['color']) ?>"></span>
                                <span class="legend-label"><?= htmlspecialchars($item['label']) ?></span>
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
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    <?php if (AuthenticationManager::getCurrentUser()->isAdmin()): ?>
    $(document).ready(function() {
        window.CRM.settingsPanel.init({
            container: '#mapAdminSettings',
            title: () => i18next.t('Map Settings'),
            icon: 'fa-solid fa-sliders',
            settings: [
                {
                    name: 'iMapZoom',
                    label: () => i18next.t('Default Map View'),
                    type: 'choice',
                    choices: [
                        { value: '3', label: () => i18next.t('Continent') },
                        { value: '5', label: () => i18next.t('Country') },
                        { value: '7', label: () => i18next.t('State') },
                        { value: '10', label: () => i18next.t('City') },
                        { value: '14', label: () => i18next.t('Neighborhood') },
                        { value: '18', label: () => i18next.t('Street') }
                    ]
                },
                {
                    name: 'bHideLatLon',
                    label: () => i18next.t('Hide Latitude/Longitude'),
                    type: 'boolean',
                    tooltip: <?= json_encode($mapSettingTooltips['bHideLatLon'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
                },
                {
                    name: 'bHidePersonAddress',
                    label: () => i18next.t('Hide Person Address'),
                    type: 'boolean',
                    tooltip: <?= json_encode($mapSettingTooltips['bHidePersonAddress'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
                }
            ],
            showAllSettingsLink: false
        });
    });
    <?php endif; ?>
</script>

<style nonce="<?= SystemURLs::getCSPNonce() ?>">
    /* ── Floating map legend (desktop) ──────────────────────────────── */
    #map-legend {
        padding: 8px 12px;
        background: #fff;
        border-radius: 6px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .18);
        min-width: 150px;
        font-size: .85rem;
    }
    .legend-title {
        font-weight: 600;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6c757d;
        margin-bottom: 6px;
    }

    /* ── Shared legend item ─────────────────────────────────────────── */
    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 3px 6px;
        border-radius: 4px;
        cursor: pointer;
        user-select: none;
        transition: opacity .15s, background .15s;
        line-height: 1.6;
    }
    .legend-item:hover {
        background: rgba(0, 0, 0, .05);
    }
    .legend-item.inactive {
        opacity: .38;
    }
    .legend-item.inactive .legend-label {
        text-decoration: line-through;
    }

    /* ── Mobile pill variant ────────────────────────────────────────── */
    .legend-pill {
        border: 1px solid rgba(0, 0, 0, .12);
        padding: 4px 10px;
        background: #f8f9fa;
    }
    .legend-pill.inactive {
        background: #f8f9fa;
    }

    /* ── Colour dot ─────────────────────────────────────────────────── */
    .legend-dot {
        display: inline-block;
        width: 11px;
        height: 11px;
        border-radius: 50%;
        border: 1px solid rgba(0, 0, 0, .2);
        flex-shrink: 0;
    }
</style>

<?php endif; ?>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
