<?php
/*******************************************************************************
 *
 *  filename    : MailChimpReprot.php
 *  last change : 2014-11-29
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2014
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';
require '../service/MailChimpService.php';

$mailchimp = new MailChimpService();

//Set the page title
$sPageTitle = gettext('MailChimp Menu');

require 'Include/Header.php';

if (!$mailchimp->isActive()) {
    echo "Mailchimp is not active";
}

// Get all the groups
$sSQL = "SELECT per_FirstName, per_LastName, per_Email, per_id FROM `stgeorge_churchinfo`.`person_per` where per_Email != '' order by per_DateLastEdited desc";
$rsPeopleWithEmail = RunQuery($sSQL);

?>

<h3>People not in Mailchimp</h3>

<table id="people_with_email" width="100%" data-toggle="table" class="table table-striped table-bordered tablesorter">
    <thead>
    <tr>
        <td>Name</td>
        <td>Email</td>
    </tr>
    </thead>
    <tbody>
    <?php
    while ($aRow = mysql_fetch_array($rsPeopleWithEmail)) {
        extract($aRow);
        $mailchimpList = $mailchimp->isEmailInMailChimp($per_Email);
        if ($mailchimpList == "") {
            echo "<tr>";
            echo "<td><a href='../PersonView.php?PersonID=" . $per_id . "'>" . $per_FirstName . " " . $per_LastName . "</a></td>";
            echo "<td>" . $per_Email . "</td>";
            echo "</tr>";
        }
    }
    ?>
    </tbody>
</table>

<script>
    $(function () {
        $("#people_with_email").tablesorter();
    });
</script>


<?php

require "Include/Footer.php";
?>
