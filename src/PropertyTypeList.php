<?php

/*******************************************************************************
 *
 *  filename    : PropertyTypeList.php
 *  last change : 2003-03-27
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2001, 2002 Deane Barker
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\RedirectUtils;

if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
    RedirectUtils::securityRedirect("Admin");
}

// Set the page title
$sPageTitle = gettext('Property Type List');

// Get the properties types
$sSQL = 'SELECT prt_ID, prt_Class, prt_Name, COUNT(pro_ID) AS Properties FROM propertytype_prt LEFT JOIN property_pro ON pro_prt_ID = prt_ID GROUP BY prt_ID, prt_Class, prt_Name';
$rsPropertyTypes = RunQuery($sSQL);

require 'Include/Header.php';
?>
<div class="card card-body">
    <div class="table-responsive">
<?php //Display the new property link
if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) {
    echo "<p align=\"center\"><a class='btn btn-primary' href=\"PropertyTypeEditor.php\">" . gettext('Add a New Property Type') . '</a></p>';
}

//Start the table
echo "<table class='table table-hover'>";
echo '<tr>';
echo '<th>' . gettext('Name') . '</th>';
echo '<th>' . gettext('Class') . '</th>';
echo '<th align="center">' . gettext('Properties') . '</th>';
if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) {
    echo '<th>' . gettext('Edit') . '</th>';
    echo '<th>' . gettext('Delete') . '</th>';
}
echo '</tr>';

//Initialize the row shading
$sRowClass = 'RowColorA';

//Loop through the records
while ($aRow = mysqli_fetch_array($rsPropertyTypes)) {
    extract($aRow);

    $sRowClass = AlternateRowStyle($sRowClass);

    echo '<tr class="' . $sRowClass . '">';
    echo '<td>' . $prt_Name . '</td>';
    echo '<td>';
    switch ($prt_Class) {
        case 'p':
            echo gettext('Person');
            break;
        case 'f':
            echo gettext('Family');
            break;
        case 'g':
            echo gettext('Group');
            break;
    }
    echo '<td align="center">' . $Properties . '</td>';
    if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) {
        echo "<td><a class='btn btn-info' href=\"PropertyTypeEditor.php?PropertyTypeID=" . $prt_ID . '">' . gettext('Edit') . '</a></td>';
        if ($Properties == 0) {
            echo "<td><a class='btn btn-danger' href=\"PropertyTypeDelete.php?PropertyTypeID=" . $prt_ID . '">' . gettext('Delete') . '</a></td>';
        } else {
            echo "<td><a class='btn btn-danger' href=\"PropertyTypeDelete.php?PropertyTypeID=" . $prt_ID . '&Warn">' . gettext('Delete') . '</a></td>';
        }
    }
    echo '</tr>';
}

//End the table
echo '</table></div></div>';

require 'Include/Footer.php';

?>
