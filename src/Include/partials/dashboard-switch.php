<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

$dashboardUser = AuthenticationManager::getCurrentUser();
$dashboardRoot = SystemURLs::getRootPath();
$dashboardTabs = ['people' => ['/v2/dashboard', gettext('People'), 'fa-users']];
$dashboardTabs['groups'] = ['/groups/dashboard', gettext('Groups'), 'fa-user-group'];
if (SystemConfig::getBooleanValue('bEnabledSundaySchool')) {
    $dashboardTabs['sundayschool'] = ['/groups/sundayschool', gettext('Sunday School'), 'fa-children'];
}
if ($dashboardUser->canViewEvents() && $dashboardUser::isEventsEnabled()) {
    $dashboardTabs['events'] = ['/event/dashboard', gettext('Events'), 'fa-calendar-days'];
}
if ($dashboardUser->isFinanceEnabled()) {
    $dashboardTabs['finance'] = ['/finance', gettext('Finance'), 'fa-circle-dollar-to-slot'];
}
if (count($dashboardTabs) < 2) {
    return;
}
?>
<ul class="nav nav-pills flex-nowrap overflow-auto mb-3" id="dashboardSwitch" aria-label="<?= gettext('Dashboard') ?>">
    <?php foreach ($dashboardTabs as $key => [$path, $label, $icon]) : ?>
        <li class="nav-item flex-shrink-0">
            <a class="nav-link<?= $dashboardSwitchActive === $key ? ' active' : '' ?>" href="<?= $dashboardRoot . $path ?>"<?= $dashboardSwitchActive === $key ? ' aria-current="page"' : '' ?>>
                <i class="fa-solid <?= $icon ?> me-2"></i><?= $label ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
