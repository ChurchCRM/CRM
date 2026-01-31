<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = "ChurchCRM - Sunday School Device Kiosk";
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");
?>

<style>
/* Kiosk-specific styles for tablet optimization */
.kiosk-container {
    min-height: 100vh;
    padding: 1rem;
}
.kiosk-header {
    background: linear-gradient(135deg, #3c8dbc 0%, #2a6a8a 100%);
    color: white;
    border-radius: 8px;
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
}
.kiosk-header h1 {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 600;
}
.kiosk-stats {
    display: flex;
    gap: 1rem;
    align-items: center;
}
.kiosk-stat {
    background: rgba(255,255,255,0.2);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
}
.kiosk-stat.checked-in {
    background: #28a745;
}
.kiosk-stat.not-here {
    background: #6c757d;
}
.kiosk-time-info {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 0.5rem;
}
.kiosk-section {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    height: calc(100vh - 220px);
    display: flex;
    flex-direction: column;
}
.kiosk-section.has-birthday-banner {
    height: calc(100vh - 320px);
}
.kiosk-section-header {
    padding: 1rem 1.25rem;
    border-bottom: 2px solid;
    flex-shrink: 0;
}
.kiosk-section-header.checked-in {
    background: #d4edda;
    border-color: #28a745;
    color: #155724;
}
.kiosk-section-header.not-checked-in {
    background: #fff3cd;
    border-color: #ffc107;
    color: #856404;
}
.kiosk-section-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}
.kiosk-section-body {
    flex: 1;
    overflow-y: auto;
    padding: 0.75rem;
}
.kiosk-member {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    margin-bottom: 0.5rem;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid transparent;
    transition: all 0.2s ease;
}
.kiosk-member:hover {
    background: #e9ecef;
}
.kiosk-member.checked-in {
    border-left-color: #28a745;
    background: #f0fff4;
}
.kiosk-member-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    margin-right: 1rem;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e9ecef;
    border: 2px solid #dee2e6;
    overflow: hidden;
}
.kiosk-member-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.kiosk-member-info {
    flex: 1;
    min-width: 0;
}
.kiosk-member-name {
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.125rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.kiosk-member-age {
    font-size: 0.85rem;
    color: #6c757d;
}
.kiosk-member-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}
.kiosk-btn {
    padding: 0.6rem 0.8rem;
    font-size: 1.1rem;
    font-weight: 500;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 44px;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.kiosk-btn-checkin {
    background: #28a745;
    color: white;
}
.kiosk-btn-checkin:hover {
    background: #218838;
}
.kiosk-btn-checkout {
    background: #ffc107;
    color: #212529;
}
.kiosk-btn-checkout:hover {
    background: #e0a800;
}
.kiosk-btn-alert {
    background: transparent;
    border: 1px solid #dc3545;
    color: #dc3545;
    padding: 0.5rem 0.75rem;
}
.kiosk-btn-alert:hover {
    background: #dc3545;
    color: white;
}
.kiosk-empty {
    text-align: center;
    padding: 2rem;
    color: #6c757d;
}
.kiosk-empty i {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
    display: block;
}
/* Birthday banner styles */
.kiosk-birthday-banner {
    background: linear-gradient(135deg, #fff5f8 0%, #ffe4ec 100%);
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(232, 62, 140, 0.2);
    border: 2px solid #e83e8c;
    overflow: hidden;
}
.birthday-banner-header {
    background: linear-gradient(135deg, #e83e8c 0%, #f06595 100%);
    color: white;
    padding: 0.75rem 1.25rem;
    font-weight: 600;
    font-size: 1.1rem;
}
.birthday-banner-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 1rem;
}
.birthday-card {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    background: white;
    border-radius: 8px;
    border-left: 4px solid #e83e8c;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    min-width: 200px;
    flex: 0 1 auto;
}
.birthday-card.today {
    border-left-color: #ffc107;
    background: #fffbeb;
    animation: pulse 2s infinite;
}
.birthday-card.upcoming {
    border-left-color: #28a745;
    background: #f0fff4;
}
.birthday-card.recent {
    border-left-color: #6c757d;
    background: #f8f9fa;
}
@keyframes pulse {
    0%, 100% { box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    50% { box-shadow: 0 0 15px rgba(255, 193, 7, 0.5); }
}
.birthday-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    margin-right: 0.75rem;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fce4ec;
    color: #e83e8c;
    font-size: 0.9rem;
    overflow: hidden;
}
.birthday-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.birthday-info {
    flex: 1;
    min-width: 0;
}
.birthday-name {
    font-weight: 600;
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.birthday-date {
    font-size: 0.75rem;
    color: #6c757d;
}
.birthday-date.today {
    color: #ffc107;
    font-weight: 600;
}
.birthday-age-badge {
    background: #e83e8c;
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    border-radius: 10px;
    white-space: nowrap;
}
.birthday-subheader {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding-bottom: 0.25rem;
    border-bottom: 1px solid #f0f0f0;
}
/* Tablet warning banner */
.tablet-warning {
    display: none;
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 6px;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    font-size: 0.9rem;
    color: #856404;
}
@media (max-width: 768px) {
    .tablet-warning {
        display: block;
    }
    .kiosk-section {
        height: auto;
        min-height: 300px;
        margin-bottom: 1rem;
    }
}
@media (min-width: 769px) and (max-aspect-ratio: 4/3) {
    .tablet-warning {
        display: none;
    }
}
</style>

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
          <div class="kiosk-section-header checked-in d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
              <i class="fas fa-check-circle mr-2"></i><?= gettext('Checked In') ?>
              <span class="badge badge-success ml-2" id="checkedInSectionCount">0</span>
            </h4>
            <button type="button" class="btn btn-sm btn-outline-dark" id="checkoutAllBtn" style="display: none;">
              <i class="fas fa-sign-out-alt mr-1"></i><?= gettext('Checkout All') ?>
            </button>
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

<script src="<?= SystemURLs::assetVersioned('/skin/js/KioskJSOM.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/Kiosk.js') ?>"></script>
<?php
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
