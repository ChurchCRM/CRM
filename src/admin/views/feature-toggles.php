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
    gettext('Finance Features') => [
        'bEnableNonDeductible' => [
            'label' => gettext('Non-Deductible Payments'),
            'description' => gettext('Allow recording non-tax-deductible payments (e.g., merchandise sales)'),
        ],
    ],
    gettext('Search Settings') => [
        'bSearchIncludePersons' => [
            'label' => gettext('Search People'),
            'description' => gettext('Include people in quick global search'),
        ],
        'bSearchIncludeFamilies' => [
            'label' => gettext('Search Families'),
            'description' => gettext('Include families in quick global search'),
        ],
        'bSearchIncludeGroups' => [
            'label' => gettext('Search Groups'),
            'description' => gettext('Include groups in quick global search'),
        ],
        'bSearchIncludeDeposits' => [
            'label' => gettext('Search Deposits'),
            'description' => gettext('Include deposits in quick global search'),
        ],
        'bSearchIncludePayments' => [
            'label' => gettext('Search Payments'),
            'description' => gettext('Include payments in quick global search'),
        ],
        'bSearchIncludeAddresses' => [
            'label' => gettext('Search Addresses'),
            'description' => gettext('Include addresses in quick global search'),
        ],
        'bSearchIncludeCalendarEvents' => [
            'label' => gettext('Search Calendar Events'),
            'description' => gettext('Include calendar events in quick global search'),
        ],
        'bSearchIncludeFamilyCustomProperties' => [
            'label' => gettext('Search Family Custom Properties'),
            'description' => gettext('Include custom family properties in quick global search'),
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
                                    <?php $isEnabled = SystemConfig::getBooleanValue($settingKey); ?>
                                    <tr data-setting="<?= $settingKey ?>">
                                        <td style="width: 100%;">
                                            <div class="font-weight-medium"><?= $settingInfo['label'] ?></div>
                                            <small class="text-muted"><?= $settingInfo['description'] ?></small>
                                        </td>
                                        <td class="text-end">
                                            <div style="display: flex; align-items: center; gap: 0.5rem; justify-content: flex-end;">
                                                <span class="status-badge" style="font-size: 0.75rem; display: none;"></span>
                                                <label class="form-check form-switch form-check-single m-0">
                                                    <input class="form-check-input feature-toggle" type="checkbox"
                                                        aria-label="<?= InputUtils::escapeAttribute($settingInfo['label']) ?>"
                                                        data-setting="<?= $settingKey ?>"
                                                        <?= $isEnabled ? 'checked' : '' ?>>
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

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
document.querySelectorAll('.feature-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const setting = this.dataset.setting;
        const isChecked = this.checked;
        const row = this.closest('tr');
        const badge = row.querySelector('.status-badge');

        const updates = {};
        updates[setting] = isChecked ? '1' : '0';

        badge.textContent = '<?= gettext('Saving...') ?>';
        badge.style.display = 'inline';
        badge.className = 'status-badge badge bg-info';

        fetch('<?= SystemURLs::getRootPath() ?>/admin/api/system/feature-toggles', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(updates)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                badge.textContent = '<?= gettext('Saved') ?>';
                badge.className = 'status-badge badge bg-success';
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                badge.textContent = '<?= gettext('Error') ?>';
                badge.className = 'status-badge badge bg-danger';
                this.checked = !isChecked;
                setTimeout(() => {
                    badge.style.display = 'none';
                }, 3000);
            }
        })
        .catch(error => {
            badge.textContent = '<?= gettext('Error') ?>';
            badge.className = 'status-badge badge bg-danger';
            this.checked = !isChecked;
            console.error('Error:', error);
            setTimeout(() => {
                badge.style.display = 'none';
            }, 3000);
        });
    });
});
</script>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
