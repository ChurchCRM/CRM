<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Portal\ThemeManager;
use ChurchCRM\Portal\ThemeValidator;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

/*
 * Admin → Member Portal (#9864, design §4).
 *
 * Variables from the route: $themes, $activeTheme, $activeThemeExists,
 * $developerMode, $showCalendar, $showVolunteer, $allowBirthdayEdit, $stats,
 * $portalCalendars.
 *
 * Every theme was validated server-side by the route, so each entry carries a
 * badge on first paint without a round trip. The page's behaviour lives in
 * webpack/admin-member-portal.ts.
 */

/** The badge for one validation summary: class, icon and label. */
$statusBadge = static function (string $status): array {
    return match ($status) {
        ThemeValidator::LEVEL_ERROR => ['bg-danger', 'fa-circle-xmark', gettext('Errors')],
        ThemeValidator::LEVEL_WARNING => ['bg-warning', 'fa-triangle-exclamation', gettext('Warnings')],
        default => ['bg-success', 'fa-circle-check', gettext('Valid')],
    };
};

/** A theme's label in the dropdown: the system theme is named as such. */
$themeLabel = static function (array $theme): string {
    return $theme['isDefault'] ? gettext('System default') : $theme['name'];
};

/**
 * What kind of calendar a Calendars-tab row is. A row of the `calendars` table
 * with an owning ministry is a ministry calendar; without one it is church-wide.
 */
$calendarKind = static function (array $portalCalendar): string {
    if ($portalCalendar['type'] !== 'calendar') {
        return gettext('System calendar');
    }

    return $portalCalendar['ministryId'] === null
        ? gettext('Church calendar')
        : gettext('Ministry calendar');
};

$themesDocUrl = 'https://github.com/ChurchCRM/CRM/blob/master/docs/portal-themes.md';
?>

