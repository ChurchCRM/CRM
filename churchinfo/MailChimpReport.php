<?php
/*******************************************************************************
 *
 *  filename    : MailChimpReprot.php
 *  last change : 2014-11-29
 *  website     : http://www.churchdb.org
 *  copyright   : Copyright 2014
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';
require 'Include/MailchimpFunctions.php';

$mailchimp = new ChurchInfoMailchimp();

//Set the page title
$sPageTitle = gettext('MailChimp Menu');

require 'Include/Header.php';

if (!$mailchimp->isActive()) {
    echo "Mailchimp is not active";
} else {

    echo "<h3>Mailchimp Lists</h3>";

    $mcLists =  $mailchimp->getLists();

    foreach ($mcLists as $list) {

        echo "<h4><u>".$list["name"]."</u></h4>";
        ?>
        <table width="300px">

        <?php
        echo "<tr><td><b>Members:</b> </td><td>".$list["stats"]["member_count"]."</td></tr>";
        echo "<tr><td><b>Campaigns:</b> </td><td>".$list["stats"]["campaign_count"]."</td></tr>";
        echo "<tr><td><b>Unsubscribed count:</b> </td><td>".$list["stats"]["unsubscribe_count"]."</td></tr>";
        echo "<tr><td><b>Unsubscribed count since last send:</b> </td><td>".$list["stats"]["unsubscribe_count_since_send"]."</td></tr>";
        echo "<tr><td><b>Cleaned count:</b> </td><td>".$list["stats"]["cleaned_count"]."</td></tr>";
        echo "<tr><td><b>Cleaned count since last send:</b> </td><td>".$list["stats"]["cleaned_count_since_send"]."</td></tr>";
        echo "</tr>";
    }
    ?>
    </table>


    <h3>Reports</h3>

    <ul>
        <li><a href="Reports/MailChimpMissingReport.php">Missing emails report </a> (slow)</li>
        <li><a href="Reports/MailChimpCsvExport.php">Create email CSV </a> (to import into mailchimp)</li>
    </ul>


<?php
}
require "Include/Footer.php";
?>
