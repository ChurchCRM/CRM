<?php
/*******************************************************************************
 *
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
require '../Service/PersonService.php';

$mailchimp = new MailChimpService();
$personService = new PersonService();

//Set the page title
$sPageTitle = gettext('People not in Mailchimp');

require '../Include/Header.php';

if (!$mailchimp->isActive()) {
  echo "Mailchimp is not active";
}

$sSQL = "SELECT per_FirstName, per_LastName, per_Email, per_id FROM person_per where per_Email != '' order by per_DateLastEdited desc";
$rsPeopleWithEmail = RunQuery($sSQL);

?>

<div class="row">
  <div class="col-lg-8 col-md-4 col-sm-4">
    <div class="box">
      <div class="box-header">
        <h3 class="box-title">Members</h3>
      </div>
      <div class="box-body table-responsive no-padding">
        <table class="table table-hover">
          <tr>
            <th></th>
            <th>Name</th>
            <th>Email</th>
          </tr>
          <?php
          while ($aRow = mysql_fetch_array($rsPeopleWithEmail)) {
            extract($aRow);
            $mailchimpList = $mailchimp->isEmailInMailChimp($per_Email);
            if ($mailchimpList == "") { ?>
              <tr>
                <td><img class="contacts-list-img" src="<?= $personService->getPhoto($per_id) ?>"></td>
                <td><a href='<?= $personService->getViewURI($per_id); ?>'><?= $per_FirstName . " " . $per_LastName ?></a></td>
                <td><?= $per_Email ?></td>
              </tr>
            <?php }
          }
          ?>
        </table>
      </div>
    </div>
  </div>
</div>

<?php

require "../Include/Footer.php";
?>
