<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

include SystemURLs::getDocumentRoot() . '/Include/Header.php';

$featureGroups = [
    gettext('Primary Modules') => [
        'bEnabledFinance' => [
            'label' => gettext('Finance Module'),
            'description' => gettext('Enable/disable the Finance menu and all finance features (deposits, payments, reports)'),
        ],
        'bEnabledFundraiser' => [
            'label' => gettext('Fundraiser Module'),
            'description' => gettext('Enable/disable the Fundraiser menu'),
        ],
        'bEnabledEvents' => [
            'label' => gettext('Events Module'),
            'description' => gettext('Enable/disable the Events section in main navigation'),
        ],
        'bEnabledSundaySchool' => [
            'label' => gettext('Sunday School Module'),
            'description' => gettext('Show/hide the Sunday School module in sidebar'),
        ],
    ],
    gettext('Communication & Email') => [
        'bEnabledEmail' => [
            'label' => gettext('Email System'),
            'description' => gettext('Master toggle for email sending. REQUIRED for password resets and notifications'),
        ],
        'bEnableBirthdayEmails' => [
            'label' => gettext('Birthday Greeting Emails'),
            'description' => gettext('Automatically send birthday emails (requires background job scheduler)'),
        ],
    ],
    gettext('Access Control & Security') => [
        'bEnableLostPassword' => [
            'label' => gettext('Lost Password Link'),
            'description' => gettext('Show "Lost Password" option on login screen (allows user self-reset)'),
        ],
        'bEnableExternalCalendarAPI' => [
            'label' => gettext('Public Calendar API'),
            'description' => gettext('Allow unauthenticated access to public events (for embedding calendars)'),
        ],
        'bEnforceCSP' => [
            'label' => gettext('Content Security Policy'),
            'description' => gettext('Enforce strict CSP headers to protect against XSS attacks'),
        ],
    ],
];
?>

<div class="page-body">
    <div class="container-xl">
        <div class="alert alert-info">
            <div class="d-flex">
                <div>
                    <i class="fa-solid fa-circle-info me-2"></i>
                </div>
                <div>
                    <?= gettext('Feature toggles control which modules and features are available in ChurchCRM. Changes take effect immediately.') ?>
                </div>
            </div>
        </div>

        <div id="featureTogglesContainer">
            <?php foreach ($featureGroups as $groupName => $features): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title"><?= $groupName ?></h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <tbody>
                                <?php foreach ($features as $settingKey => $settingInfo): ?>
                                    <tr data-setting="<?= $settingKey ?>">
                                        <td class="w-100">
                                            <div class="font-weight-medium"><?= $settingInfo['label'] ?></div>
                                            <small class="text-muted"><?= $settingInfo['description'] ?></small>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex align-items-center gap-2 justify-content-end">
                                                <span class="status-badge admin-status-badge-sm" style="display: none;"></span>
                                                <label class="form-check form-switch form-check-single m-0">
                                                    <input class="form-check-input feature-toggle" type="checkbox"
                                                        aria-label="<?= InputUtils::escapeAttribute($settingInfo['label']) ?>"
                                                        data-setting="<?= $settingKey ?>"
                                                        <?= SystemConfig::getBooleanValue($settingKey) ? 'checked' : '' ?>>
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/feature-toggles.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
