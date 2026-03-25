<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;

$sPageTitle = gettext('Query Listing');

$sSQL = 'SELECT * FROM query_qry ORDER BY qry_Name';
$rsQueries = RunQuery($sSQL);

$aFinanceQueries = explode(',', $aFinanceQueries);

require_once __DIR__ . '/Include/Header.php';

?>
<div class="card">
    <div class="list-group list-group-flush">
        <?php while ($aRow = mysqli_fetch_array($rsQueries)) :
            extract($aRow);
            if (AuthenticationManager::getCurrentUser()->isFinanceEnabled() || !in_array($qry_ID, $aFinanceQueries)) : ?>
        <div class="list-group-item">
            <div class="row align-items-center">
                <div class="col">
                    <a href="QueryView.php?QueryID=<?= $qry_ID ?>" class="fw-bold text-body">
                        <?= gettext($qry_Name) ?>
                    </a>
                    <div class="text-secondary"><?= gettext($qry_Description) ?></div>
                </div>
                <div class="col-auto">
                    <a href="QueryView.php?QueryID=<?= $qry_ID ?>" class="btn btn-sm btn-outline-primary">
                        <?= gettext('Run') ?>
                    </a>
                </div>
            </div>
        </div>
            <?php endif; ?>
        <?php endwhile; ?>
    </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
