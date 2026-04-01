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

<form method="POST"
      action="<?= $sRootPath ?>/admin/system/church-info"
      id="church-info-form"
      novalidate>

    <!-- Church Identity -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-church me-2"></i><?= gettext('Church Identity') ?></h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
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

                    <div class="mb-3">
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
            </div>
        </div>
    </div>

    <!-- Contact Information -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-address-book me-2"></i><?= gettext('Contact Information') ?></h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="mb-3 col-md-6">
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
                        </div>

                        <div class="mb-3 col-md-6">
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Location -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-map me-2"></i><?= gettext('Location') ?></h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
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

                    <div class="row">
                        <div class="mb-3 col-md-4">
                            <label for="sChurchCity"><?= gettext('City') ?> <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control"
                                   id="sChurchCity"
                                   name="sChurchCity"
                                   value="<?= InputUtils::escapeHTML($churchInfo['sChurchCity']) ?>"
                                   maxlength="100"
                                   required>
                        </div>
                        <div class="mb-3 col-md-3">
                            <label for="sChurchState"><?= gettext('State') ?> <span class="text-danger">*</span></label>
                            <div id="sChurchStateContainer" style="width: 100%;"
                                 data-user-selected-state="<?= InputUtils::escapeHTML($churchInfo['sChurchState']) ?>">
                            </div>
                        </div>
                        <div class="mb-3 col-md-2">
                            <label for="sChurchZip"><?= gettext('Zip Code') ?> <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control"
                                   id="sChurchZip"
                                   name="sChurchZip"
                                   value="<?= InputUtils::escapeHTML($churchInfo['sChurchZip']) ?>"
                                   maxlength="20"
                                   required>
                        </div>
                        <div class="mb-3 col-md-3">
                            <label for="sChurchCountry"><?= gettext('Country') ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="sChurchCountry" name="sChurchCountry" style="width: 100%;"
                                    data-user-selected="<?= InputUtils::escapeHTML($churchInfo['sChurchCountry']) ?>">
                            </select>
                        </div>
                    </div>

                    <?php
                    $hasCoords = !empty($churchInfo['iChurchLatitude'])
                        && !empty($churchInfo['iChurchLongitude'])
                        && ((float) $churchInfo['iChurchLatitude'] !== 0.0
                            || (float) $churchInfo['iChurchLongitude'] !== 0.0);
                    ?>

                    <?php if ($hasCoords): ?>
                    <hr class="my-3">
                    <h5 class="mb-3"><?= gettext('Map') ?></h5>
                    <link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/external/leaflet/leaflet.css') ?>">
                    <div id="church-location-map" class="mb-2 rounded border" style="height:280px;"></div>
                    <p class="text-muted small mb-0">
                        <i class="fa-solid fa-location-dot me-1"></i>
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
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fa-solid fa-location-dot me-2"></i>
                        <?= gettext('A map will appear here once a street address is saved. Coordinates are detected automatically — no manual entry required.') ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Language & Localization -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-globe me-2"></i><?= gettext('Language & Localization') ?></h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="mb-3 col-md-4">
                            <label for="sLanguage"><?= gettext('Language') ?></label>
                            <select class="form-select auto-tomselect" id="sLanguage" name="sLanguage" style="width: 100%;">
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
                                <?= gettext('System language for the church.') ?>
                            </small>
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="sTimeZone"><?= gettext('Time Zone') ?></label>
                            <select class="form-select auto-tomselect" id="sTimeZone" name="sTimeZone" style="width: 100%;">
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
                        <div class="mb-3 col-md-4">
                            <label for="sDistanceUnit"><?= gettext('Distance Unit') ?></label>
                            <select class="form-select" id="sDistanceUnit" name="sDistanceUnit">
                                <option value="miles" <?= ($churchInfo['sDistanceUnit'] === 'miles') ? 'selected' : '' ?>>
                                    <?= gettext('miles') ?>
                                </option>
                                <option value="kilometers" <?= ($churchInfo['sDistanceUnit'] === 'kilometers') ? 'selected' : '' ?>>
                                    <?= gettext('kilometers') ?>
                                </option>
                            </select>
                            <small class="form-text text-muted">
                                <?= gettext('Unit used to measure distance.') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Address Defaults -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0"><i class="fa-solid fa-copy me-2"></i><?= gettext('Address Defaults') ?></h3>
                    <button type="button" class="btn btn-outline-primary btn-sm ms-auto" id="copy-church-address">
                        <i class="fa-solid fa-copy me-1"></i><?= gettext('Copy from church address') ?>
                    </button>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        <?= gettext('These values are pre-filled when creating new families. Leave blank to require manual entry.') ?>
                    </p>
                    <div class="row">
                        <div class="mb-3 col-md-4">
                            <label for="sDefaultCity"><?= gettext('Default City') ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="sDefaultCity"
                                   name="sDefaultCity"
                                   value="<?= InputUtils::escapeHTML($churchInfo['sDefaultCity']) ?>"
                                   maxlength="100">
                        </div>
                        <div class="mb-3 col-md-3">
                            <label for="sDefaultState"><?= gettext('Default State') ?></label>
                            <div id="sDefaultStateContainer" style="width: 100%;"
                                 data-user-selected-state="<?= InputUtils::escapeHTML($churchInfo['sDefaultState']) ?>">
                            </div>
                        </div>
                        <div class="mb-3 col-md-2">
                            <label for="sDefaultZip"><?= gettext('Default Zip') ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="sDefaultZip"
                                   name="sDefaultZip"
                                   value="<?= InputUtils::escapeHTML($churchInfo['sDefaultZip']) ?>"
                                   maxlength="20">
                        </div>
                        <div class="mb-3 col-md-3">
                            <label for="sDefaultCountry"><?= gettext('Default Country') ?></label>
                            <select class="form-select" id="sDefaultCountry" name="sDefaultCountry" style="width: 100%;"
                                    data-user-selected="<?= InputUtils::escapeHTML($churchInfo['sDefaultCountry']) ?>">
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Display Preview -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-eye me-2"></i><?= gettext('Display Preview') ?></h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        <?= gettext('This is how your church information will appear on reports and directories.') ?>
                    </p>

                    <div class="card-body bg-light rounded">
                        <address class="mb-0">
                            <?php if (!empty($churchInfo['sChurchName'])): ?>
                            <strong><?= InputUtils::escapeHTML($churchInfo['sChurchName']) ?></strong><br>
                            <?php endif; ?>
                            <?php if (!empty($churchInfo['sChurchAddress'])): ?>
                            <?= InputUtils::escapeHTML($churchInfo['sChurchAddress']) ?><br>
                            <?php endif; ?>
                            <?php
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
                            <i class="fa-solid fa-phone me-1"></i><?= InputUtils::escapeHTML($churchInfo['sChurchPhone']) ?><br>
                            <?php endif; ?>
                            <?php if (!empty($churchInfo['sChurchEmail'])): ?>
                            <i class="fa-solid fa-envelope me-1"></i><a href="mailto:<?= InputUtils::escapeAttribute($churchInfo['sChurchEmail']) ?>" target="_blank" rel="noopener noreferrer"><?= InputUtils::escapeHTML($churchInfo['sChurchEmail']) ?></a><br>
                            <?php endif; ?>
                            <?php if (!empty($churchInfo['sChurchWebSite'])): ?>
                            <i class="fa-solid fa-globe me-1"></i><a href="<?= InputUtils::escapeAttribute($churchInfo['sChurchWebSite']) ?>" target="_blank" rel="noopener noreferrer"><?= InputUtils::escapeHTML($churchInfo['sChurchWebSite']) ?></a>
                            <?php endif; ?>
                        </address>
                    </div>

                    <?php if (empty($churchInfo['sChurchName'])): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        <?= gettext('Church name is required. Please fill in the Church Identity section above.') ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk me-1"></i>
                            <?= gettext('Save Church Information') ?>
                        </button>
                        <a href="<?= $sRootPath ?>/admin/" class="btn btn-secondary ms-2">
                            <?= gettext('Cancel') ?>
                        </a>
                    </div>
                    <small class="text-muted">
                        <span class="text-danger">*</span> <?= gettext('Required') ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

