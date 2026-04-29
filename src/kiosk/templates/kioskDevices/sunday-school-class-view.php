<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = 'ChurchCRM - Kiosk';
require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';
?>

<!-- Kiosk Status Container - shown when waiting for event or acceptance -->
<div id="noEvent" class="kiosk-status-container">
  <!-- Content populated by JavaScript -->
</div>

<!-- Event Display Container -->
<div id="event">
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
            <i class="fa-solid fa-tablet-screen-button me-1"></i>
            <span id="kioskName"></span>
            <span class="mx-2">|</span>
            <i class="fa-solid fa-users me-1"></i>
            <span class="kiosk-group-name"></span>
            <span class="mx-2">|</span>
            <i class="fa-solid fa-clock me-1"></i>
            <span id="startTime"></span> &mdash; <span id="endTime"></span>
            <span id="timeRemaining" class="badge bg-warning-lt text-warning ms-2 d-none"></span>
          </div>
        </div>
        <div class="d-flex flex-column align-items-end mt-2 mt-md-0">
          <div class="kiosk-stats mb-2">
            <div class="kiosk-stat checked-in">
              <i class="fa-solid fa-check me-1"></i>
              <span id="checkedInCount">0</span> <?= gettext('Here') ?>
            </div>
            <div class="kiosk-stat not-here">
              <i class="fa-solid fa-clock me-1"></i>
              <span id="notCheckedInCount">0</span> <?= gettext('Expected') ?>
            </div>
          </div>
          <!-- Check-in By toggle -->
          <div class="kiosk-checkin-by-toggle">
            <div class="form-check form-switch">
              <input type="checkbox" class="form-check-input" id="checkinByToggle">
              <label class="form-check-label text-white" for="checkinByToggle">
                <i class="fa-solid fa-user-check me-1"></i><?= gettext('Check-in By') ?>
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Two Column Layout with Birthday Sidebar -->
    <div class="row" id="classMemberContainer">
      <!-- Birthday Banner (Top, full width when has birthdays) -->
      <div class="col-12 mb-3" id="birthdayBannerContainer">
        <div class="kiosk-birthday-banner" id="birthdayBanner">
          <div class="birthday-banner-header">
            <i class="fa-solid fa-cake-candles me-2"></i><?= gettext('Upcoming Birthdays') ?>
            <span class="badge bg-light text-dark ms-2" id="birthdayCount">0</span>
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
              <span class="badge bg-warning text-dark ms-2" id="notCheckedInSectionCount">0</span>
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
              <span class="badge bg-green-lt text-green ms-2" id="checkedInSectionCount">0</span>
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
  <button type="button" class="kiosk-fab kiosk-fab-guest" id="registerGuestBtn" title="Register walk-in guest">
    <i class="fa-solid fa-user-plus"></i>
  </button>
  <button type="button" class="kiosk-fab kiosk-fab-alert" id="alertAllBtn" title="Send alert to all families">
    <i class="fa-solid fa-bullhorn"></i>
  </button>
  <button type="button" class="kiosk-fab kiosk-fab-checkout" id="checkoutAllBtn" title="Checkout all students">
    <i class="fa-solid fa-right-from-bracket"></i>
  </button>
</div>

<!-- Check-in By Modal -->
<div class="modal fade" id="checkinByModal" tabindex="-1" aria-labelledby="checkinByModalTitle" aria-modal="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="checkinByModalTitle"><?= gettext('Who is bringing them in?') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= gettext('Cancel') ?>"></button>
      </div>
      <div class="modal-body" id="checkinByModalBody">
        <div class="text-center py-4">
          <i class="fa-solid fa-spinner fa-spin fa-2x text-primary"></i>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="checkinBySkipBtn">
          <i class="fa-solid fa-forward me-1"></i><?= gettext('Skip') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Guest Registration Modal -->
<div class="modal fade" id="guestRegistrationModal" tabindex="-1" aria-labelledby="guestRegistrationModalTitle" aria-modal="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-orange text-white">
        <h5 class="modal-title" id="guestRegistrationModalTitle">
          <i class="fa-solid fa-user-plus me-2"></i><?= gettext('Register Walk-In Guest') ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= gettext('Cancel') ?>"></button>
      </div>
      <div class="modal-body">
        <div id="guestFormError" class="alert alert-danger" style="display:none;"></div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="guestFirstName"><?= gettext('First Name') ?> <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-lg" id="guestFirstName" autocomplete="given-name" placeholder="<?= gettext('First Name') ?>">
            <div class="invalid-feedback"><?= gettext('First name is required') ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="guestLastName"><?= gettext('Last Name') ?> <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-lg" id="guestLastName" autocomplete="family-name" placeholder="<?= gettext('Last Name') ?>">
            <div class="invalid-feedback"><?= gettext('Last name is required') ?></div>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="guestBirthYear"><?= gettext('Birth Year') ?></label>
            <input type="number" class="form-control" id="guestBirthYear" min="1900" max="<?= date('Y') ?>" placeholder="<?= date('Y') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="guestBirthMonth"><?= gettext('Birth Month') ?></label>
            <select class="form-select" id="guestBirthMonth">
              <option value=""><?= gettext('— Month —') ?></option>
              <option value="1"><?= gettext('January') ?></option>
              <option value="2"><?= gettext('February') ?></option>
              <option value="3"><?= gettext('March') ?></option>
              <option value="4"><?= gettext('April') ?></option>
              <option value="5"><?= gettext('May') ?></option>
              <option value="6"><?= gettext('June') ?></option>
              <option value="7"><?= gettext('July') ?></option>
              <option value="8"><?= gettext('August') ?></option>
              <option value="9"><?= gettext('September') ?></option>
              <option value="10"><?= gettext('October') ?></option>
              <option value="11"><?= gettext('November') ?></option>
              <option value="12"><?= gettext('December') ?></option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="guestBirthDay"><?= gettext('Birth Day') ?></label>
            <input type="number" class="form-control" id="guestBirthDay" min="1" max="31" placeholder="<?= gettext('Day') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="guestPhone"><?= gettext('Phone') ?></label>
            <input type="tel" class="form-control" id="guestPhone" autocomplete="tel" placeholder="<?= gettext('Phone number') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="guestEmail"><?= gettext('Email') ?></label>
            <input type="email" class="form-control" id="guestEmail" autocomplete="email" placeholder="<?= gettext('Email address') ?>">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fa-solid fa-xmark me-1"></i><?= gettext('Cancel') ?>
        </button>
        <button type="button" class="btn btn-orange" id="guestRegisterSubmitBtn">
          <i class="fa-solid fa-user-plus me-1"></i><?= gettext('Register & Check In') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/kiosk.min.js') ?>"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php';
