<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;

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
        'bEnableSelfRegistration' => [
            'label' => gettext('Self-Registration'),
            'description' => gettext('Allow visitors to create their own family records via public registration page'),
        ],
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

<div class="page-wrapper">
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title"><?= gettext('Feature Toggles') ?></h2>
                    <div class="page-subtitle"><?= gettext('Enable or disable features for your organization') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <div class="alert alert-info">
            <div class="d-flex">
                <div>
                    <i class="fa-solid fa-circle-info me-2"></i>
                </div>
                <div>
                    <strong><?= gettext('Feature toggles') ?></strong>
                    <?= gettext('control which modules and features are available in ChurchCRM. Changes take effect immediately.') ?>
                </div>
            </div>
        </div>

        <form id="featureTogglesForm">
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
                                    <tr>
                                        <td style="width: 100%;">
                                            <div class="font-weight-medium"><?= $settingInfo['label'] ?></div>
                                            <small class="text-muted"><?= $settingInfo['description'] ?></small>
                                        </td>
                                        <td class="text-end">
                                            <label class="form-check form-switch form-check-single m-0">
                                                <input class="form-check-input feature-toggle" type="checkbox"
                                                    name="<?= $settingKey ?>" value="1"
                                                    data-setting="<?= $settingKey ?>"
                                                    <?= $isEnabled ? 'checked' : '' ?>>
                                            </label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="form-footer">
                <a href="/admin/" class="btn btn-link"><?= gettext('Cancel') ?></a>
                <button type="submit" class="btn btn-primary"><?= gettext('Save Changes') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('featureTogglesForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const updates = {};
    document.querySelectorAll('.feature-toggle').forEach(checkbox => {
        updates[checkbox.name] = checkbox.checked ? '1' : '0';
    });

    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i><?= gettext('Saving...') ?>';

    fetch('/api/system/feature-toggles', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(updates)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('<?= gettext('Feature settings saved successfully') ?>', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.error || '<?= gettext('Error saving settings') ?>', 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<?= gettext('Save Changes') ?>';
        }
    })
    .catch(error => {
        showAlert('<?= gettext('Error saving settings') ?>', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<?= gettext('Save Changes') ?>';
        console.error('Error:', error);
    });
});
</script>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
