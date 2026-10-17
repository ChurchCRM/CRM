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
        'sVolunteerVersion' => [
            'label' => gettext('Volunteer Management'),
            'description' => gettext('Which volunteer experience this church uses'),
            'helpOffcanvas' => '#ministry-experience-help',
            'choices' => [
                'v1' => gettext('V1 — Volunteer Opportunities (legacy)'),
                'v2' => gettext('V2 — Ministries'),
                'both' => gettext('Both (transition)'),
            ],
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
                                    <?php $isChoice = isset($settingInfo['choices']); ?>
                                    <?php $isEnabled = $isChoice ? false : SystemConfig::getBooleanValue($settingKey); ?>
                                    <tr data-setting="<?= $settingKey ?>">
                                        <td class="w-100">
                                            <div class="font-weight-medium"><?= $settingInfo['label'] ?></div>
                                            <small class="text-muted"><?= $settingInfo['description'] ?></small>
                                            <?php if (isset($settingInfo['helpOffcanvas'])): ?>
                                                <a href="#" role="button" class="small ms-1" data-bs-toggle="offcanvas" data-bs-target="<?= InputUtils::escapeAttribute($settingInfo['helpOffcanvas']) ?>"><?= gettext('What do these mean?') ?></a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex align-items-center gap-2 justify-content-end">
                                                <span class="status-badge admin-status-badge-sm" style="display: none;"></span>
                                                <?php if ($isChoice): ?>
                                                    <?php $currentChoice = (string) SystemConfig::getValue($settingKey); ?>
                                                    <select class="form-select feature-choice w-auto" data-setting="<?= $settingKey ?>"
                                                        aria-label="<?= InputUtils::escapeAttribute($settingInfo['label']) ?>">
                                                        <?php foreach ($settingInfo['choices'] as $value => $choiceLabel): ?>
                                                            <option value="<?= InputUtils::escapeAttribute($value) ?>" <?= $currentChoice === $value ? 'selected' : '' ?>><?= InputUtils::escapeHTML($choiceLabel) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php else: ?>
                                                    <label class="form-check form-switch form-check-single m-0">
                                                        <input class="form-check-input feature-toggle" type="checkbox"
                                                            aria-label="<?= InputUtils::escapeAttribute($settingInfo['label']) ?>"
                                                            data-setting="<?= $settingKey ?>"
                                                            <?= $isEnabled ? 'checked' : '' ?>>
                                                    </label>
                                                <?php endif; ?>
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

<div class="offcanvas offcanvas-end" tabindex="-1" id="ministry-experience-help" aria-labelledby="ministry-experience-help-title">
  <div class="offcanvas-header">
    <h3 class="offcanvas-title" id="ministry-experience-help-title"><?= gettext('What the volunteer experiences are') ?></h3>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
  </div>
  <div class="offcanvas-body">
    <p class="mb-2">
      <?= gettext('ChurchCRM has two ways of managing volunteers. Only one is meant to be in use at a time; the Volunteer experience setting decides which one this church sees. Switching hides or shows pages and menu entries — it never deletes data, so you can switch back.') ?>
    </p>
    <h4 class="mt-3"><?= gettext('V1 — Volunteer Opportunities (legacy)') ?></h4>
    <p class="mb-2">
      <?= gettext('A single list of opportunities, such as "Nursery" or "Greeter", kept under People → Admin. Each person is tagged with the opportunities they are willing to help with, on the Volunteer tab of their record. That is all it records: there are no teams, no schedules, no assignments to a date, and no reminder emails. Reports and the person record are where the tags are used.') ?>
      <?= gettext('Choosing V1 pauses V2 volunteer email; anything queued is sent when V2 is back on, unless its occurrence is over.') ?>
    </p>
    <h4 class="mt-3"><?= gettext('V2 — Ministries') ?></h4>
    <p class="mb-2">
      <?= gettext('Volunteering organised the way a church runs it. Each ministry has teams and positions; people are qualified for positions and belong to the ministry\'s pool group; schedules follow the events on the calendar and produce dated occurrences with staffing requirements; coordinators assign people, who accept, decline or propose a substitute; reminders and alerts go out by email. Administrators and users with the Manage Ministries permission see every ministry and may create, deactivate and delete them. A ministry coordinator is given the Manage My Ministries permission instead: the Ministries heading in the sidebar then shows only the ministries they coordinate. A team leader runs their team from the Member Portal. Members use the Member Portal to see their schedule, respond, sign up and offer help.') ?>
    </p>
    <h4 class="mt-3"><?= gettext('Both') ?></h4>
    <p class="mb-0">
      <?= gettext('A transition setting. The legacy Volunteer Opportunities pages and the new Ministries pages are both available, and a person record shows both Volunteer tabs, clearly labelled. Use it while you move a church from V1 to V2, then switch to V2 only.') ?>
    </p>
  </div>
</div>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
