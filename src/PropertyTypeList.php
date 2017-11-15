<?php
/*******************************************************************************
 *
 *  filename    : PropertyTypeList.php
 *  last change : 2003-03-27
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002 Deane Barker
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\PropertyQuery;
use ChurchCRM\PropertyTypeQuery;

// Set the page title
$sPageTitle = gettext('Property Type List');

$ormPropertyTypes = PropertyTypeQuery::Create()
    ->leftJoinProperty()
    ->groupByPrtId()
    ->groupByPrtClass()
    ->groupByPrtName()
    ->find();


require 'Include/Header.php';
?>
<div class="box box-body">
    <div class="table-responsive">
<?php //Display the new property link
if ($_SESSION['bMenuOptions']) {
    echo "<p align=\"center\"><a class='btn btn-primary' href=\"PropertyTypeEditor.php\">".gettext('Add a New Property Type').'</a></p>';
}

//Start the table
echo "<table class='table table-hover'>";
echo '<tr>';
echo '<th>'.gettext('Name').'</th>';
echo '<th>'.gettext('Class').'</th>';
echo '<th align="center">'.gettext('Properties').'</th>';
if ($_SESSION['bMenuOptions']) {
    echo '<th>'.gettext('Edit').'</th>';
    echo '<th>'.gettext('Delete').'</th>';
}
echo '</tr>';

//Initalize the row shading
$sRowClass = 'RowColorA';

//Loop through the records
foreach ($ormPropertyTypes as $ormPropertyType) {
    //extract($aRow);

    $sRowClass = AlternateRowStyle($sRowClass);

    echo '<tr class="'.$sRowClass.'">';
    echo '<td>'.$ormPropertyType->getPrtName().'</td>';
    echo '<td>';
    if ($ormPropertyType->getPrtName() == 'Menu') {
        echo gettext('Sunday School Sub Menu');
    } else {
        switch ($ormPropertyType->getPrtClass()) { case 'p': echo gettext('Person'); break; case 'f': echo gettext('Family'); break; case 'g': echo gettext('Group'); break;}
    }
        
    echo '<td align="center">'.$Properties.'</td>';
    
    $activLink = "";
    if ($ormPropertyType->getPrtName() == 'Menu') {
        $activLink = " disabled";
    }
    
    if ($_SESSION['bMenuOptions']) {
        echo "<td><a class='btn btn-info".$activLink."' href=\"PropertyTypeEditor.php?PropertyTypeID=".$ormPropertyType->getPrtId().'">'.gettext('Edit').'</a></td>';
        if ($Properties == 0) {
            echo "<td><a class='btn btn-danger".$activLink."' href=\"PropertyTypeDelete.php?PropertyTypeID=".$ormPropertyType->getPrtId().'">'.gettext('Delete').'</a></td>';
        } else {
            echo "<td><a class='btn btn-danger".$activLink."' href=\"PropertyTypeDelete.php?PropertyTypeID=".$ormPropertyType->getPrtId().'&Warn">'.gettext('Delete').'</a></td>';
        }
    }
    echo '</tr>';
}

//End the table
echo '</table></div></div>';

require 'Include/Footer.php';

?>
