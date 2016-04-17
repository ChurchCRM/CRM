<?php
/*******************************************************************************
 *
 *  filename    : MailChimpDashboard.php
 *  last change : 2014-11-29
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2014
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';
require '../Service/MailchimpService.php';


$mailchimp = new MailChimpService();

//Set the page title
$sPageTitle = gettext('MailChimp Dashboard');

require '../Include/Header.php';

$isActive = $mailchimp->isActive();

if ($isActive) {
  $mcLists = $mailchimp->getLists();
  ?>
  <div class="row">
    <?php foreach ($mcLists as $list) { ?>
      <div class="col-lg-4 col-md-2 col-sm-2">
        <div class="box">
          <div class="box-header">
            <h3 class="box-title">List: <?= $list["name"] ?></h3>
          </div>
          <div class="box-body">
            <?
            echo "<table width='300px''>";
            echo "<tr><td><b>Members:</b> </td><td>" . $list["stats"]["member_count"] . "</td></tr>";
            echo "<tr><td><b>Campaigns:</b> </td><td>" . $list["stats"]["campaign_count"] . "</td></tr>";
            echo "<tr><td><b>Unsubscribed count:</b> </td><td>" . $list["stats"]["unsubscribe_count"] . "</td></tr>";
            echo "<tr><td><b>Unsubscribed count since last send:</b> </td><td>" . $list["stats"]["unsubscribe_count_since_send"] . "</td></tr>";
            echo "<tr><td><b>Cleaned count:</b> </td><td>" . $list["stats"]["cleaned_count"] . "</td></tr>";
            echo "<tr><td><b>Cleaned count since last send:</b> </td><td>" . $list["stats"]["cleaned_count_since_send"] . "</td></tr>";
            echo "</tr></table>";
            ?>
          </div>
        </div>
      </div>
    <?php } ?>
  </div>
  <div class="row">
    <div class="col-lg-4 col-md-2 col-sm-2">
      <div class="box">
        <div class="box-header">
          <h3 class="box-title">Generate Email Export</h3>
        </div>
        <div class="box-body">
          MailChimp offers several ways to add subscribers to your list.
          This will generate a subscribers CSV file to <a href="http://kb.mailchimp.com/lists/growth/import-subscribers-to-a-list" target="_blank">import.</a>

          <p class="text-center">
            <a class="btn btn-app" href="MailChimpCsvExport.php">
              <i class="fa fa-save"></i> Generate
            </a>
          </p>
        </div>
      </div>
    </div>
    <div class="col-lg-4 col-md-2 col-sm-2">
      <h3>Reports</h3>
      <ul>
        <li><a href="MailChimpMissingReport.php">Missing emails report </a> (slow)</li>
      </ul>
    </div>
  </div>
<?php } else { ?>
  <div class="row">
    <div class="col-lg-12 col-md-7 col-sm-3">
      <div class="box box-body">
        <div class="alert alert-danger alert-dismissible">
          <h4><i class="icon fa fa-ban"></i> MailChimp is not configured</h4>
          Please update the MailChimp API key in Setting-><a href="../SettingsGeneral.php">Edit General Settings</a>, then update mailChimpApiKey. For more info see our <a href="http://docs.churchcrm.io">MailChimp support docs.</a>
        </div>
      </div>
    </div>
  </div>
<?php }

require "../Include/Footer.php";
?>