</form>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function () {
    var requiredFields = ['sChurchName', 'sChurchPhone', 'sChurchEmail', 'sChurchAddress', 'sChurchCity', 'sChurchState', 'sChurchZip', 'sChurchCountry'];

    document.getElementById('church-info-form').addEventListener('submit', function (e) {
        e.preventDefault();

        var firstInvalidField = null;
        var hasErrors = false;

        for (var i = 0; i < requiredFields.length; i++) {
            var el = document.getElementById(requiredFields[i]);
            if (!el) { continue; }

            if (!el.value.trim()) {
                el.classList.add('is-invalid');
                // For TomSelect-enhanced selects, also mark the wrapper
                if (el.tomselect) {
                    var tsWrapper = el.parentNode.querySelector('.ts-wrapper');
                    if (tsWrapper) { tsWrapper.classList.add('is-invalid'); }
                }
                hasErrors = true;
                if (!firstInvalidField) {
                    firstInvalidField = el;
                }
            } else {
                el.classList.remove('is-invalid');
                if (el.tomselect) {
                    var tsWrapper = el.parentNode.querySelector('.ts-wrapper');
                    if (tsWrapper) { tsWrapper.classList.remove('is-invalid'); }
                }
            }
        }

        if (hasErrors) {
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // Focus via TomSelect API when available, otherwise native focus
            if (firstInvalidField.tomselect) {
                firstInvalidField.tomselect.focus();
            } else {
                firstInvalidField.focus();
            }
            return;
        }

        this.submit();
    });

    // Clear is-invalid on input/change
    document.querySelectorAll('#church-info-form input, #church-info-form select').forEach(function (el) {
        ['input', 'change'].forEach(function (evtName) {
            el.addEventListener(evtName, function () {
                if (this.value && this.value.trim()) {
                    this.classList.remove('is-invalid');
                    const wrapper = this.nextElementSibling;
                    if (wrapper && wrapper.classList.contains('ts-wrapper')) {
                        wrapper.classList.remove('is-invalid');
                    }
                }
            });
        });
    });
})();
</script>

<!-- Church Info page JavaScript -->
<script src="<?= SystemURLs::assetVersioned('/skin/v2/church-info.min.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
