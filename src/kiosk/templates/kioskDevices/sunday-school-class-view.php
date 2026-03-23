<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle ="ChurchCRM - Sunday School Device Kiosk";
require(SystemURLs::getDocumentRoot() ."/Include/HeaderNotLoggedIn.php");
?>

<!-- Kiosk Status Container - shown when waiting for event or acceptance -->
<div id="noEvent" class="kiosk-status-container d-none">
  <!-- Content populated by JavaScript -->
</div>

<!-- Event Display Container -->
<div id="event" class="d-none">
  <div class="kiosk-container">
    <!-- Tablet Warning -->
    <div class="tablet-warning">
      <i class="fa-solid fa-tablet-screen-button me-2"></i>
      <strong><?= gettext('Tip') ?>:</strong> <?= gettext('This kiosk is best viewed on a tablet in landscape mode for optimal check-in experience.') ?>
    </div>
    
    <!-- Event Header -->
    <div class="kiosk-header">
      <div class="d-flex justify-content-between align-items-start flex-wrap">
        <div>
          <h1 id="eventTitle"></h1>
          <div class="kiosk-time-info">
            <i class="fa-solid fa-users me-1"></i>
            <span class="kiosk-group-name"></span>
            <span class="mx-2">|</span>
            <i class="fa-solid fa-clock me-1"></i>
            <span id="startTime"></span> &mdash; <span id="endTime"></span>
          </div>
        </div>
        <div class="kiosk-stats mt-2 mt-md-0">
          <div class="kiosk-stat checked-in">
            <i class="fa-solid fa-check me-1"></i>
            <span id="checkedInCount">0</span> <?= gettext('Here') ?>
          </div>
          <div class="kiosk-stat not-here">
            <i class="fa-solid fa-clock me-1"></i>
            <span id="notCheckedInCount">0</span> <?= gettext('Expected') ?>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Two Column Layout with Birthday Sidebar -->
    <div class="row" id="classMemberContainer">
      <!-- Birthday Banner (Top, full width when has birthdays) -->
      <div class="col-12 mb-3" id="birthdayBannerContainer">
        <div class="kiosk-birthday-banner d-none" id="birthdayBanner">
          <div class="birthday-banner-header">
            <i class="fa-solid fa-cake-candles me-2"></i><?= gettext('Upcoming Birthdays') ?>
            <span class="badge bg-light ms-2" id="birthdayCount">0</span>
          </div>
          <div class="birthday-banner-list" id="birthdayList">
            <!-- Birthday cards rendered here -->
          </div>
        </div>
      </div>
      
      <!-- Not Checked In Section (Primary focus - left side) -->
      <div class="col-lg-6 col-md-6 mb-3">
        <div class="kiosk-section">
          <div class="kiosk-section-header not-checked-in">
            <h4>
              <i class="fa-solid fa-clock me-2"></i><?= gettext('Waiting to Check In') ?>
              <span class="badge bg-warning ms-2" id="notCheckedInSectionCount">0</span>
            </h4>
          </div>
          <div class="kiosk-section-body" id="notCheckedInList">
            <div class="kiosk-empty">
              <i class="fa-solid fa-spinner fa-spin"></i>
              <p><?= gettext('Loading...') ?></p>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Checked In Section -->
      <div class="col-lg-6 col-md-6 mb-3">
        <div class="kiosk-section">
          <div class="kiosk-section-header checked-in">
            <h4 class="mb-0">
              <i class="fa-solid fa-circle-check me-2"></i><?= gettext('Checked In') ?>
              <span class="badge bg-success ms-2" id="checkedInSectionCount">0</span>
            </h4>
          </div>
          <div class="kiosk-section-body" id="checkedInList">
            <div class="kiosk-empty">
              <i class="fa-solid fa-user-clock"></i>
              <p><?= gettext('No one checked in yet') ?></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Floating Action Buttons (FABs) -->
<div class="kiosk-fab-container">
  <button type="button" class="kiosk-fab kiosk-fab-refresh" id="refreshBtn" title="Refresh member list">
    <i class="fa-solid fa-arrows-rotate"></i>
  </button>
  <button type="button" class="kiosk-fab kiosk-fab-alert d-none" id="alertAllBtn" title="Send alert to all families">
    <i class="fa-solid fa-bullhorn"></i>
  </button>
  <button type="button" class="kiosk-fab kiosk-fab-checkout d-none" id="checkoutAllBtn" title="Checkout all students">
    <i class="fa-solid fa-right-from-bracket"></i>
  </button>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/kiosk.min.js') ?>"></script>
<?php
require(SystemURLs::getDocumentRoot() ."/Include/FooterNotLoggedIn.php");
