<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Ensure variables have defaults (sGlobalMessage and sGlobalMessageClass are
// consumed by Footer.php via showGlobalMessage() → window.CRM.notify())
$sGlobalMessage      = $sGlobalMessage ?? '';
$sGlobalMessageClass = $sGlobalMessageClass ?? 'success';
$validationError     = $validationError ?? '';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header p-0 pt-1">
                <ul class="nav nav-tabs" id="church-info-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="basic-tab"
                           data-toggle="tab" href="#basic" role="tab"
                           aria-controls="basic" aria-selected="true">
                            <i class="fa-solid fa-church mr-1"></i><?= gettext('Basic') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="location-tab"
                           data-toggle="tab" href="#location" role="tab"
                           aria-controls="location" aria-selected="false">
                            <i class="fa-solid fa-map-marker-alt mr-1"></i><?= gettext('Location') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="contact-tab"
                           data-toggle="tab" href="#contact" role="tab"
                           aria-controls="contact" aria-selected="false">
                            <i class="fa-solid fa-envelope mr-1"></i><?= gettext('Contact') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="map-tab"
                           data-toggle="tab" href="#map" role="tab"
                           aria-controls="map" aria-selected="false">
                            <i class="fa-solid fa-globe mr-1"></i><?= gettext('Map') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="display-tab"
                           data-toggle="tab" href="#display" role="tab"
                           aria-controls="display" aria-selected="false">
                            <i class="fa-solid fa-desktop mr-1"></i><?= gettext('Display') ?>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <form method="POST"
                      action="<?= $sRootPath ?>/admin/system/church-info"
                      id="church-info-form">
                    <div class="tab-content" id="church-info-tab-content">

                        <!-- Tab 1: Basic Information -->
                        <div class="tab-pane fade show active" id="basic" role="tabpanel" aria-labelledby="basic-tab">
                            <h5 class="mb-3"><?= gettext('Basic Information') ?></h5>

                            <div class="form-group">
                                <label for="sChurchName">
                                    <?= gettext('Church Name') ?>
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control<?= (!empty($validationError)) ? ' is-invalid' : '' ?>"
                                       id="sChurchName"
                                       name="sChurchName"
                                       value="<?= InputUtils::escapeHTML($churchInfo['sChurchName']) ?>"
                                       required
                                       maxlength="200"
                                       aria-describedby="churchNameHelp">
                                <small id="churchNameHelp" class="form-text text-muted">
                                    <?= gettext('Required. Used on all reports and communications.') ?>
                                </small>
                                <?php if (!empty($validationError)): ?>
                                <div class="invalid-feedback"><?= InputUtils::escapeHTML($validationError) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="sChurchWebSite"><?= gettext('Website') ?></label>
                                <input type="url"
                                       class="form-control"
                                       id="sChurchWebSite"
                                       name="sChurchWebSite"
                                       value="<?= InputUtils::escapeHTML($churchInfo['sChurchWebSite']) ?>"
                                       maxlength="200"
                                       placeholder="https://">
                                <small class="form-text text-muted">
                                    <?= gettext('Optional. URL for your church website.') ?>
                                </small>
                            </div>
                        </div>

                        <!-- Tab 2: Location -->
                        <div class="tab-pane fade" id="location" role="tabpanel" aria-labelledby="location-tab">
                            <h5 class="mb-3"><?= gettext('Location') ?></h5>

                            <div class="form-group">
                                <label for="sChurchAddress"><?= gettext('Street Address') ?></label>
                                <input type="text"
                                       class="form-control"
                                       id="sChurchAddress"
                                       name="sChurchAddress"
                                       value="<?= InputUtils::escapeHTML($churchInfo['sChurchAddress']) ?>"
                                       maxlength="200">
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-5">
                                    <label for="sChurchCity"><?= gettext('City') ?></label>
                                    <input type="text"
                                           class="form-control"
                                           id="sChurchCity"
                                           name="sChurchCity"
                                           value="<?= InputUtils::escapeHTML($churchInfo['sChurchCity']) ?>"
                                           maxlength="100">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="sChurchState"><?= gettext('State / Province') ?></label>
                                    <input type="text"
                                           class="form-control"
                                           id="sChurchState"
                                           name="sChurchState"
                                           value="<?= InputUtils::escapeHTML($churchInfo['sChurchState']) ?>"
                                           maxlength="50">
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="sChurchZip"><?= gettext('ZIP / Postal Code') ?></label>
                                    <input type="text"
                                           class="form-control"
                                           id="sChurchZip"
                                           name="sChurchZip"
                                           value="<?= InputUtils::escapeHTML($churchInfo['sChurchZip']) ?>"
                                           maxlength="20">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="sChurchCountry"><?= gettext('Country') ?></label>
                                <select class="form-control" id="sChurchCountry" name="sChurchCountry">
                                    <option value=""><?= gettext('— Select Country —') ?></option>
                                    <?php foreach ($countries as $code => $name): ?>
                                    <option value="<?= InputUtils::escapeHTML($code) ?>"
                                        <?= ($churchInfo['sChurchCountry'] === $code) ? 'selected' : '' ?>>
                                        <?= InputUtils::escapeHTML($name) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Tab 3: Contact Information -->
                        <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
                            <h5 class="mb-3"><?= gettext('Contact Information') ?></h5>

                            <div class="form-group">
                                <label for="sChurchPhone"><?= gettext('Phone Number') ?></label>
                                <input type="tel"
                                       class="form-control"
                                       id="sChurchPhone"
                                       name="sChurchPhone"
                                       value="<?= InputUtils::escapeHTML($churchInfo['sChurchPhone']) ?>"
                                       maxlength="30"
                                       placeholder="(555) 555-5555">
                                <small class="form-text text-muted">
                                    <?= gettext('Main contact phone number for the church.') ?>
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="sChurchEmail"><?= gettext('Email Address') ?></label>
                                <input type="email"
                                       class="form-control"
                                       id="sChurchEmail"
                                       name="sChurchEmail"
                                       value="<?= InputUtils::escapeHTML($churchInfo['sChurchEmail']) ?>"
                                       maxlength="200"
                                       placeholder="info@yourchurch.org">
                                <small class="form-text text-muted">
                                    <?= gettext('Main contact email for church communications.') ?>
                                </small>
                            </div>
                        </div>

                        <!-- Tab 4: Map & Timezone -->
                        <div class="tab-pane fade" id="map" role="tabpanel" aria-labelledby="map-tab">
                            <h5 class="mb-3"><?= gettext('Map &amp; Timezone') ?></h5>

                            <?php
                            $hasCoords = !empty($churchInfo['iChurchLatitude'])
                                && !empty($churchInfo['iChurchLongitude'])
                                && ((float) $churchInfo['iChurchLatitude'] !== 0.0
                                    || (float) $churchInfo['iChurchLongitude'] !== 0.0);
                            ?>

                            <?php if ($hasCoords): ?>
                            <!-- Leaflet map showing geocoded church location (auto-detected on save) -->
                            <link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/external/leaflet/leaflet.css') ?>">
                            <div id="church-location-map" class="mb-2 rounded border" style="height:280px;"></div>
                            <p class="text-muted small mb-3">
                                <i class="fa-solid fa-location-dot mr-1"></i>
                                <?= gettext('Geocoded coordinates:') ?>
                                <?= InputUtils::escapeHTML($churchInfo['iChurchLatitude']) ?>,
                                <?= InputUtils::escapeHTML($churchInfo['iChurchLongitude']) ?>
                                &mdash; <?= gettext('Updated automatically on every save.') ?>
                            </p>
                            <script nonce="<?= SystemURLs::getCSPNonce() ?>">
                                window.CRM = window.CRM || {};
                                window.CRM.churchMapConfig = <?= json_encode([
                                    'lat'  => (float) $churchInfo['iChurchLatitude'],
                                    'lng'  => (float) $churchInfo['iChurchLongitude'],
                                    'name' => $churchInfo['sChurchName'],
                                ]) ?>;
                            </script>
                            <script src="<?= SystemURLs::assetVersioned('/skin/external/leaflet/leaflet.js') ?>"></script>
                            <script nonce="<?= SystemURLs::getCSPNonce() ?>">
                            document.addEventListener('DOMContentLoaded', function () {
                                var cfg = window.CRM.churchMapConfig;
                                var churchMap = null;
                                // Initialise only when the Map tab is visible; Leaflet needs a sized container.
                                // Guard against re-initialisation when the tab is shown more than once.
                                function initChurchMap() {
                                    if (churchMap !== null) {
                                        return;
                                    }
                                    churchMap = L.map('church-location-map', {
                                        scrollWheelZoom: false,
                                        zoomControl: true
                                    }).setView([cfg.lat, cfg.lng], 15);
                                    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                        maxZoom: 19,
                                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors'
                                    }).addTo(churchMap);
                                    L.marker([cfg.lat, cfg.lng])
                                        .bindPopup('<strong>' + cfg.name + '</strong>')
                                        .addTo(churchMap);
                                }
                                var mapTab = document.getElementById('map-tab');
                                if (mapTab) {
                                    mapTab.addEventListener('shown.bs.tab', initChurchMap);
                                    // If the Map tab is already active on page load, init immediately.
                                    if (mapTab.classList.contains('active')) {
                                        initChurchMap();
                                    }
                                }
                            });
                            </script>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fa-solid fa-location-dot mr-2"></i>
                                <?= gettext('A map will appear here once a street address is saved. Coordinates are detected automatically — no manual entry required.') ?>
                            </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="sTimeZone"><?= gettext('Time Zone') ?></label>
                                <select class="form-control" id="sTimeZone" name="sTimeZone">
                                    <?php foreach ($timezones as $tz): ?>
                                    <option value="<?= InputUtils::escapeHTML($tz) ?>"
                                        <?= ($churchInfo['sTimeZone'] === $tz) ? 'selected' : '' ?>>
                                        <?= InputUtils::escapeHTML($tz) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">
                                    <?= gettext('Used for scheduling events and reporting times.') ?>
                                </small>
                            </div>
                        </div>

                        <!-- Tab 5: Display -->
                        <div class="tab-pane fade" id="display" role="tabpanel" aria-labelledby="display-tab">
                            <h5 class="mb-3"><?= gettext('Display Preview') ?></h5>
                            <p class="text-muted">
                                <?= gettext('This is how your church information will appear on reports and directories.') ?>
                            </p>

                            <div class="card card-body bg-light">
                                <address class="mb-0">
                                    <?php if (!empty($churchInfo['sChurchName'])): ?>
                                    <strong><?= InputUtils::escapeHTML($churchInfo['sChurchName']) ?></strong><br>
                                    <?php endif; ?>
                                    <?php if (!empty($churchInfo['sChurchAddress'])): ?>
                                    <?= InputUtils::escapeHTML($churchInfo['sChurchAddress']) ?><br>
                                    <?php endif; ?>
                                    <?php
                                    // Format city, state  zip (standard address format)
                                    $cityStateParts = array_filter([$churchInfo['sChurchCity'], $churchInfo['sChurchState']]);
                                    $cityStateStr = implode(', ', $cityStateParts);
                                    $zip = $churchInfo['sChurchZip'];
                                    $cityLine = trim($cityStateStr . ($zip !== '' ? ' ' . $zip : ''));
                                    if ($cityLine !== ''):
                                    ?>
                                    <?= InputUtils::escapeHTML($cityLine) ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($churchInfo['sChurchCountry'])): ?>
                                    <?= InputUtils::escapeHTML($countries[$churchInfo['sChurchCountry']] ?? $churchInfo['sChurchCountry']) ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($churchInfo['sChurchPhone'])): ?>
                                    <i class="fa-solid fa-phone mr-1"></i><?= InputUtils::escapeHTML($churchInfo['sChurchPhone']) ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($churchInfo['sChurchEmail'])): ?>
                                    <i class="fa-solid fa-envelope mr-1"></i><a href="mailto:<?= InputUtils::escapeHTML($churchInfo['sChurchEmail']) ?>"><?= InputUtils::escapeHTML($churchInfo['sChurchEmail']) ?></a><br>
                                    <?php endif; ?>
                                    <?php if (!empty($churchInfo['sChurchWebSite'])): ?>
                                    <i class="fa-solid fa-globe mr-1"></i><a href="<?= InputUtils::escapeHTML($churchInfo['sChurchWebSite']) ?>" target="_blank" rel="noopener noreferrer"><?= InputUtils::escapeHTML($churchInfo['sChurchWebSite']) ?></a>
                                    <?php endif; ?>
                                </address>
                            </div>

                            <?php if (empty($churchInfo['sChurchName'])): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                                <?= gettext('Church name is required. Please complete the Basic tab.') ?>
                            </div>
                            <?php endif; ?>
                        </div>

                    </div><!-- /.tab-content -->

                    <!-- Form Actions -->
                    <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-save mr-1"></i>
                                <?= gettext('Save Church Information') ?>
                            </button>
                            <a href="<?= $sRootPath ?>/admin/" class="btn btn-secondary ml-2">
                                <?= gettext('Cancel') ?>
                            </a>
                        </div>
                        <small class="text-muted">
                            <span class="text-danger">*</span> <?= gettext('Required') ?>
                        </small>
                    </div>

                </form>
            </div><!-- /.card-body -->
        </div><!-- /.card -->
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
