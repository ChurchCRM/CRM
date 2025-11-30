<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = "ChurchCRM - Sunday School Device Kiosk";
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");
?>

<div>
  <h1 id="noEvent"></h1>
</div>
<div id="event">
  <div class="container" id="eventDetails">
    <div class="col-md-6">
      <span id="eventTitle" ></span>
    </div>
    <div class="col-md-2">
      <span>Start Time</span>
      <span id="startTime"></span>
    </div>
    <div class="col-md-2">
      <span>End Time</span>
      <span id="endTime"></span>
    </div>
  </div>
  <div class="container" id="classMemberContainer"></div>
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/KioskJSOM.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/Kiosk.js"></script>
<?php
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
