<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PredefinedReportsQuery;
use ChurchCRM\view\PageHeader;

$sPageTitle = gettext('Query Listing');
$sPageSubtitle = gettext('View and run saved database queries');

$queries = PredefinedReportsQuery::create()->orderByQryName()->find();

$aFinanceQueries = explode(',', $aFinanceQueries);

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Data & Reports')],
]);
require_once __DIR__ . '/Include/Header.php';

?>
<div class="card">
    <div class="list-group list-group-flush">
        <?php foreach ($queries as $query) :
            if (AuthenticationManager::getCurrentUser()->isFinanceEnabled() || !in_array($query->getQryId(), $aFinanceQueries)) : ?>
        <div class="list-group-item">
            <div class="row align-items-center">
                <div class="col">
                    <a href="QueryView.php?QueryID=<?= $query->getQryId() ?>" class="fw-bold text-body">
                        <?= gettext($query->getQryName()) ?>
                    </a>
                    <div class="text-secondary"><?= gettext($query->getQryDescription()) ?></div>
                </div>
                <div class="col-auto">
                    <a href="QueryView.php?QueryID=<?= $query->getQryId() ?>" class="btn btn-sm btn-outline-primary">
                        <?= gettext('Run') ?>
                    </a>
                </div>
            </div>
        </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
