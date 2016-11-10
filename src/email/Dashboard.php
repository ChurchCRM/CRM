<?php
/*******************************************************************************
 *
 *  filename    : Dashboard.php
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
use ChurchCRM\Service\MailChimpService;


$mailchimp = new MailChimpService();

//Set the page title
$sPageTitle = gettext('eMail Dashboard');

require '../Include/Header.php';
?>
<div class="row">
  <div class="col-lg-4 col-md-2 col-sm-2">
    <div class="box">
      <div class="box-header">
        <h3 class="box-title"><?= gettext("Email Export") ?></h3>
      </div>
      <div class="box-body">
        <?= gettext("You can import the generated CSV file to external email system.
        For MailChimp see:") ?> <a href="http://kb.mailchimp.com/lists/growth/import-subscribers-to-a-list"
                                   target="_blank"><?= gettext("import subscribers to a list.") ?></a>
        <br/><br/>

        <p class="text-center">
          <a class="btn btn-app" href="MemberEmailExport.php">
            <i class="fa fa-file-o"></i> <?= gettext("Generate") ?>
          </a>
        </p>
      </div>
    </div>
  </div>
</div>

<?php if ($mailchimp->isActive()) {
  $mcLists = $mailchimp->getLists();
  ?>
  <div class="row">
    <?php foreach ($mcLists as $list) { ?>
      <div class="col-lg-4 col-md-2 col-sm-2">
        <div class="box">
          <div class="box-header">
            <h3 class="box-title"><?= gettext("List:") ?> <?= $list["name"] ?></h3>
          </div>
          <div class="box-body">
            <?
            echo "<table width='300px''>";
            echo "<tr><td><b>" . gettext('Members:') . "</b> </td><td>" . $list["stats"]["member_count"] . "</td></tr>";
            echo "<tr><td><b>" . gettext('Campaigns:') . "</b> </td><td>" . $list["stats"]["campaign_count"] . "</td></tr>";
            echo "<tr><td><b>" . gettext('Unsubscribed count:') . "</b> </td><td>" . $list["stats"]["unsubscribe_count"] . "</td></tr>";
            echo "<tr><td><b>" . gettext('Unsubscribed count since last send:') . "</b> </td><td>" . $list["stats"]["unsubscribe_count_since_send"] . "</td></tr>";
            echo "<tr><td><b>" . gettext('Cleaned count:') . "</b> </td><td>" . $list["stats"]["cleaned_count"] . "</td></tr>";
            echo "<tr><td><b>" . gettext('Cleaned count since last send:') . "</b> </td><td>" . $list["stats"]["cleaned_count_since_send"] . "</td></tr>";
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
          <h3 class="box-title">MailChimp</h3>
        </div>
        <div class="box-body">
          <ul>
            <li><a href="MailChimpMissingReport.php"><?= gettext("Missing emails report") ?> </a> (slow)</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
<?php } else { ?>
  <div class="row">
    <div class="col-lg-12 col-md-7 col-sm-3">
      <div class="box box-body">
        <div class="alert alert-danger alert-dismissible">
          <h4><i class="icon fa fa-ban"></i> MailChimp <?= gettext("is not configured") ?></h4>
          <?= gettext("Please update the") ?> MailChimp <?= gettext("API key in Setting->") ?><a href="../SystemSettings.php"><?= gettext("Edit General Settings") ?></a>,
          <?= gettext("then update") ?> mailChimpApiKey. <?= gettext("For more info see our ") ?><a href="http://docs.churchcrm.io"> MailChimp <?= gettext("support docs.") ?></a>
        </div>
      </div>
    </div>
  </div>
<?php }

require "../Include/Footer.php";
?>
