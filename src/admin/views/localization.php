<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// sGlobalMessage / sGlobalMessageClass are consumed by Footer.php via
// showGlobalMessage() → window.CRM.notify()
$sGlobalMessage      = $sGlobalMessage ?? '';
$sGlobalMessageClass = $sGlobalMessageClass ?? 'success';
?>

<form method="POST"
      action="<?= $sRootPath ?>/admin/system/localization"
      id="localization-form"
      novalidate>

    <!-- Language & Region -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-globe me-2"></i><?= gettext('Language &amp; Region') ?></h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="mb-3 col-md-4">
                            <label for="sLanguage"><?= gettext('Language') ?></label>
                            <select class="form-select" id="sLanguage" name="sLanguage"
                                data-selected-locale="<?= InputUtils::escapeAttribute($localeSettings['sLanguage']) ?>"
                                style="width: 100%;"></select>
                            <small class="form-text text-body-secondary">
                                <?= gettext('System language for the church.') ?>
                            </small>
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="sTimeZone"><?= gettext('Time Zone') ?></label>
                            <select class="form-select auto-tomselect" id="sTimeZone" name="sTimeZone" style="width: 100%;">
                                <?php foreach ($timezones as $tz): ?>
                                <option value="<?= InputUtils::escapeHTML($tz) ?>"
                                    <?= ($localeSettings['sTimeZone'] === $tz) ? 'selected' : '' ?>>
                                    <?= InputUtils::escapeHTML($tz) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-body-secondary">
                                <?= gettext('Used for scheduling events and reporting times.') ?>
                            </small>
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="sDistanceUnit"><?= gettext('Distance Unit') ?></label>
                            <select class="form-select" id="sDistanceUnit" name="sDistanceUnit">
                                <option value="miles" <?= ($localeSettings['sDistanceUnit'] === 'miles') ? 'selected' : '' ?>>
                                    <?= gettext('miles') ?>
                                </option>
                                <option value="kilometers" <?= ($localeSettings['sDistanceUnit'] === 'kilometers') ? 'selected' : '' ?>>
                                    <?= gettext('kilometers') ?>
                                </option>
                            </select>
                            <small class="form-text text-body-secondary">
                                <?= gettext('Unit used to measure distance.') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Date & Time Formats -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-calendar-days me-2"></i><?= gettext('Date &amp; Time Formats') ?></h3>
                </div>
                <div class="card-body">
                    <p class="text-body-secondary mb-2">
                        <?= gettext('These fields use formatting codes (letters that stand for parts of a date). Pick a preset below each field, or build your own using the reference. The live preview shows exactly how it will look. Leave blank to use the system default.') ?>
                    </p>

                    <p class="mb-3">
                        <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="collapse" data-bs-target="#dateTokenReference" aria-expanded="false" aria-controls="dateTokenReference">
                            <i class="fa-solid fa-circle-question me-1"></i><?= gettext('What do these codes mean?') ?>
                        </button>
                    </p>

                    <div class="collapse mb-3" id="dateTokenReference">
                        <div class="card-body bg-light rounded">
                            <p class="small text-body-secondary mb-2">
                                <?= gettext('Common formatting codes (examples use Friday, 7 March 2025, 3:09 PM)') ?>
                            </p>
                            <div class="row small">
                                <div class="col-md-4">
                                    <strong><?= gettext('Day') ?></strong>
                                    <dl class="row mb-0">
                                        <dt class="col-3 fw-normal"><code>d</code></dt><dd class="col-9 mb-0"><?= gettext('Day, 2 digits') ?> — 07</dd>
                                        <dt class="col-3 fw-normal"><code>j</code></dt><dd class="col-9 mb-0"><?= gettext('Day, no leading zero') ?> — 7</dd>
                                        <dt class="col-3 fw-normal"><code>D</code></dt><dd class="col-9 mb-0"><?= gettext('Weekday, short') ?> — Fri</dd>
                                        <dt class="col-3 fw-normal"><code>l</code></dt><dd class="col-9 mb-0"><?= gettext('Weekday, full') ?> — Friday</dd>
                                    </dl>
                                </div>
                                <div class="col-md-4">
                                    <strong><?= gettext('Month &amp; Year') ?></strong>
                                    <dl class="row mb-0">
                                        <dt class="col-3 fw-normal"><code>m</code></dt><dd class="col-9 mb-0"><?= gettext('Month, 2 digits') ?> — 03</dd>
                                        <dt class="col-3 fw-normal"><code>n</code></dt><dd class="col-9 mb-0"><?= gettext('Month, no leading zero') ?> — 3</dd>
                                        <dt class="col-3 fw-normal"><code>M</code></dt><dd class="col-9 mb-0"><?= gettext('Month, short') ?> — Mar</dd>
                                        <dt class="col-3 fw-normal"><code>F</code></dt><dd class="col-9 mb-0"><?= gettext('Month, full') ?> — March</dd>
                                        <dt class="col-3 fw-normal"><code>Y</code></dt><dd class="col-9 mb-0"><?= gettext('Year, 4 digits') ?> — 2025</dd>
                                        <dt class="col-3 fw-normal"><code>y</code></dt><dd class="col-9 mb-0"><?= gettext('Year, 2 digits') ?> — 25</dd>
                                    </dl>
                                </div>
                                <div class="col-md-4">
                                    <strong><?= gettext('Time') ?></strong>
                                    <dl class="row mb-0">
                                        <dt class="col-3 fw-normal"><code>g</code></dt><dd class="col-9 mb-0"><?= gettext('Hour 12h, no zero') ?> — 3</dd>
                                        <dt class="col-3 fw-normal"><code>h</code></dt><dd class="col-9 mb-0"><?= gettext('Hour 12h, 2 digits') ?> — 03</dd>
                                        <dt class="col-3 fw-normal"><code>H</code></dt><dd class="col-9 mb-0"><?= gettext('Hour 24h, 2 digits') ?> — 15</dd>
                                        <dt class="col-3 fw-normal"><code>i</code></dt><dd class="col-9 mb-0"><?= gettext('Minutes') ?> — 09</dd>
                                        <dt class="col-3 fw-normal"><code>a</code></dt><dd class="col-9 mb-0"><?= gettext('am / pm') ?> — pm</dd>
                                        <dt class="col-3 fw-normal"><code>A</code></dt><dd class="col-9 mb-0"><?= gettext('AM / PM') ?> — PM</dd>
                                    </dl>
                                </div>
                            </div>
                            <p class="small text-body-secondary mb-0 mt-2">
                                <?= gettext('Example: "l, j F Y" produces "Friday, 7 March 2025". Any other characters (spaces, commas, slashes) appear as typed.') ?>
                            </p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="mb-3 col-md-4">
                            <label for="sDateFormatLong"><?= gettext('Long Date Format') ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="sDateFormatLong"
                                   name="sDateFormatLong"
                                   value="<?= InputUtils::escapeHTML($localeSettings['sDateFormatLong']) ?>"
                                   maxlength="50"
                                   placeholder="m/d/Y">
                            <small class="form-text text-body-secondary">
                                <?= gettext('Used on reports and member lists. Default: m/d/Y') ?>
                            </small>
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="sDateFormatNoYear"><?= gettext('Date Without Year') ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="sDateFormatNoYear"
                                   name="sDateFormatNoYear"
                                   value="<?= InputUtils::escapeHTML($localeSettings['sDateFormatNoYear']) ?>"
                                   maxlength="50"
                                   placeholder="m/d">
                            <small class="form-text text-body-secondary">
                                <?= gettext('Birthdays and anniversaries. Default: m/d') ?>
                            </small>
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="sDateTimeFormat"><?= gettext('Date &amp; Time Format') ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="sDateTimeFormat"
                                   name="sDateTimeFormat"
                                   value="<?= InputUtils::escapeHTML($localeSettings['sDateTimeFormat']) ?>"
                                   maxlength="50"
                                   placeholder="m/d/Y g:i a">
                            <small class="form-text text-body-secondary">
                                <?= gettext('Includes time component. Default: m/d/Y g:i a') ?>
                            </small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="mb-3 col-md-4">
                            <label for="sDateFilenameFormat"><?= gettext('Filename Date Format') ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="sDateFilenameFormat"
                                   name="sDateFilenameFormat"
                                   value="<?= InputUtils::escapeHTML($localeSettings['sDateFilenameFormat']) ?>"
                                   maxlength="50"
                                   placeholder="Ymd-Gis">
                            <small class="form-text text-body-secondary">
                                <?= gettext('Used in exported file names. Default: Ymd-Gis') ?>
                            </small>
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="sDatePickerFormat"><?= gettext('Date Picker Format') ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="sDatePickerFormat"
                                   name="sDatePickerFormat"
                                   value="<?= InputUtils::escapeHTML($localeSettings['sDatePickerFormat']) ?>"
                                   maxlength="50"
                                   placeholder="Y-m-d">
                            <small class="form-text text-body-secondary">
                                <?= gettext('PHP format for date picker inputs. Default: Y-m-d') ?>
                            </small>
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="sDatePickerPlaceHolder"><?= gettext('Date Picker Placeholder') ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="sDatePickerPlaceHolder"
                                   name="sDatePickerPlaceHolder"
                                   value="<?= InputUtils::escapeHTML($localeSettings['sDatePickerPlaceHolder']) ?>"
                                   maxlength="50"
                                   placeholder="yyyy-mm-dd">
                            <small class="form-text text-body-secondary">
                                <?= gettext('Hint text shown in date inputs. Default: yyyy-mm-dd') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Currency & Finance Formats -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-dollar-sign me-2"></i><?= gettext('Currency &amp; Finance Formats') ?></h3>
                </div>
                <div class="card-body">
                    <p class="text-body-secondary mb-3">
                        <?= gettext('Configure how monetary amounts are displayed throughout the system. As finance pages are updated to use these settings (epic #8459), the symbol, position, and separators configured here will apply system-wide.') ?>
                    </p>

                    <div class="mb-3">
                        <label class="form-label d-block"><?= gettext('Quick presets') ?></label>
                        <div id="currency-presets" class="d-flex flex-wrap gap-1" aria-label="<?= gettext('Currency presets') ?>"></div>
                        <small class="form-text text-body-secondary"><?= gettext('Populates the fields below without saving. Click Save to apply.') ?></small>
                    </div>

                    <div class="row">
                        <div class="mb-3 col-md-4">
                            <label for="sCurrencySymbol"><?= gettext('Currency Symbol') ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="sCurrencySymbol"
                                   name="sCurrencySymbol"
                                   value="<?= InputUtils::escapeHTML($localeSettings['sCurrencySymbol']) ?>"
                                   maxlength="8"
                                   placeholder="$">
                            <small class="form-text text-body-secondary">
                                <?= gettext('Symbol or code shown next to amounts (e.g. $, €, £, CHF, CAD $). Default: $') ?>
                            </small>
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="sCurrencyPosition"><?= gettext('Symbol Position') ?></label>
                            <select class="form-select" id="sCurrencyPosition" name="sCurrencyPosition">
                                <option value="before" <?= ($localeSettings['sCurrencyPosition'] === 'before') ? 'selected' : '' ?>>
                                    <?= gettext('Before amount (¤ 100.00)') ?>
                                </option>
                                <option value="after" <?= ($localeSettings['sCurrencyPosition'] === 'after') ? 'selected' : '' ?>>
                                    <?= gettext('After amount (100.00 ¤)') ?>
                                </option>
                            </select>
                            <small class="form-text text-body-secondary">
                                <?= gettext('Whether the symbol appears before or after the numeric amount. Default: Before amount') ?>
                            </small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="mb-3 col-md-4">
                            <label for="sThousandsSeparator"><?= gettext('Thousands Separator') ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="sThousandsSeparator"
                                   name="sThousandsSeparator"
                                   value="<?= InputUtils::escapeHTML($localeSettings['sThousandsSeparator']) ?>"
                                   maxlength="1"
                                   placeholder=",">
                            <small class="form-text text-body-secondary">
                                <?= gettext('Single character used to group thousands (e.g. , or .). Default: ,') ?>
                            </small>
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="sDecimalSeparator"><?= gettext('Decimal Separator') ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="sDecimalSeparator"
                                   name="sDecimalSeparator"
                                   value="<?= InputUtils::escapeHTML($localeSettings['sDecimalSeparator']) ?>"
                                   maxlength="1"
                                   placeholder=".">
                            <small class="form-text text-body-secondary">
                                <?= gettext('Single character used as the decimal point (e.g. . or ,). Default: .') ?>
                            </small>
                        </div>
                        <div class="mb-3 col-md-4 d-flex align-items-end">
                            <div class="w-100">
                                <label class="form-label"><?= gettext('Live Preview') ?></label>
                                <div class="card-body bg-light rounded py-2 px-3">
                                    <span class="fw-medium" id="currency-format-preview">&mdash;</span>
                                </div>
                                <small class="form-text text-body-secondary"><?= gettext('Updates as you type.') ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Phone Number Formats -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-phone me-2"></i><?= gettext('Phone Number Formats') ?></h3>
                </div>
                <div class="card-body">
                    <p class="text-body-secondary mb-3">
                        <?= gettext('Use 9 as a digit placeholder (e.g. (999) 999-9999). Leave blank to use the system default.') ?>
                    </p>

                    <div class="row">
                        <div class="mb-3 col-md-4">
                            <label for="sPhoneFormat"><?= gettext('Home/Work Phone Format') ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="sPhoneFormat"
                                   name="sPhoneFormat"
                                   value="<?= InputUtils::escapeHTML($localeSettings['sPhoneFormat']) ?>"
                                   maxlength="50"
                                   placeholder="(999) 999-9999">
                            <small class="form-text text-body-secondary">
                                <?= gettext('Default: (999) 999-9999') ?>
                            </small>
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="sPhoneFormatCell"><?= gettext('Cell Phone Format') ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="sPhoneFormatCell"
                                   name="sPhoneFormatCell"
                                   value="<?= InputUtils::escapeHTML($localeSettings['sPhoneFormatCell']) ?>"
                                   maxlength="50"
                                   placeholder="(999) 999-9999">
                            <small class="form-text text-body-secondary">
                                <?= gettext('Default: (999) 999-9999') ?>
                            </small>
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="sPhoneFormatWithExt"><?= gettext('Phone with Extension') ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="sPhoneFormatWithExt"
                                   name="sPhoneFormatWithExt"
                                   value="<?= InputUtils::escapeHTML($localeSettings['sPhoneFormatWithExt']) ?>"
                                   maxlength="50"
                                   placeholder="(999) 999-9999 x99999">
                            <small class="form-text text-body-secondary">
                                <?= gettext('Default: (999) 999-9999 x99999') ?>
                            </small>
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
                    <p class="text-body-secondary">
                        <?= gettext('This is how dates and phone numbers will appear throughout the system.') ?>
                    </p>

                    <!-- Selected language: translation completeness + system support -->
                    <div class="mb-3">
                        <h5 class="mb-2"><i class="fa-solid fa-language me-2"></i><?= gettext('Selected Language') ?></h5>
                        <div class="card-body bg-light rounded" id="locale-preview"
                             data-system-check="<?= $systemCheckEnabled ? '1' : '0' ?>">
                            <div class="d-flex align-items-center mb-2">
                                <span class="fs-3 me-2" id="locale-preview-flag"></span>
                                <div>
                                    <div class="fw-medium" id="locale-preview-name">&mdash;</div>
                                    <div class="small text-body-secondary" id="locale-preview-code"></div>
                                </div>
                            </div>

                            <div id="locale-preview-translation" class="mb-2">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-body-secondary"><?= gettext('Translation completeness') ?></span>
                                    <span class="fw-medium" id="locale-preview-percent">—</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar" id="locale-preview-bar" role="progressbar" style="width: 0;"></div>
                                </div>
                                <div class="small text-body-secondary mt-1" id="locale-preview-translation-note"></div>
                            </div>

                            <div class="small" id="locale-preview-support"></div>
                        </div>
                    </div>

                    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
                        window.CRM = window.CRM || {};
                        window.CRM.localeStats = <?= json_encode($localeStats, JSON_THROW_ON_ERROR) ?>;
                        window.CRM.localeSystemCheckEnabled = <?= $systemCheckEnabled ? 'true' : 'false' ?>;
                        window.CRM.localePreviewI18n = {
                            translated: <?= json_encode(gettext('%d% of interface text is translated for this language.'), JSON_THROW_ON_ERROR) ?>,
                            fullyTranslated: <?= json_encode(gettext('Fully translated.'), JSON_THROW_ON_ERROR) ?>,
                            notTracked: <?= json_encode(gettext('Translation tracking is not available for this language.'), JSON_THROW_ON_ERROR) ?>,
                            supported: <?= json_encode(gettext('Supported — this locale is installed on the server.'), JSON_THROW_ON_ERROR) ?>,
                            notSupported: <?= json_encode(gettext('Not installed on the server — dates and numbers may not format correctly for this locale. Ask your host to install it (e.g. locale-gen).'), JSON_THROW_ON_ERROR) ?>,
                            checkUnavailable: <?= json_encode(gettext('Server locale support could not be checked (exec is disabled).'), JSON_THROW_ON_ERROR) ?>,
                        };
                    </script>

                    <div class="row">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <h5 class="mb-2"><i class="fa-solid fa-calendar-days me-2"></i><?= gettext('Date &amp; Time Formats') ?></h5>
                            <div class="card-body bg-light rounded">
                                <dl class="row row-cols-1 mb-0 small">
                                    <dt class="col-7 fw-normal text-body-secondary"><?= gettext('Long Date Format') ?></dt>
                                    <dd class="col-5 mb-1 text-end fw-medium format-preview-value" data-source="sDateFormatLong" data-kind="date">&mdash;</dd>

                                    <dt class="col-7 fw-normal text-body-secondary"><?= gettext('Date Without Year') ?></dt>
                                    <dd class="col-5 mb-1 text-end fw-medium format-preview-value" data-source="sDateFormatNoYear" data-kind="date">&mdash;</dd>

                                    <dt class="col-7 fw-normal text-body-secondary"><?= gettext('Date &amp; Time Format') ?></dt>
                                    <dd class="col-5 mb-1 text-end fw-medium format-preview-value" data-source="sDateTimeFormat" data-kind="date">&mdash;</dd>

                                    <dt class="col-7 fw-normal text-body-secondary"><?= gettext('Filename Date Format') ?></dt>
                                    <dd class="col-5 mb-1 text-end fw-medium format-preview-value" data-source="sDateFilenameFormat" data-kind="date">&mdash;</dd>

                                    <dt class="col-7 fw-normal text-body-secondary"><?= gettext('Date Picker Format') ?></dt>
                                    <dd class="col-5 mb-1 text-end fw-medium format-preview-value" data-source="sDatePickerFormat" data-kind="date">&mdash;</dd>

                                    <dt class="col-7 fw-normal text-body-secondary"><?= gettext('Date Picker Placeholder') ?></dt>
                                    <dd class="col-5 mb-0 text-end fw-medium format-preview-value" data-source="sDatePickerPlaceHolder" data-kind="literal">&mdash;</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5 class="mb-2"><i class="fa-solid fa-phone me-2"></i><?= gettext('Phone Number Formats') ?></h5>
                            <div class="card-body bg-light rounded">
                                <dl class="row row-cols-1 mb-0 small">
                                    <dt class="col-7 fw-normal text-body-secondary"><?= gettext('Home/Work Phone Format') ?></dt>
                                    <dd class="col-5 mb-1 text-end fw-medium format-preview-value" data-source="sPhoneFormat" data-kind="phone">&mdash;</dd>

                                    <dt class="col-7 fw-normal text-body-secondary"><?= gettext('Cell Phone Format') ?></dt>
                                    <dd class="col-5 mb-1 text-end fw-medium format-preview-value" data-source="sPhoneFormatCell" data-kind="phone">&mdash;</dd>

                                    <dt class="col-7 fw-normal text-body-secondary"><?= gettext('Phone with Extension') ?></dt>
                                    <dd class="col-5 mb-0 text-end fw-medium format-preview-value" data-source="sPhoneFormatWithExt" data-kind="phone">&mdash;</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
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
                            <?= gettext('Save Localization Settings') ?>
                        </button>
                        <a href="<?= $sRootPath ?>/admin/" class="btn btn-secondary ms-2">
                            <?= gettext('Cancel') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</form>

<!-- Localization page JavaScript -->
<script src="<?= SystemURLs::assetVersioned('/skin/v2/localization.min.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
