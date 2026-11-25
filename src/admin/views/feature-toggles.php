<?php

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
$sections = [];
foreach ($featureGroups as $groupName => $features) {
    $settings = [];
    foreach ($features as $settingKey => $settingInfo) {
        $settings[] = ['name' => $settingKey, 'type' => 'boolean', 'label' => $settingInfo['label'], 'tooltip' => $settingInfo['description']];
    }
    $sections[] = ['id' => 'featureGroup' . count($sections), 'title' => $groupName, 'settings' => $settings];
}
?>

<div id="featureTogglesContainer" data-reload-on-save>
    <?php foreach ($sections as $section) : ?>
        <div id="<?= InputUtils::escapeAttribute($section['id']) ?>" class="mb-3"
             data-title="<?= InputUtils::escapeAttribute($section['title']) ?>"
             data-icon="fa-solid fa-toggle-on"
             data-settings="<?= InputUtils::jsonEncodeForAttribute($section['settings']) ?>"></div>
    <?php endforeach; ?>
</div>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/admin-settings.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
