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
                           data-bs-toggle="tab" href="#basic" role="tab"
                           aria-controls="basic" aria-selected="true">
                            <i class="fa-solid fa-church mr-1"></i><?= gettext('Basic Information') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="location-tab"
                           data-bs-toggle="tab" href="#location" role="tab"
                           aria-controls="location" aria-selected="false">
                            <i class="fa-solid fa-map mr-1"></i><?= gettext('Location & Map') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="display-tab"
                           data-bs-toggle="tab" href="#display" role="tab"
                           aria-controls="display" aria-selected="false">
                            <i class="fa-solid fa-desktop mr-1"></i><?= gettext('Display Preview') ?>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <form method="POST"
                      action="<?= $sRootPath ?>/admin/system/church-info"
                      id="church-info-form"
                      novalidate>
                    <div class="tab-content" id="church-info-tab-content">

                        <!-- Tab 1: Basic Information & Contact -->
                        <div class="tab-pane fade show active" id="basic" role="tabpanel" aria-labelledby="basic-tab">
                            <h5 class="mb-4"><?= gettext('Basic Information') ?></h5>

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

                            <hr class="my-4">
                            <h5 class="mb-3"><?= gettext('Contact Information') ?></h5>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="sChurchPhone">
                                        <?= gettext('Phone Number') ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="tel"
                                           class="form-control"
                                           id="sChurchPhone"
                                           name="sChurchPhone"
                                           value="<?= InputUtils::escapeHTML($churchInfo['sChurchPhone']) ?>"
                                           maxlength="30"
                                           placeholder="(555) 555-5555"
                                           required>
                                    <small class="form-text text-muted">
                                        <?= gettext('Main contact phone number for the church.') ?>
                                    </small>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="sChurchEmail">
                                        <?= gettext('Email Address') ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="email"
                                           class="form-control"
                                           id="sChurchEmail"
                                           name="sChurchEmail"
                                           value="<?= InputUtils::escapeHTML($churchInfo['sChurchEmail']) ?>"
                                           maxlength="200"
                                           placeholder="info@yourchurch.org"
                                           required>
                                    <small class="form-text text-muted">
                                        <?= gettext('Main contact email for church communications.') ?>
                                    </small>
                                </div>
                            </div>

                            <hr class="my-4">
                            <h5 class="mb-3"><?= gettext('Language & Localization') ?></h5>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="sLanguage"><?= gettext('Language') ?></label>
                                    <select class="form-control select2" id="sLanguage" name="sLanguage" style="width: 100%;">
                                        <?php
                                        $supportedLocales = json_decode(file_get_contents(SystemURLs::getDocumentRoot() . '/locale/locales.json'), true);
                                        foreach ($supportedLocales as $locale => $localeData):
                                            $label = gettext($locale);
                                            $value = $localeData['locale'];
                                        ?>
                                        <option value="<?= InputUtils::escapeHTML($value) ?>"
                                            <?= ($churchInfo['sLanguage'] === $value) ? 'selected' : '' ?>>
                                            <?= InputUtils::escapeHTML($label) ?> [<?= InputUtils::escapeHTML($value) ?>]
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">
                                        <?= gettext('System language for the church. Affects date formats, phone formats, and other localizations.') ?>
                                    </small>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="sTimeZone"><?= gettext('Time Zone') ?></label>
                                    <select class="form-control select2" id="sTimeZone" name="sTimeZone" style="width: 100%;">
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
                        </div>

                        <!-- Tab 2: Location & Map -->
                        <div class="tab-pane fade" id="location" role="tabpanel" aria-labelledby="location-tab">
                            <h5 class="mb-4"><?= gettext('Location Information') ?></h5>

                            <div class="form-group">
                                <label for="sChurchAddress">
                                    <?= gettext('Street Address') ?>
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       id="sChurchAddress"
                                       name="sChurchAddress"
                                       value="<?= InputUtils::escapeHTML($churchInfo['sChurchAddress']) ?>"
                                       maxlength="200"
                                       required>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="sChurchCity"><?= gettext('City') ?> <span class="text-danger">*</span></label>
                                    <input type="text"
                                           class="form-control"
                                           id="sChurchCity"
                                           name="sChurchCity"
                                           value="<?= InputUtils::escapeHTML($churchInfo['sChurchCity']) ?>"
                                           maxlength="100"
                                           required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="sChurchState"><?= gettext('State') ?> <span class="text-danger">*</span></label>
                                    <div id="sChurchStateContainer" style="width: 100%;"
                                         data-user-selected-state="<?= InputUtils::escapeHTML($churchInfo['sChurchState']) ?>">
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="sChurchZip"><?= gettext('Zip Code') ?> <span class="text-danger">*</span></label>
                                    <input type="text"
                                           class="form-control"
                                           id="sChurchZip"
                                           name="sChurchZip"
                                           value="<?= InputUtils::escapeHTML($churchInfo['sChurchZip']) ?>"
                                           maxlength="20"
                                           required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="sChurchCountry"><?= gettext('Country') ?> <span class="text-danger">*</span></label>
                                    <select class="form-control" id="sChurchCountry" name="sChurchCountry" style="width: 100%;"
                                            data-user-selected="<?= InputUtils::escapeHTML($churchInfo['sChurchCountry']) ?>">
                                    </select>
                                </div>
                            </div>

                            <hr class="my-4">
                            <h5 class="mb-3"><?= gettext('Map') ?></h5>

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
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fa-solid fa-location-dot mr-2"></i>
                                <?= gettext('A map will appear here once a street address is saved. Coordinates are detected automatically — no manual entry required.') ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Tab 3: Display Preview -->
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
                                <?= gettext('Church name is required. Please complete the Basic Information tab.') ?>
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
                <script nonce="<?= SystemURLs::getCSPNonce() ?>">
                (function () {
                    // Required fields grouped by the tab nav-link that contains them
                    var tabFields = {
                        'basic-tab':    ['sChurchName', 'sChurchPhone', 'sChurchEmail'],
                        'location-tab': ['sChurchAddress', 'sChurchCity', 'sChurchZip', 'sChurchCountry'],
                    };

                    document.getElementById('church-info-form').addEventListener('submit', function (e) {
                        e.preventDefault(); // always prevent — we submit manually when valid

                        var firstInvalidTab = null;
                        var firstInvalidField = null;
                        var hasErrors = false;

                        for (var tabId in tabFields) {
                            var fields = tabFields[tabId];
                            for (var i = 0; i < fields.length; i++) {
                                var el = document.getElementById(fields[i]);
                                if (!el) { continue; }

                                if (!el.value.trim()) {
                                    el.classList.add('is-invalid');
                                    hasErrors = true;
                                    if (!firstInvalidTab) {
                                        firstInvalidTab  = tabId;
                                        firstInvalidField = el;
                                    }
                                } else {
                                    el.classList.remove('is-invalid');
                                }
                            }
                        }

                        if (hasErrors) {
                            // Switch to the tab that has the first error and focus the field
                            document.getElementById(firstInvalidTab).click();
                            firstInvalidField.focus();
                            return;
                        }

                        // All valid — submit for real
                        this.submit();
                    });

                    // Clear is-invalid on input/change so the user gets live feedback
                    // Both 'input' (text/number) and 'change' (select, Select2) must be covered.
                    document.querySelectorAll('#church-info-form input, #church-info-form select').forEach(function (el) {
                        ['input', 'change'].forEach(function (evtName) {
                            el.addEventListener(evtName, function () {
                                if (this.value && this.value.trim()) { this.classList.remove('is-invalid'); }
                            });
                        });
                    });
                })();
                </script>
            </div><!-- /.card-body -->
        </div><!-- /.card -->
    </div>
</div>

<!-- Church Info page JavaScript - handles country/state sync and map initialization -->
<script src="<?= SystemURLs::assetVersioned('/skin/v2/church-info.min.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
