<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;

$sPageTitle = gettext('Query Listing');

$sSQL = 'SELECT * FROM query_qry ORDER BY qry_Name';
$rsQueries = RunQuery($sSQL);

$aFinanceQueries = explode(',', $aFinanceQueries);

require_once 'Include/Header.php';

?>
<div class="card card-primary">
    <div class="card-body">
        <p class="text-right">
        </p>

        <ul>
            <?php while ($aRow = mysqli_fetch_array($rsQueries)) : ?>
            <li>
                <p>
                <?php
                    extract($aRow);

                    // Filter out finance-related queries if the user doesn't have finance permissions
                if (AuthenticationManager::getCurrentUser()->isFinanceEnabled() || !in_array($qry_ID, $aFinanceQueries)) {
                    // Display the query name and description
                    echo '<a href="QueryView.php?QueryID=' . $qry_ID . '">' . gettext($qry_Name) . '</a>:';
                    echo '<br>';
                    echo gettext($qry_Description);
                }
                ?>
                </p>
            </li>
            <?php endwhile; ?>
        </ul>
    </div>

</div>
<?php
require_once 'Include/Footer.php';
