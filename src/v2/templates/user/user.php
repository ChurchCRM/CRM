<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext("Settings");
$sPageSubtitle = $user->getFullName();
$isOwnProfile = (AuthenticationManager::getCurrentUser()->getId() === $user->getId());
$personId = $user->getPersonId();
$person = $user->getPerson();
$photo = new Photo('Person', $personId);
$hasUploadedPhoto = $photo->hasUploadedPhoto();
$avatarApiUrl = SystemURLs::getRootPath() . '/api/person/' . $personId . '/photo';
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card">
  <div class="row g-0">
    <!-- Left nav tabs -->
    <div class="col-12 col-md-3 d-md-block border-end">
      <div class="card-body">
        <div class="list-group list-group-transparent" id="settingsNav">
          <a href="#tab-account" class="list-group-item list-group-item-action d-flex align-items-center active" data-bs-toggle="list">
            <i class="ti ti-user me-2"></i><?= gettext("My Account") ?>
          </a>
          <a href="#tab-appearance" class="list-group-item list-group-item-action d-flex align-items-center" data-bs-toggle="list">
            <i class="ti ti-palette me-2"></i><?= gettext("Appearance") ?>
          </a>
          <a href="#tab-localization" class="list-group-item list-group-item-action d-flex align-items-center" data-bs-toggle="list">
            <i class="ti ti-language me-2"></i><?= gettext("Localization") ?>
          </a>
          <a href="#tab-api" class="list-group-item list-group-item-action d-flex align-items-center" data-bs-toggle="list">
            <i class="ti ti-api me-2"></i><?= gettext("API Access") ?>
          </a>
          <a href="#tab-permissions" class="list-group-item list-group-item-action d-flex align-items-center" data-bs-toggle="list">
            <i class="ti ti-shield-lock me-2"></i><?= gettext("Permissions") ?>
          </a>
        </div>
      </div>
    </div>

    <!-- Right content panes -->
    <div class="col-12 col-md-9">
      <div class="card-body">
        <div class="tab-content">

          <!-- =============== MY ACCOUNT =============== -->
          <div class="tab-pane active show" id="tab-account">
            <h3 class="card-title"><?= gettext("My Account") ?></h3>
            <p class="text-muted"><?= gettext("Profile and security") ?></p>

            <!-- Profile -->
            <div class="row mb-4">
              <div class="col-sm-3 text-center">
                <?php if ($hasUploadedPhoto): ?>
                <img id="userAvatar" src="<?= $avatarApiUrl ?>" class="avatar avatar-xl rounded-circle mb-2" alt="<?= htmlspecialchars($user->getFullName()) ?>">
                <?php else: ?>
                <?php
                $nameParts = preg_split('/\s+/', trim($user->getFullName()));
                $initials = count($nameParts) >= 2
                    ? mb_strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr(end($nameParts), 0, 1))
                    : mb_strtoupper(mb_substr($nameParts[0] ?? '', 0, 2));
                ?>
                <span id="userAvatar" class="avatar avatar-xl rounded-circle mb-2" style="font-size: 1.5rem;"><?= htmlspecialchars($initials) ?></span>
                <?php endif; ?>
                <div>
                  <button id="uploadPhotoBtn" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-camera me-1"></i><?= gettext("Change Photo") ?>
                  </button>
                </div>
              </div>
              <div class="col-sm-9">
                <div class="row mb-2">
                  <div class="col-sm-4 text-muted"><?= gettext("Username") ?></div>
                  <div class="col-sm-8"><?= htmlspecialchars($user->getUserName()) ?></div>
                </div>
                <div class="row mb-2">
                  <div class="col-sm-4 text-muted"><?= gettext("Name") ?></div>
                  <div class="col-sm-8">
                    <?= htmlspecialchars($user->getFullName()) ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $personId ?>" class="ms-2 text-muted small"><i class="ti ti-pencil"></i></a>
                  </div>
                </div>
                <div class="row mb-2">
                  <div class="col-sm-4 text-muted"><?= gettext("Email") ?></div>
                  <div class="col-sm-8">
                    <?= htmlspecialchars($user->getEmail() ?? '') ?: '<span class="text-muted">' . gettext("Not set") . '</span>' ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $personId ?>" class="ms-2 text-muted small"><i class="ti ti-pencil"></i></a>
                  </div>
                </div>
              </div>
            </div>

            <hr>

            <h4 class="mb-3"><?= gettext("Security") ?></h4>
            <div class="row mb-2">
              <div class="col-sm-3 text-muted"><?= gettext("Two-Factor Authentication") ?></div>
              <div class="col-sm-9">
                <?php if ($user->is2FactorAuthEnabled()): ?>
                <span class="badge bg-success-lt text-success"><i class="ti ti-shield-check me-1"></i><?= gettext("Active") ?></span>
                <?php else: ?>
                <span class="badge bg-secondary-lt text-secondary"><i class="ti ti-shield-off me-1"></i><?= gettext("Inactive") ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="row mb-3 mt-3">
              <div class="col-sm-9 offset-sm-3">
                <a href="<?= SystemURLs::getRootPath() ?>/v2/user/current/changepassword" class="btn btn-outline-primary me-2">
                  <i class="ti ti-key me-1"></i><?= gettext("Change Password") ?>
                </a>
                <a href="<?= SystemURLs::getRootPath() ?>/v2/user/current/manage2fa" class="btn btn-outline-primary">
                  <i class="ti ti-shield me-1"></i><?= gettext("Manage 2FA") ?>
                </a>
              </div>
            </div>

            <hr>

            <div class="row mb-3">
              <div class="col-sm-9 offset-sm-3">
                <a id="editSettings" href="<?= SystemURLs::getRootPath() ?>/SettingsIndividual.php" class="btn btn-outline-secondary">
                  <i class="ti ti-settings me-1"></i><?= gettext("Advanced Settings") ?>
                </a>
                <small class="form-hint mt-1"><?= gettext("Manage additional preferences like email delimiters and display options") ?></small>
              </div>
            </div>
          </div>

          <!-- =============== APPEARANCE =============== -->
          <div class="tab-pane" id="tab-appearance">
            <h3 class="card-title"><?= gettext("Appearance") ?></h3>
            <p class="text-muted"><?= gettext("Customize the look and feel of the application") ?></p>

            <!-- Theme Mode -->
            <div class="row mb-4">
              <label class="col-sm-3 col-form-label"><?= gettext("Theme Mode") ?></label>
              <div class="col-sm-9">
                <div class="form-selectgroup">
                  <label class="form-selectgroup-item">
                    <input type="radio" name="themeMode" value="default" class="form-selectgroup-input" id="themeModeLight">
                    <span class="form-selectgroup-label">
                      <i class="ti ti-sun me-1"></i><?= gettext("Light") ?>
                    </span>
                  </label>
                  <label class="form-selectgroup-item">
                    <input type="radio" name="themeMode" value="dark" class="form-selectgroup-input" id="themeModeDark">
                    <span class="form-selectgroup-label">
                      <i class="ti ti-moon me-1"></i><?= gettext("Dark") ?>
                    </span>
                  </label>
                </div>
                <small class="form-hint"><?= gettext("Choose between light and dark color scheme") ?></small>
              </div>
            </div>

            <!-- Primary Color -->
            <div class="row mb-4">
              <label class="col-sm-3 col-form-label"><?= gettext("Primary Color") ?></label>
              <div class="col-sm-9">
                <div class="d-flex flex-wrap gap-2" id="primaryColorPicker">
                  <?php
                  $colors = [
                      '' => ['label' => 'Default', 'hex' => '#066fd1'],
                      'blue' => ['label' => 'Blue', 'hex' => '#066fd1'],
                      'azure' => ['label' => 'Azure', 'hex' => '#4299e1'],
                      'indigo' => ['label' => 'Indigo', 'hex' => '#4263eb'],
                      'purple' => ['label' => 'Purple', 'hex' => '#ae3ec9'],
                      'pink' => ['label' => 'Pink', 'hex' => '#d6336c'],
                      'red' => ['label' => 'Red', 'hex' => '#d63939'],
                      'orange' => ['label' => 'Orange', 'hex' => '#f76707'],
                      'yellow' => ['label' => 'Yellow', 'hex' => '#f59f00'],
                      'lime' => ['label' => 'Lime', 'hex' => '#74b816'],
                      'green' => ['label' => 'Green', 'hex' => '#2fb344'],
                      'teal' => ['label' => 'Teal', 'hex' => '#0ca678'],
                      'cyan' => ['label' => 'Cyan', 'hex' => '#17a2b8'],
                  ];
                  foreach ($colors as $value => $info):
                  ?>
                  <button type="button"
                          class="btn-color-swatch rounded-circle border"
                          data-color="<?= $value ?>"
                          style="width: 2rem; height: 2rem; background-color: <?= $info['hex'] ?>; cursor: pointer; position: relative;"
                          title="<?= gettext($info['label']) ?>">
                  </button>
                  <?php endforeach; ?>
                </div>
                <small class="form-hint"><?= gettext("Set the accent color used for buttons, links, and active elements") ?></small>
              </div>
            </div>

            <hr>

            <!-- Layout -->
            <h4 class="mb-3"><?= gettext("Layout") ?></h4>
            <div class="row mb-3">
              <label class="col-sm-3 col-form-label" for="boxedLayout"><?= gettext("Boxed Layout") ?></label>
              <div class="col-sm-9">
                <label class="form-check form-switch">
                  <input type="checkbox" class="form-check-input user-setting-checkbox" id="boxedLayout" data-layout="layout-boxed" data-css="body" data-setting-name="ui.boxed">
                  <span class="form-check-label"><?= gettext("Constrain the page width to a centered container") ?></span>
                </label>
              </div>
            </div>


            <hr>

            <!-- Tables -->
            <h4 class="mb-3"><?= gettext("Tables") ?></h4>
            <div class="row mb-3">
              <label class="col-sm-3 col-form-label" for="tablePageLength"><?= gettext("Rows per page") ?></label>
              <div class="col-sm-9">
                <select id="tablePageLength" class="form-select">
                  <option value="10">10</option>
                  <option value="25">25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="-1"><?= gettext("All") ?></option>
                </select>
                <small class="form-hint"><?= gettext("Default number of rows shown in data tables. Takes effect on next page load.") ?></small>
              </div>
            </div>
          </div>

          <!-- =============== LOCALIZATION =============== -->
          <div class="tab-pane" id="tab-localization">
            <h3 class="card-title"><?= gettext("Localization") ?></h3>
            <p class="text-muted"><?= gettext("Language and regional settings") ?></p>

            <div class="row mb-3">
              <label class="col-sm-3 col-form-label" for="user-locale-setting"><?= gettext("Language") ?></label>
              <div class="col-sm-9">
                <select id="user-locale-setting" class="form-select">
                </select>
                <small class="form-hint"><?= gettext("Override the system default locale") ?>: <strong><?= Bootstrapper::getCurrentLocale()->getSystemLocale() ?></strong></small>
              </div>
            </div>

            <?php if ($localeInfo->shouldShowTranslationPercentage()): ?>
            <div class="row mb-3">
              <label class="col-sm-3 col-form-label"><?= gettext("Translation Progress") ?></label>
              <div class="col-sm-9">
                <div class="progress mb-2" style="height: 1.25rem;">
                  <div class="progress-bar bg-<?= $localeInfo->getTranslationPercentage() >= 90 ? 'success' : ($localeInfo->getTranslationPercentage() >= 50 ? 'warning' : 'danger') ?>"
                       role="progressbar"
                       style="width: <?= $localeInfo->getTranslationPercentage() ?>%"
                       aria-valuenow="<?= $localeInfo->getTranslationPercentage() ?>"
                       aria-valuemin="0"
                       aria-valuemax="100">
                    <?= $localeInfo->getTranslationPercentage() ?>%
                  </div>
                </div>
                <small class="form-hint"><?= gettext("Percentage of strings translated for your current language") ?></small>
              </div>
            </div>
            <?php endif; ?>

            <hr>

            <h4 class="mb-3"><?= gettext("Help Improve Translations") ?></h4>
            <div class="alert alert-info">
              <div class="d-flex">
                <div><i class="ti ti-info-circle me-2 mt-1"></i></div>
                <div>
                  <h4 class="alert-title"><?= gettext("Missing or incorrect translations?") ?></h4>
                  <p class="mb-2"><?= gettext("ChurchCRM translations are managed by the community on POEditor. You can help by:") ?></p>
                  <ul class="mb-2">
                    <li><?= gettext("Fixing incorrect or awkward translations in your language") ?></li>
                    <li><?= gettext("Translating missing strings to improve coverage") ?></li>
                    <li><?= gettext("Suggesting better phrasing for existing translations") ?></li>
                  </ul>
                  <a href="https://poeditor.com/join/project?hash=RABdnDSqAt" target="_blank" class="btn btn-outline-info btn-sm">
                    <i class="ti ti-external-link me-1"></i><?= gettext("Join the Translation Project on POEditor") ?>
                  </a>
                </div>
              </div>
            </div>

            <div class="alert alert-warning">
              <div class="d-flex">
                <div><i class="ti ti-alert-triangle me-2 mt-1"></i></div>
                <div>
                  <h4 class="alert-title"><?= gettext("Found a bug with translations?") ?></h4>
                  <p class="mb-0"><?= gettext("If you notice garbled characters, HTML entities appearing as text, or translations that break page layout, please report the issue on GitHub so it can be fixed for everyone.") ?></p>
                </div>
              </div>
            </div>
          </div>

          <!-- =============== API ACCESS =============== -->
          <div class="tab-pane" id="tab-api">
            <h3 class="card-title"><?= gettext("API Access") ?></h3>
            <p class="text-muted"><?= gettext("Manage your API key for external integrations") ?></p>

            <div class="row mb-3">
              <label class="col-sm-3 col-form-label"><?= gettext("API Key") ?></label>
              <div class="col-sm-9">
                <div class="input-group">
                  <input id="apiKey" type="text" class="form-control font-monospace" value="<?= htmlspecialchars($user->getApiKey()) ?>" readonly>
                  <button id="regenApiKey" class="btn btn-warning" type="button" title="<?= gettext("Regenerate") ?>">
                    <i class="ti ti-refresh me-1"></i><?= gettext("Regenerate") ?>
                  </button>
                </div>
                <small class="form-hint"><?= gettext("Use this key to authenticate API requests. Regenerating will invalidate the current key.") ?></small>
              </div>
            </div>

            <hr>

            <h4 class="mb-3"><?= gettext("Usage") ?></h4>
            <p class="text-muted"><?= gettext("Include your API key in requests using the x-api-key header:") ?></p>
            <pre class="p-3 bg-light rounded"><code>curl -H "x-api-key: <?= htmlspecialchars(substr($user->getApiKey(), 0, 8)) ?>..." \
     <?= SystemURLs::getURL() ?>/api/person/1</code></pre>
          </div>

          <!-- =============== PERMISSIONS =============== -->
          <div class="tab-pane" id="tab-permissions">
            <h3 class="card-title"><?= gettext("Permissions") ?></h3>
            <p class="text-muted"><?= gettext("Your current access levels (read-only)") ?></p>

            <?php
            $permissions = [
                ['label' => gettext("Administrator"), 'granted' => $user->isAdmin()],
                ['label' => gettext("Add Records"), 'granted' => $user->isAdmin() || $user->isAddRecords()],
                ['label' => gettext("Edit Records"), 'granted' => $user->isAdmin() || $user->isEditRecords()],
                ['label' => gettext("Delete Records"), 'granted' => $user->isAdmin() || $user->isDeleteRecords()],
                ['label' => gettext("Manage Properties and Classifications"), 'granted' => $user->isAdmin() || $user->isMenuOptions()],
                ['label' => gettext("Manage Groups and Roles"), 'granted' => $user->isAdmin() || $user->isManageGroups()],
                ['label' => gettext("Manage Donations and Finance"), 'granted' => $user->isAdmin() || $user->isFinance()],
                ['label' => gettext("Manage Notes"), 'granted' => $user->isAdmin() || $user->isNotes()],
            ];
            foreach ($permissions as $perm):
            ?>
            <div class="row mb-2">
              <div class="col-sm-6"><?= $perm['label'] ?></div>
              <div class="col-sm-6">
                <?php if ($perm['granted']): ?>
                <span class="badge bg-success-lt text-success"><i class="ti ti-check me-1"></i><?= gettext("Yes") ?></span>
                <?php else: ?>
                <span class="badge bg-secondary-lt text-secondary"><i class="ti ti-x me-1"></i><?= gettext("No") ?></span>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

        </div><!-- /.tab-content -->
      </div>
    </div>
  </div>
</div>

<!-- Photo Uploader -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/photo-uploader.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/photo-uploader.min.js') ?>"></script>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.viewUserId = <?= $user->getId() ?>;
    window.CRM.viewPersonId = <?= $personId ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/user.js') ?>"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
