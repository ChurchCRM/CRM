<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\LocaleInfo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;

$sPageTitle = gettext("Settings");
$sPageSubtitle = $user->getFullName();
$isOwnProfile = (AuthenticationManager::getCurrentUser()->getId() === $user->getId());
// Use distinct variable names so Header.php's reassignment of $personId,
// $avatarApiUrl, $hasUploadedPhoto, and $photo (always reads the logged-in
// user's values) cannot clobber the viewed user's values. Same pattern as
// $viewedUserLocaleInfo used for the locale identity-leak fix.
$viewedPersonId = $user->getPersonId();
$firstName = $user->getPerson() ? $user->getPerson()->getFirstName() : '';
$accountLabel = $isOwnProfile
    ? gettext('My Account')
    : ($firstName !== '' ? sprintf(gettext("%s's Account"), $firstName) : gettext('Account'));
if (AuthenticationManager::getCurrentUser()->isAdmin()) {
    $sPageHeaderButtons = PageHeader::buttons([
        ['label' => gettext('User List'), 'url' => '/admin/system/users', 'icon' => 'fa-users'],
        ['label' => gettext('Edit'), 'url' => '/admin/system/users/' . $viewedPersonId . '/edit', 'icon' => 'fa-pencil'],
    ]);
}
// Use a distinct variable so Header.php's reassignment of $localeInfo
// (which always reads the logged-in user's locale) cannot clobber this.
$viewedUserLocaleInfo = new LocaleInfo(SystemConfig::getValue('sLanguage'), $user->getSetting('ui.locale'));