<div class="container-fluid" id="memberPortalPage">

    <?php if (!$activeThemeExists): ?>
        <div class="alert alert-danger" id="memberPortalMissingTheme">
            <h4 class="alert-title">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= gettext('The active theme folder is missing') ?>
            </h4>
            <p class="mb-0">
                <?= sprintf(
                    gettext('The Member Portal is set to the theme "%s", which is no longer on the server. Members are seeing an error page until you pick a theme that exists.'),
                    InputUtils::escapeHTML($activeTheme)
                ) ?>
            </p>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs" role="tablist" id="memberPortalTabs">
        <li class="nav-item" role="presentation">
            <a class="nav-link active" id="portal-settings-tab" data-bs-toggle="tab" href="#portal-settings" role="tab">
                <i class="fa-solid fa-sliders me-2"></i><?= gettext('Settings') ?>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="portal-themes-tab" data-bs-toggle="tab" href="#portal-themes" role="tab">
                <i class="fa-solid fa-palette me-2"></i><?= gettext('Themes') ?>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="portal-statistics-tab" data-bs-toggle="tab" href="#portal-statistics" role="tab">
                <i class="fa-solid fa-chart-simple me-2"></i><?= gettext('Statistics') ?>
            </a>
        </li>
        <!-- Calendars (MP5, issue #9866): which calendars members see. -->
        <li class="nav-item" role="presentation" id="portal-calendars-tab-item">
            <a class="nav-link" id="portal-calendars-tab" data-bs-toggle="tab" href="#portal-calendars" role="tab">
                <i class="fa-solid fa-calendar-days me-2"></i><?= gettext('Calendars') ?>
            </a>
        </li>
    </ul>

    <div class="tab-content pt-3">

        <!-- ============================ Settings ============================ -->
        <div class="tab-pane fade show active" id="portal-settings" role="tabpanel">
            <div class="row">
                <div class="col-12 col-xl-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title mb-0">
                                <i class="fa-solid fa-palette me-2"></i><?= gettext('Theme') ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <label class="form-label" for="portalThemeSelect"><?= gettext('Portal theme') ?></label>
                            <div class="d-flex gap-2 align-items-start flex-wrap">
                                <select class="form-select w-auto flex-grow-1" id="portalThemeSelect">
                                    <?php foreach ($themes as $theme): ?>
                                        <?php [$badgeClass, $badgeIcon, $badgeLabel] = $statusBadge($theme['status']); ?>
                                        <option value="<?= InputUtils::escapeAttribute($theme['id']) ?>"
                                                data-status="<?= InputUtils::escapeAttribute($theme['status']) ?>"
                                                <?= $theme['id'] === $activeTheme ? 'selected' : '' ?>>
                                            <?= InputUtils::escapeHTML($themeLabel($theme)) ?> — <?= InputUtils::escapeHTML($badgeLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" id="portalThemeCheckButton">
                                    <i class="fa-solid fa-stethoscope me-2"></i><?= gettext('Check') ?>
                                </button>
                                <button type="button" class="btn btn-primary" id="portalThemeActivateButton">
                                    <i class="fa-solid fa-circle-check me-2"></i><?= gettext('Activate') ?>
                                </button>
                            </div>
                            <p class="form-hint mt-2">
                                <?= gettext('A theme is a folder your church uploads to Include/themes/ on the server. The system default ships with ChurchCRM.') ?>
                            </p>

                            <div class="mt-3">
                                <span class="me-2"><?= gettext('Last check') ?></span>
                                <?php
                                $activeEntry = null;
                                foreach ($themes as $theme) {
                                    if ($theme['id'] === $activeTheme) {
                                        $activeEntry = $theme;
                                        break;
                                    }
                                }
                                [$badgeClass, $badgeIcon, $badgeLabel] = $statusBadge($activeEntry['status'] ?? ThemeValidator::LEVEL_ERROR);
                                ?>
                                <span class="badge <?= $badgeClass ?>" id="portalThemeStatusBadge">
                                    <i class="fa-solid <?= $badgeIcon ?> me-1"></i><span class="badge-text"><?= InputUtils::escapeHTML($badgeLabel) ?></span>
                                </span>
                            </div>

                            <div id="portalThemeFindings" class="mt-3"></div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-6">
                    <!-- The Settings Panel component renders and saves these four
                         ConfigItems through POST /admin/api/system/config/{name}. -->
                    <div id="portalSettingsPanel"></div>
                </div>
            </div>
        </div>

        <!-- ============================= Themes ============================= -->
        <div class="tab-pane fade" id="portal-themes" role="tabpanel">
            <div class="alert alert-info" id="portalThemeDisclaimer">
                <i class="fa-solid fa-circle-info me-2"></i>
                <?= gettext('Themes are provided by your church, not by ChurchCRM, and are not verified by the ChurchCRM project.') ?>
                <a href="<?= InputUtils::escapeAttribute($themesDocUrl) ?>" target="_blank" rel="noopener" class="ms-1">
                    <?= gettext('Read the theme authoring guide') ?>
                </a>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table" id="portalThemesTable">
                        <thead>
                            <tr>
                                <th><?= gettext('Theme') ?></th>
                                <th><?= gettext('Folder') ?></th>
                                <th><?= gettext('Author') ?></th>
                                <th class="text-center"><?= gettext('Overridden templates') ?></th>
                                <th><?= gettext('Validation') ?></th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($themes as $theme): ?>
                                <?php [$badgeClass, $badgeIcon, $badgeLabel] = $statusBadge($theme['status']); ?>
                                <tr data-theme="<?= InputUtils::escapeAttribute($theme['id']) ?>">
                                    <td>
                                        <span class="fw-bold"><?= InputUtils::escapeHTML($themeLabel($theme)) ?></span>
                                        <?php if ($theme['id'] === $activeTheme): ?>
                                            <span class="badge bg-primary ms-2"><?= gettext('Active') ?></span>
                                        <?php endif; ?>
                                        <?php if ($theme['description'] !== ''): ?>
                                            <div class="text-secondary small"><?= InputUtils::escapeHTML($theme['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= InputUtils::escapeHTML($theme['id']) ?></code></td>
                                    <td><?= $theme['author'] !== '' ? InputUtils::escapeHTML($theme['author']) : '<span class="text-secondary">&mdash;</span>' ?></td>
                                    <td class="text-center"><?= (int) $theme['overriddenTemplates'] ?></td>
                                    <td>
                                        <span class="badge <?= $badgeClass ?>">
                                            <i class="fa-solid <?= $badgeIcon ?> me-1"></i><?= InputUtils::escapeHTML($badgeLabel) ?>
                                        </span>
                                        <?php if ($theme['findings'] !== []): ?>
                                            <button type="button" class="btn btn-sm btn-ghost-secondary portal-theme-findings-toggle"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#portalFindings-<?= InputUtils::escapeAttribute($theme['id']) ?>">
                                                <?= gettext('Findings') ?>
                                                <span class="badge bg-secondary ms-1"><?= count($theme['findings']) ?></span>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="w-1">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-ghost-secondary" type="button"
                                                    data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <button type="button" class="dropdown-item portal-theme-activate"
                                                        data-theme="<?= InputUtils::escapeAttribute($theme['id']) ?>"
                                                        <?= $theme['id'] === $activeTheme ? 'disabled' : '' ?>>
                                                    <i class="fa-solid fa-circle-check me-2"></i><?= gettext('Activate') ?>
                                                </button>
                                                <button type="button" class="dropdown-item portal-theme-check"
                                                        data-theme="<?= InputUtils::escapeAttribute($theme['id']) ?>">
                                                    <i class="fa-solid fa-stethoscope me-2"></i><?= gettext('Check') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php if ($theme['findings'] !== []): ?>
                                    <tr class="collapse" id="portalFindings-<?= InputUtils::escapeAttribute($theme['id']) ?>">
                                        <td colspan="6" class="bg-surface-secondary">
                                            <ul class="list-unstyled mb-0">
                                                <?php foreach ($theme['findings'] as $finding): ?>
                                                    <li class="py-1">
                                                        <span class="badge <?= $finding['level'] === ThemeValidator::LEVEL_ERROR ? 'bg-danger' : 'bg-warning' ?> me-2">
                                                            <?= $finding['level'] === ThemeValidator::LEVEL_ERROR ? gettext('Error') : gettext('Warning') ?>
                                                        </span>
                                                        <?php if ($finding['file'] !== ''): ?>
                                                            <code><?= InputUtils::escapeHTML($finding['file']) ?></code><?php if ($finding['line'] > 0): ?>
                                                                <span class="text-secondary"><?= sprintf(gettext('line %d'), (int) $finding['line']) ?></span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        <span><?= InputUtils::escapeHTML($finding['message']) ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- =========================== Statistics =========================== -->
        <div class="tab-pane fade" id="portal-statistics" role="tabpanel">
            <p class="text-secondary">
                <?= gettext('These numbers count self-service accounts only: people whose login can reach the Member Portal and nothing else.') ?>
            </p>

            <div class="row row-cards mb-3" id="portalStatCards">
                <?php
                $statCards = [
                    ['id' => 'activeNow', 'label' => gettext('Active now'), 'hint' => gettext('In the last 15 minutes'), 'icon' => 'fa-circle-dot', 'color' => 'bg-green'],
                    ['id' => 'signedIn24Hours', 'label' => gettext('Last 24 hours'), 'hint' => gettext('Signed in'), 'icon' => 'fa-clock', 'color' => 'bg-blue'],
                    ['id' => 'signedIn7Days', 'label' => gettext('Last 7 days'), 'hint' => gettext('Signed in'), 'icon' => 'fa-calendar-week', 'color' => 'bg-azure'],
                    ['id' => 'signedIn30Days', 'label' => gettext('Last 30 days'), 'hint' => gettext('Signed in'), 'icon' => 'fa-calendar-days', 'color' => 'bg-indigo'],
                    ['id' => 'totalAccounts', 'label' => gettext('Self-service accounts'), 'hint' => gettext('Total'), 'icon' => 'fa-users', 'color' => 'bg-secondary'],
                    ['id' => 'neverSignedIn', 'label' => gettext('Never signed in'), 'hint' => gettext('Accounts'), 'icon' => 'fa-user-slash', 'color' => 'bg-orange'],
                ];
                foreach ($statCards as $card): ?>
                    <div class="col-6 col-lg-4 col-xl-2">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <span class="<?= $card['color'] ?> text-white avatar rounded-circle">
                                            <i class="fa-solid <?= $card['icon'] ?>"></i>
                                        </span>
                                    </div>
                                    <div class="col">
                                        <div class="h2 mb-0" id="portalStat-<?= $card['id'] ?>"><?= (int) $stats[$card['id']] ?></div>
                                        <div class="fw-medium"><?= InputUtils::escapeHTML($card['label']) ?></div>
                                        <div class="text-secondary small"><?= InputUtils::escapeHTML($card['hint']) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fa-solid fa-right-to-bracket me-2"></i><?= gettext('Ten most recent sign-ins') ?>
                    </h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table" id="portalRecentSignInsTable">
                        <thead>
                            <tr>
                                <th><?= gettext('Member') ?></th>
                                <th><?= gettext('Login') ?></th>
                                <th><?= gettext('Last sign-in') ?></th>
                                <th><?= gettext('Last seen in the portal') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($stats['recentSignIns'] === []): ?>
                                <tr id="portalNoRecentSignIns">
                                    <td colspan="4" class="text-secondary">
                                        <?= gettext('No member has signed in to the Member Portal yet.') ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($stats['recentSignIns'] as $signIn): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= SystemURLs::getRootPath() ?>/people/view/<?= (int) $signIn['personId'] ?>">
                                                <?= InputUtils::escapeHTML($signIn['name']) ?>
                                            </a>
                                        </td>
                                        <td><?= InputUtils::escapeHTML($signIn['userName']) ?></td>
                                        <td><?= InputUtils::escapeHTML($signIn['lastLogin']) ?></td>
                                        <td>
                                            <?= $signIn['lastPortalActivity'] !== ''
                                                ? InputUtils::escapeHTML($signIn['lastPortalActivity'])
                                                : '<span class="text-secondary">&mdash;</span>' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================ Calendars =========================== -->
        <div class="tab-pane fade" id="portal-calendars" role="tabpanel">
            <p class="text-secondary" id="portalCalendarsLead">
                <?= gettext('Members see only the calendars switched on here. Birthdays and anniversaries show first names and last initials only.') ?>
            </p>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table" id="portalCalendarsTable">
                        <thead>
                            <tr>
                                <th><?= gettext('Calendar') ?></th>
                                <th><?= gettext('Kind') ?></th>
                                <th class="w-1"><?= gettext('Colour') ?></th>
                                <th class="w-1"><?= gettext('Show in Member Portal') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($portalCalendars as $portalCalendar): ?>
                                <?php
                                $rowKey = $portalCalendar['type'] . '-' . $portalCalendar['id'];
                                $kind = $calendarKind($portalCalendar);
                                ?>
                                <tr data-calendar-type="<?= InputUtils::escapeAttribute($portalCalendar['type']) ?>"
                                    data-calendar-id="<?= (int) $portalCalendar['id'] ?>">
                                    <td><span class="fw-bold"><?= InputUtils::escapeHTML($portalCalendar['name']) ?></span></td>
                                    <td class="text-secondary"><?= InputUtils::escapeHTML($kind) ?></td>
                                    <td>
                                        <span class="portal-calendar-swatch d-inline-block rounded border"
                                              style="inline-size: 1.25rem; block-size: 1.25rem; background-color: <?= InputUtils::escapeAttribute($portalCalendar['colors']['background']) ?>;"
                                              title="<?= InputUtils::escapeAttribute($portalCalendar['colors']['background']) ?>"
                                              aria-hidden="true"></span>
                                    </td>
                                    <td>
                                        <label class="form-check form-switch mb-0">
                                            <input class="form-check-input portal-calendar-switch" type="checkbox"
                                                   id="portalCalendarSwitch-<?= InputUtils::escapeAttribute($rowKey) ?>"
                                                   <?= $portalCalendar['visible'] ? 'checked' : '' ?>>
                                            <span class="visually-hidden">
                                                <?= sprintf(gettext('Show "%s" in the Member Portal'), InputUtils::escapeHTML($portalCalendar['name'])) ?>
                                            </span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($portalCalendars === []): ?>
                                <tr id="portalNoCalendars">
                                    <td colspan="4" class="text-secondary">
                                        <?= gettext('This installation has no calendars yet.') ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-primary" id="portalCalendarsSaveButton">
                        <i class="fa-solid fa-floppy-disk me-2"></i><?= gettext('Save calendars') ?>
                    </button>
                    <span class="text-secondary" id="portalCalendarsStatus" role="status"></span>
                </div>
            </div>
        </div>

    </div>
</div>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM = window.CRM || {};
    window.CRM.memberPortalAdmin = <?= InputUtils::jsonEncodeForScript([
        'activeTheme' => $activeTheme,
        'defaultThemeId' => ThemeManager::DEFAULT_THEME,
    ]) ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/admin-member-portal.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
