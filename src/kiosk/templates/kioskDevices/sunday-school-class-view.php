<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = "ChurchCRM - Sunday School Device Kiosk";
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");
?>

<!-- Kiosk Status Container - shown when waiting for event or acceptance -->
<div id="noEvent" class="kiosk-status-container" style="display: none;">
  <!-- Content populated by JavaScript -->
</div>

<!-- Event Display Container -->
<div id="event" style="display: none;">
  <div class="kiosk-container">
    <!-- Tablet Warning -->
    <div class="tablet-warning">
      <i class="fas fa-tablet-alt mr-2"></i>
      <strong><?= gettext('Tip') ?>:</strong> <?= gettext('This kiosk is best viewed on a tablet in landscape mode for optimal check-in experience.') ?>
    </div>
    
    <!-- Event Header -->
    <div class="kiosk-header">
      <div class="d-flex justify-content-between align-items-start flex-wrap">
        <div>
          <h1 id="eventTitle"></h1>
          <div class="kiosk-time-info">
            <i class="fas fa-users mr-1"></i>
            <span class="kiosk-group-name"></span>
            <span class="mx-2">|</span>
            <i class="fas fa-clock mr-1"></i>
            <span id="startTime"></span> &mdash; <span id="endTime"></span>
          </div>
        </div>
        <div class="kiosk-stats mt-2 mt-md-0">
          <div class="kiosk-stat checked-in">
            <i class="fas fa-check mr-1"></i>
            <span id="checkedInCount">0</span> <?= gettext('Here') ?>
          </div>
          <div class="kiosk-stat not-here">
            <i class="fas fa-clock mr-1"></i>
            <span id="notCheckedInCount">0</span> <?= gettext('Expected') ?>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Two Column Layout with Birthday Sidebar -->
    <div class="row" id="classMemberContainer">
      <!-- Birthday Banner (Top, full width when has birthdays) -->
      <div class="col-12 mb-3" id="birthdayBannerContainer">
        <div class="kiosk-birthday-banner" id="birthdayBanner" style="display: none;">
          <div class="birthday-banner-header">
            <i class="fas fa-birthday-cake mr-2"></i><?= gettext('Upcoming Birthdays') ?>
            <span class="badge badge-light ml-2" id="birthdayCount">0</span>
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
              <i class="fas fa-clock mr-2"></i><?= gettext('Waiting to Check In') ?>
              <span class="badge badge-warning ml-2" id="notCheckedInSectionCount">0</span>
            </h4>
          </div>
          <div class="kiosk-section-body" id="notCheckedInList">
            <div class="kiosk-empty">
              <i class="fas fa-spinner fa-spin"></i>
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
              <i class="fas fa-check-circle mr-2"></i><?= gettext('Checked In') ?>
              <span class="badge badge-success ml-2" id="checkedInSectionCount">0</span>
            </h4>
          </div>
          <div class="kiosk-section-body" id="checkedInList">
            <div class="kiosk-empty">
              <i class="fas fa-user-clock"></i>
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
  <button type="button" class="kiosk-fab kiosk-fab-refresh" id="refreshBtn" onclick="window.CRM.kiosk.updateActiveClassMembers();" title="Refresh member list">
    <i class="fas fa-sync-alt"></i>
  </button>
  <button type="button" class="kiosk-fab kiosk-fab-alert" id="alertAllBtn" onclick="window.CRM.kiosk.alertAll();" style="display: none;" title="Send alert to all families">
    <i class="fas fa-bullhorn"></i>
  </button>
  <button type="button" class="kiosk-fab kiosk-fab-checkout" id="checkoutAllBtn" onclick="window.CRM.kiosk.checkOutAll();" style="display: none;" title="Checkout all students">
    <i class="fas fa-sign-out-alt"></i>
  </button>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/kiosk.min.js') ?>"></script>
<?php
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