// Read user settings server-side so controls are pre-populated without JS API calls
$_userStyle = $user->getSettingValue('ui.style');
$_userPrimary = $user->getSettingValue('ui.theme.primary');
$_userTableSize = $user->getSettingValue('ui.table.size');

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card">
  <div class="row g-0">
    <!-- Left nav tabs -->
    <div class="col-12 col-md-3 d-md-block border-end">
      <div class="card-body">
        <div class="list-group list-group-transparent" id="settingsNav">
          <a href="#tab-account" class="list-group-item list-group-item-action d-flex align-items-center active" data-bs-toggle="list">
            <i class="ti ti-user me-2"></i><?= InputUtils::escapeHTML($accountLabel) ?>
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
            <h3 class="card-title"><?= InputUtils::escapeHTML($accountLabel) ?></h3>
            <p class="text-body-secondary"><?= gettext("Profile and security") ?></p>

            <?php
            $isLocked       = $user->isLocked();
            $mustChange     = $user->getNeedPasswordChange();
            $failedLogins   = $user->getFailedLogins();
            $maxFailedLogins = SystemConfig::getIntValue('iMaxFailedLogins');
            ?>
            <?php if ($isLocked): ?>
            <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
              <i class="ti ti-lock me-2 flex-shrink-0 fs-3"></i>
              <div>
                <strong><?= gettext('Account locked') ?></strong>
                <div class="small"><?= gettext('This account is locked because of too many failed login attempts.') ?></div>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($mustChange): ?>
            <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
              <i class="ti ti-key me-2 flex-shrink-0 fs-3"></i>
              <div>
                <strong><?= gettext('Password change required') ?></strong>
                <div class="small"><?= gettext('This user must set a new password the next time they sign in.') ?></div>
              </div>
            </div>
            <?php endif; ?>

            <!-- Profile -->
            <div class="row mb-4">
              <div class="col-sm-3 text-center">
                <img id="userAvatar"
                     data-image-entity-type="person"
                     data-image-entity-id="<?= $viewedPersonId ?>"
                     class="avatar avatar-xl rounded-circle mb-2"
                     alt="<?= InputUtils::escapeAttribute($user->getFullName()) ?>">
                <div>
                  <button id="uploadPhotoBtn" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-camera me-1"></i><?= gettext("Change Photo") ?>
                  </button>
                </div>
              </div>
              <div class="col-sm-9">
                <?php $canEditUserPerson = AuthenticationManager::getCurrentUser()->canEditPerson($viewedPersonId, $user->getPerson()?->getFamId() ?? 0); ?>
                <div class="row mb-2">
                  <div class="col-sm-4 text-body-secondary"><?= gettext("Username") ?></div>
                  <div class="col-sm-8"><?= InputUtils::escapeHTML($user->getUserName()) ?></div>
                </div>
                <div class="row mb-2">
                  <div class="col-sm-4 text-body-secondary"><?= gettext("Name") ?></div>
                  <div class="col-sm-8">
                    <?= InputUtils::escapeHTML($user->getFullName()) ?>
                    <?php if ($canEditUserPerson): ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?PersonID=<?= $viewedPersonId ?>" class="ms-2 text-body-secondary small" title="<?= gettext("Edit") ?>"><i class="ti ti-pencil"></i></a>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="row mb-2">
                  <div class="col-sm-4 text-body-secondary"><?= gettext("Email") ?></div>
                  <div class="col-sm-8">
                    <?= InputUtils::escapeHTML($user->getEmail() ?? '') ?: '<span class="text-body-secondary">' . gettext("Not set") . '</span>' ?>
                    <?php if ($canEditUserPerson): ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?PersonID=<?= $viewedPersonId ?>" class="ms-2 text-body-secondary small" title="<?= gettext("Edit") ?>"><i class="ti ti-pencil"></i></a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <hr>

            <h4 class="mb-3"><?= gettext("Security") ?></h4>
            <div class="row mb-2">
              <div class="col-sm-3 text-body-secondary"><?= gettext("Two-Factor Authentication") ?></div>
              <div class="col-sm-9">
                <?php if ($user->is2FactorAuthEnabled()): ?>
                <span class="badge bg-success-lt text-success"><i class="ti ti-shield-check me-1"></i><?= gettext("Active") ?></span>
                <?php else: ?>
                <span class="badge bg-secondary-lt text-secondary"><i class="ti ti-shield-off me-1"></i><?= gettext("Inactive") ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-sm-3 text-body-secondary"><?= gettext('Account status') ?></div>
              <div class="col-sm-9">
                <?php if ($isLocked): ?>
                <span class="badge bg-danger-lt text-danger"><i class="ti ti-lock me-1"></i><?= gettext('Locked') ?></span>
                <?php else: ?>
                <span class="badge bg-success-lt text-success"><i class="ti ti-lock-open me-1"></i><?= gettext('Active') ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-sm-3 text-body-secondary"><?= gettext('Password status') ?></div>
              <div class="col-sm-9">
                <?php if ($mustChange): ?>
                <span class="badge bg-warning-lt text-warning"><i class="ti ti-key me-1"></i><?= gettext('Change required') ?></span>
                <?php else: ?>
                <span class="badge bg-success-lt text-success"><i class="ti ti-check me-1"></i><?= gettext('OK') ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-sm-3 text-body-secondary"><?= gettext('Failed login attempts') ?></div>
              <div class="col-sm-9">
                <?php if ($failedLogins > 0): ?>
                <span class="badge <?= $isLocked ? 'bg-danger-lt text-danger' : 'bg-warning-lt text-warning' ?>"><?= InputUtils::escapeHTML($failedLogins) ?><?php if ($maxFailedLogins > 0): ?>&nbsp;/&nbsp;<?= InputUtils::escapeHTML($maxFailedLogins) ?><?php endif; ?></span>
                <?php else: ?>
                <span class="text-body-secondary"><?= gettext('None') ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-sm-3 text-body-secondary"><?= gettext('Last login') ?></div>
              <div class="col-sm-9"><?= InputUtils::escapeHTML($user->getLastLogin(SystemConfig::getValue('sDateTimeFormat')) ?: gettext('Never')) ?></div>
            </div>
            <?php if ($isOwnProfile): ?>
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
            <?php endif; ?>

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
            <p class="text-body-secondary"><?= gettext("Customize the look and feel of the application") ?></p>

            <!-- Theme Mode -->
            <div class="row mb-4">
              <label class="col-sm-3 col-form-label"><?= gettext("Theme Mode") ?></label>
              <div class="col-sm-9">
                <div class="form-selectgroup">
                  <label class="form-selectgroup-item">
                    <input type="radio" name="themeMode" value="default" class="form-selectgroup-input" id="themeModeLight"<?= $_userStyle !== 'dark' ? ' checked' : '' ?>>
                    <span class="form-selectgroup-label">
                      <i class="ti ti-sun me-1"></i><?= gettext("Light") ?>
                    </span>
                  </label>
                  <label class="form-selectgroup-item">
                    <input type="radio" name="themeMode" value="dark" class="form-selectgroup-input" id="themeModeDark"<?= $_userStyle === 'dark' ? ' checked' : '' ?>>
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
                      '' => ['label' => gettext('Default'), 'hex' => '#066fd1'],
                      'blue' => ['label' => gettext('Blue'), 'hex' => '#066fd1'],
                      'azure' => ['label' => gettext('Azure'), 'hex' => '#4299e1'],
                      'indigo' => ['label' => gettext('Indigo'), 'hex' => '#4263eb'],
                      'purple' => ['label' => gettext('Purple'), 'hex' => '#ae3ec9'],
                      'pink' => ['label' => gettext('Pink'), 'hex' => '#d6336c'],
                      'red' => ['label' => gettext('Red'), 'hex' => '#d63939'],
                      'orange' => ['label' => gettext('Orange'), 'hex' => '#f76707'],
                      'yellow' => ['label' => gettext('Yellow'), 'hex' => '#f59f00'],
                      'lime' => ['label' => gettext('Lime'), 'hex' => '#74b816'],
                      'green' => ['label' => gettext('Green'), 'hex' => '#2fb344'],
                      'teal' => ['label' => gettext('Teal'), 'hex' => '#0ca678'],
                      'cyan' => ['label' => gettext('Cyan'), 'hex' => '#17a2b8'],
                  ];
                  foreach ($colors as $value => $info):
                  ?>
                  <button type="button" class="btn-color-swatch rounded-circle<?= $value === $_userPrimary ? ' active' : '' ?>" data-color="<?= $value ?>" style="background-color: <?= $info['hex'] ?>;" title="<?= $info['label'] ?>" aria-label="<?= $info['label'] ?>"></button>
                  <?php endforeach; ?>
                </div>
                <small class="form-hint"><?= gettext("Set the accent color used for buttons, links, and active elements") ?></small>
              </div>
            </div>

            <hr>

            <!-- Tables -->
            <h4 class="mb-3"><?= gettext("Tables") ?></h4>
            <div class="row mb-3">
              <label class="col-sm-3 col-form-label" for="tablePageLength"><?= gettext("Rows per page") ?></label>
              <div class="col-sm-9">
                <?php $tableVal = $_userTableSize !== '' ? $_userTableSize : '10'; ?>
                <select id="tablePageLength" class="form-select">
                  <option value="10"<?= $tableVal === '10' ? ' selected' : '' ?>>10</option>
                  <option value="25"<?= $tableVal === '25' ? ' selected' : '' ?>>25</option>
                  <option value="50"<?= $tableVal === '50' ? ' selected' : '' ?>>50</option>
                  <option value="100"<?= $tableVal === '100' ? ' selected' : '' ?>>100</option>
                  <option value="-1"<?= $tableVal === '-1' ? ' selected' : '' ?>><?= gettext("All") ?></option>
                </select>
                <small class="form-hint"><?= gettext("Default number of rows shown in data tables. Takes effect on next page load.") ?></small>
              </div>
            </div>
          </div>

          <!-- =============== LOCALIZATION =============== -->
          <div class="tab-pane" id="tab-localization">
            <h3 class="card-title"><?= gettext("Localization") ?></h3>
            <p class="text-body-secondary"><?= gettext("Language and regional settings") ?></p>

            <div class="row mb-3">
              <label class="col-sm-3 col-form-label" for="user-locale-setting"><?= gettext("Language") ?></label>
              <div class="col-sm-9">
                <select id="user-locale-setting" class="form-select">
                </select>
                <small class="form-hint"><?= gettext("Override the system default locale") ?>: <strong><?= $viewedUserLocaleInfo->getSystemLocale() ?></strong></small>
              </div>
            </div>

            <?php if ($viewedUserLocaleInfo->shouldShowTranslationPercentage()): ?>
            <div class="row mb-3">
              <label class="col-sm-3 col-form-label"><?= gettext("Translation Progress") ?></label>
              <div class="col-sm-9">
                <div class="progress mb-2" style="height: 1.25rem;">
                  <div class="progress-bar bg-<?= $viewedUserLocaleInfo->getTranslationPercentage() >= 90 ? 'success' : ($viewedUserLocaleInfo->getTranslationPercentage() >= 50 ? 'warning' : 'danger') ?>"
                       role="progressbar"
                       style="width: <?= $viewedUserLocaleInfo->getTranslationPercentage() ?>%"
                       aria-valuenow="<?= $viewedUserLocaleInfo->getTranslationPercentage() ?>"
                       aria-valuemin="0"
                       aria-valuemax="100">
                    <?= $viewedUserLocaleInfo->getTranslationPercentage() ?>%
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
                  <p class="mb-2"><?= gettext("ChurchCRM translations are managed by the community on POEditor. You can help by") ?>:</p>
                  <ul class="mb-2">
                    <li><?= gettext("Fixing incorrect or awkward translations in your language") ?></li>
                    <li><?= gettext("Translating missing strings to improve coverage") ?></li>
                    <li><?= gettext("Suggesting better phrasing for existing translations") ?></li>
                  </ul>
                  <a href="https://poeditor.com/join/project?hash=RABdnDSqAt" target="_blank" rel="noopener noreferrer" class="btn btn-outline-info btn-sm">
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
            <p class="text-body-secondary"><?= gettext("Manage your API key for external integrations") ?></p>

            <div class="row mb-3">
              <label class="col-sm-3 col-form-label"><?= gettext("API Key") ?></label>
              <div class="col-sm-9">
                <div class="input-group">
                  <input id="apiKey" type="text" class="form-control font-monospace" value="<?= InputUtils::escapeAttribute($user->getApiKey()) ?>" readonly>
                  <button id="regenApiKey" class="btn btn-warning" type="button" title="<?= gettext("Regenerate") ?>">
                    <i class="ti ti-refresh me-1"></i><?= gettext("Regenerate") ?>
                  </button>
                </div>
                <small class="form-hint"><?= gettext("Use this key to authenticate API requests. Regenerating will invalidate the current key.") ?></small>
              </div>
            </div>

            <hr>

            <h4 class="mb-3"><?= gettext("Usage") ?></h4>
            <p class="text-body-secondary"><?= gettext("Include your API key in requests using the x-api-key header") ?>:</p>
            <pre class="p-3 bg-light rounded"><code>curl -H "x-api-key: <?= InputUtils::escapeHTML(substr($user->getApiKey(), 0, 8)) ?>..." \
     <?= SystemURLs::getURL() ?>/api/person/1</code></pre>
          </div>

          <!-- =============== PERMISSIONS =============== -->
          <div class="tab-pane" id="tab-permissions">
            <h3 class="card-title"><?= gettext("Permissions") ?></h3>
            <p class="text-body-secondary"><?= gettext("Your current access levels (read-only)") ?></p>

            <?php if ($user->isAdmin()): ?>
            <!-- Case 1: admin — full access, no individual rows -->
            <div class="text-center py-4">
              <div class="mb-3">
                <span class="badge bg-warning-lt text-warning px-3 py-2" style="font-size:1rem;">
                  <i class="ti ti-shield-check me-2"></i><?= gettext("Administrator") ?>
                </span>
              </div>
              <p class="text-body-secondary mb-0"><?= gettext("Administrators have full access to all features and data. Individual permissions do not apply.") ?></p>
            </div>
            <?php elseif ($user->isEditSelf()): ?>
            <!-- Case 2: self-service user — no individual permission rows -->
            <div class="text-center py-4">
              <div class="mb-3">
                <span class="badge bg-info-lt text-info px-3 py-2" style="font-size:1rem;">
                  <i class="ti ti-user-check me-2"></i><?= gettext("Self-service only") ?>
                </span>
              </div>
              <p class="text-body-secondary mb-0"><?= gettext("This user can only review and update their own family profile. Individual permissions do not apply.") ?></p>
              <?php if ($isOwnProfile): ?>
              <p class="text-body-secondary mt-2 mb-0 small"><?= gettext("Contact an administrator to change your permissions.") ?></p>
              <?php endif; ?>
            </div>
            <?php else: ?>
            <!-- Case 3: admin viewing another non-admin user — show full permissions -->

            <!-- People & Families group -->
            <div class="border rounded mb-3">
              <div class="px-3 py-2 border-bottom bg-light">
                <strong><i class="ti ti-users me-2"></i><?= gettext("People &amp; Families") ?></strong>
                <p class="text-body-secondary small mb-0 mt-1"><?= gettext("All users can view congregation members. This permission cannot be removed.") ?></p>
              </div>
              <div class="row align-items-center px-3 py-2">
                <div class="col-sm-6 text-body-secondary"><?= gettext("View") ?></div>
                <div class="col-sm-6 d-flex flex-wrap gap-1">
                  <span class="badge bg-success-lt text-success"><i class="ti ti-eye me-1"></i><?= gettext("View") ?></span>
                  <span class="badge bg-secondary-lt text-secondary"><i class="ti ti-lock me-1"></i><?= gettext("Always granted") ?></span>
                </div>
              </div>
              <?php foreach ([
                [gettext("Add"), $user->isAddRecords()],
                [gettext("Edit"), $user->isEditRecords()],
                [gettext("Delete"), $user->isDeleteRecords()],
                [gettext("Notes"), $user->isNotes()],
              ] as [$capLabel, $capGranted]): ?>
              <div class="row align-items-center border-top px-3 py-2">
                <div class="col-sm-6"><?= InputUtils::escapeHTML($capLabel) ?></div>
                <div class="col-sm-6">
                  <?php if ($capGranted): ?>
                  <span class="badge bg-success-lt text-success"><i class="ti ti-check me-1"></i><?= gettext("Yes") ?></span>
                  <?php else: ?>
                  <span class="badge bg-secondary-lt text-secondary"><i class="ti ti-x me-1"></i><?= gettext("No") ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>

            <hr>
            <?php
            $permissions = [
                ['label' => gettext("Manage Properties and Classifications"), 'granted' => $user->isMenuOptions()],
                ['label' => gettext("Manage Groups and Roles"), 'granted' => $user->isManageGroups()],
                ['label' => gettext("Manage Donations and Finance"), 'granted' => $user->isFinance()],
                ['label' => gettext("Manage Fundraisers"), 'granted' => $user->isManageFundraisers()],
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
            <?php endif; ?>
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
    window.CRM.viewPersonId = <?= $viewedPersonId ?>;
    window.CRM.viewIsOwnProfile = <?= $isOwnProfile ? 'true' : 'false' ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/user.js') ?>"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
