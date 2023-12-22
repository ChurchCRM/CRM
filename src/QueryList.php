<?php

/*******************************************************************************
 *
 *  filename    : QueryList.php
 *  last change : 2003-01-07
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2001, 2002 Deane Barker
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;

//Set the page title
$sPageTitle = gettext('Query Listing');

$sSQL = 'SELECT * FROM query_qry ORDER BY qry_Name';
$rsQueries = RunQuery($sSQL);

$aFinanceQueries = explode(',', $aFinanceQueries);

require 'Include/Header.php';

?>
<div class="card card-primary">
    <div class="card-body">
        <p class="text-right">
            <?php
            if (AuthenticationManager::getCurrentUser()->isAdmin()) {
                echo '<a href="QuerySQL.php" class="text-red">' . gettext('Run a Free-Text Query') . '</a>';
            }
            ?>
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

require 'Include/Footer.php';
