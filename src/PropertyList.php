<?php

/*******************************************************************************
 *
 *  filename    : PropertyList.php
 *  last change : 2003-01-07
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2001, 2002 Deane Barker
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

//Get the type to display
$sType = InputUtils::legacyFilterInput($_GET['Type'], 'char', 1);

//Based on the type, set the TypeName
switch ($sType) {
    case 'p':
        $sTypeName = gettext('Person');
        break;

    case 'f':
        $sTypeName = gettext('Family');
        break;

    case 'g':
        $sTypeName = gettext('Group');
        break;

    default:
        RedirectUtils::redirect('v2/dashboard');
        break;
}

//Set the page title
$sPageTitle = $sTypeName . ' ' . gettext('Property List');

//Get the properties
$sSQL = "SELECT * FROM property_pro, propertytype_prt WHERE prt_ID = pro_prt_ID AND pro_Class = '" . $sType . "' ORDER BY prt_Name,pro_Name";
$rsProperties = RunQuery($sSQL);

require 'Include/Header.php'; ?>

<div class="card card-body">

<?php if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) {
    //Display the new property link
    echo "<p align=\"center\"><a class='btn btn-primary' href=\"PropertyEditor.php?Type=" . $sType . '">' . gettext('Add a New') . ' ' . $sTypeName . ' ' . gettext('Property') . '</a></p>';
}

//Start the table
echo "<table class='table'>";
echo '<tr>';
echo '<th valign="top">' . gettext('Name') . '</th>';
echo '<th valign="top">' . gettext('A') . ' ' . $sTypeName . ' ' . gettext('with this Property...') . '</b></th>';
echo '<th valign="top">' . gettext('Prompt') . '</th>';
if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) {
    echo '<td valign="top"><b>' . gettext('Edit') . '</b></td>';
    echo '<td valign="top"><b>' . gettext('Delete') . '</b></td>';
}
echo '</tr>';

echo '<tr><td>&nbsp;</td></tr>';

//Initialize the row shading
$sRowClass = 'RowColorA';
$iPreviousPropertyType = -1;
$sBlankLine = '';

//Loop through the records
while ($aRow = mysqli_fetch_array($rsProperties)) {
    $pro_Prompt = '';
    $pro_Description = '';
    extract($aRow);

    //Did the Type change?
    if ($iPreviousPropertyType != $prt_ID) {
        //Write the header row
        echo $sBlankLine;
        echo '<tr class="RowColorA"><td colspan="5"><b>' . $prt_Name . '</b></td></tr>';
        $sBlankLine = '<tr><td>&nbsp;</td></tr>';

        //Reset the row color
        $sRowClass = 'RowColorA';
    }

    $sRowClass = AlternateRowStyle($sRowClass);

    echo '<tr class="' . $sRowClass . '">';
    echo '<td valign="top">' . $pro_Name . '&nbsp;</td>';
    echo '<td valign="top">';
    if (strlen($pro_Description) > 0) {
        echo '...' . $pro_Description;
    }
    echo '&nbsp;</td>';
    echo '<td valign="top">' . $pro_Prompt . '&nbsp;</td>';
    if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) {
        echo "<td valign=\"top\"><a class='btn btn-primary' href=\"PropertyEditor.php?PropertyID=" . $pro_ID . '&Type=' . $sType . '">' . gettext('Edit') . '</a></td>';
        echo "<td valign=\"top\"><a class='btn btn-danger' href=\"PropertyDelete.php?PropertyID=" . $pro_ID . '&Type=' . $sType . '">' . gettext('Delete') . '</a></td>';
    }
    echo '</tr>';

    //Store the PropertyType
    $iPreviousPropertyType = $prt_ID;
}

//End the table
echo '</table></div>';

require 'Include/Footer.php';

?>
