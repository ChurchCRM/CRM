<?php
/*******************************************************************************
 *
 *  filename    : /Include/QueryFunctions.php
 *  website     : http://www.churchcrm.io
 *
 *  Contributors:
 *  2017 Louis Bridgman
 *
 *
 *  Copyright 2017 Louis Bridgman
 *
 *
 *
 ******************************************************************************/
// This file contains functions specifically related to ORM-related queries
use ChurchCRM\ListOptionQuery;

// Get months of the year
$birthdayMonths = array(
"1" => gettext('January'),
"2" => gettext('February'),
"3" => gettext('March'),
"4" => gettext('April'),
"5" => gettext('May'),
"6" => gettext('June'),
"7" => gettext('July'),
"8" => gettext('August'),
"9" => gettext('September'),
"10" => gettext('October'),
"11" => gettext('November'),
"12" => gettext('December'),
);

//Get membership classes
$rsMembershipClasses = ListOptionQuery::create()->filterByID('1')->orderByOptionId()->find();
$memberClass = array(0);
foreach ($rsMembershipClasses as $Member) {
    $memberClass[$Member->getOptionSequence()] = $Member->getOptionName();
}

function QuerySelect($fieldname)
{
    $sizes = ['default', 6, 7, 8, 9, 10, 11, 12, 14, 16, 18];
    echo '<tr>';
    echo '<td class="LabelColumn"> '.gettext('Font Size').':</td>';
    echo '<td class="TextColumn">';
    echo "<select name=\"$fieldname\">";
    foreach ($sizes as $s) {
        $sel = '';
        if (array_key_exists($fieldname, $_COOKIE) && $_COOKIE[$fieldname] == $s) {
            $sel = ' selected';
        }
        echo '<option value="'.$s.'"'.$sel.'>'.gettext("$s").'</option>';
    }
    echo '</select>';
    echo '</td>';
    echo '</tr>';
}
