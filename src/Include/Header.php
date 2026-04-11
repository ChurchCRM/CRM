<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\view\MenuRenderer;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Service\NotificationService;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;

$localeInfo = Bootstrapper::getCurrentLocale();

// Turn ON output buffering
ob_start();

require_once __DIR__ . '/Header-Security.php';

// Initialize plugin system for logged-in users
$pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
PluginManager::init($pluginsPath);

// Resolve theme attributes from user settings
$_themeUser = AuthenticationManager::getCurrentUser();
$_themeAttrs = '';
$_themeStyle = $_themeUser->getSettingValue('ui.style');
if ($_themeStyle === 'dark') {
    $_themeAttrs .= ' data-bs-theme="dark"';
}
$_themePrimary = $_themeUser->getSettingValue('ui.theme.primary');
if ($_themePrimary !== '') {
    $_themeAttrs .= ' data-bs-theme-primary="' . InputUtils::escapeAttribute($_themePrimary) . '"';
}
// Top level menu index counter
$MenuFirst = 1;
?>
<!DOCTYPE html>
<html<?= $localeInfo->isRTL() ? ' dir="rtl"' : '' ?><?= $_themeAttrs ?>>
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <?php require_once __DIR__ . '/Header-HTML-Scripts.php'; ?>
  <?= PluginManager::getPluginHeadContent() ?>
</head>

<body class="antialiased">
<div class="page">

  <!-- Issue Report Modal -->
  <div id="IssueReportModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
      <div class="modal-content" id="bugForm">
        <form name="issueReport">
          <input type="hidden" name="pageName" value="<?= $_SERVER['REQUEST_URI'] ?>"/>
          <div class="modal-header">
            <h5 class="modal-title"><i class="ti ti-bug me-2"></i><?= gettext('Report an Issue') ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info mb-3">
              <i class="ti ti-info-circle me-1"></i>
              <?= gettext('Clicking "Open GitHub Issue" will open a new tab with your system info pre-filled. No personally identifiable information will be included unless you add it.') ?>
            </div>
            <div class="mb-3">
              <label for="issueDescription" class="fw-bold"><?= gettext('Describe the issue') ?> <span class="text-muted fw-normal">(<?= gettext('optional') ?>)</span></label>
              <textarea id="issueDescription" class="form-control" rows="4" placeholder="<?= gettext('What went wrong? What did you expect to happen?') ?>"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
            <button type="button" class="btn btn-primary" id="submitIssue">
              <i class="ti ti-brand-github me-1"></i><?= gettext('Open GitHub Issue') ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- End Issue Report Modal -->

  <?php
  // Initialize window.CRM and body scripts
  $currentUser = AuthenticationManager::getCurrentUser();
  $tableSizeSetting = $currentUser->getSetting("ui.table.size");
  if (empty($tableSizeSetting)) {
      $tableSize = 10;
  } else {
      $tableSize = $tableSizeSetting->getValue();
  }
  ?>
  <script nonce="<?= SystemURLs::getCSPNonce() ?>">
      // Initialize window.CRM if not already created by webpack bundles
      if (!window.CRM) {
          window.CRM = {};
      }

      // Extend window.CRM with server-side configuration (preserving existing properties like notify)
      Object.assign(window.CRM, {
          root:"<?= SystemURLs::getRootPath() ?>",
          fullURL:"<?= SystemURLs::getURL() ?>",
          lang:"<?= $localeInfo->getLanguageCode() ?>",
          isRTL:<?= $localeInfo->isRTL() ? 'true' : 'false' ?>,
          userId:"<?= AuthenticationManager::getCurrentUser()->getId() ?>",
          userName:<?= json_encode(AuthenticationManager::getCurrentUser()->getPerson()?->getFullName() ?? '') ?>,
          version:"<?= $_SESSION['sSoftwareInstalledVersion'] ?? 'unknown' ?>",
          systemLocale:"<?= $localeInfo->getSystemLocale() ?>",
          locale:"<?= $localeInfo->getLocale() ?>",
          shortLocale:"<?= $localeInfo->getShortLocale() ?>",
          timeZone:<?= SystemConfig::getValueForJs('sTimeZone') ?>,
          maxUploadSize:"<?= SystemService::getMaxUploadFileSize(true) ?>",
          maxUploadSizeBytes:"<?= SystemService::getMaxUploadFileSize(false) ?>",
          datePickerformat:<?= SystemConfig::getValueForJs('sDatePickerPlaceHolder') ?>,
          churchWebSite:<?= SystemConfig::getValueForJs('sChurchWebSite') ?>,
          systemConfigs: {
            sDateTimeFormat:<?= DateTimeUtils::getDateTimeFormatForJs() ?>,
          },
          comm: {
            smtpConfigured: <?= json_encode(SystemConfig::hasValidMailServerSettings()) ?>,
            vonageEnabled: <?= json_encode(PluginManager::getPlugin('vonage')?->isConfigured() ?? false) ?>,
          },
          // Plugin configs from active plugins (via getClientConfig())
          plugins: <?= json_encode(PluginManager::getPluginsClientConfig(), JSON_FORCE_OBJECT) ?>,
          // Legacy: keep bEnableGravatarPhotos for backward compatibility with existing JS
          bEnableGravatarPhotos: <?= json_encode(PluginManager::getPluginsClientConfig()['gravatar']['enabled'] ?? false) ?>,
          plugin: {
              dataTable : {
"pageLength": <?= $tableSize ?>,
"lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100,"All"]],
"language": {
"url":"<?= SystemURLs::getRootPath() ?>/locale/vendor/datatables/<?= $localeInfo->getDataTables() ?>.json"
                  },
                  responsive: true,
                  layout: {
                      topStart: 'search',
                      topEnd: 'buttons',
                      bottomStart: 'pageLength',
                      bottomEnd: ['info', 'paging']
                  },
                  buttons: [
                      {
                          extend: 'csv',
                          text: '<i class="ti ti-table-export"></i>',
                          titleAttr: 'Export CSV',
                          exportOptions: {
                              columns: ':not(.no-export)'
                          }
                      },
                      {
                          extend: 'print',
                          text: '<i class="ti ti-printer"></i>',
                          titleAttr: 'Print',
                          exportOptions: {
                              columns: ':not(.no-export)'
                          }
                      }
                  ]
              }
          },
          PageName:"<?= $_SERVER['REQUEST_URI']; ?>"
      });
      // Initialize moment locale if available
      if (typeof moment !== 'undefined' && window.CRM.shortLocale) {
          moment.locale(window.CRM.shortLocale);
      }
  </script>
  <script src="<?= SystemURLs::assetVersioned('/skin/js/CRMJSOM.js') ?>"></script>
  <script src="<?= SystemURLs::assetVersioned('/skin/js/CommunicationUtils.js') ?>"></script>

  <!-- ============================================================ -->
  <!-- Sidebar (Tabler vertical navbar)                              -->
  <!-- ============================================================ -->
  <aside class="navbar navbar-vertical navbar-expand-xl d-print-none" id="sidebar">
    <div class="container-fluid">
      <button class="navbar-toggler" type="button"
              data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
              aria-controls="sidebar-menu" aria-expanded="false"
              aria-label="<?= gettext('Toggle navigation') ?>">
        <span class="navbar-toggler-icon"></span>
      </button>
      <a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard" class="navbar-brand py-2">
        <img src="<?= SystemURLs::getRootPath() ?>/Images/CRM_50x50.png"
             alt="<?= InputUtils::escapeAttribute(ChurchMetaData::getChurchName() ?: 'ChurchCRM') ?>"
             class="navbar-brand-image rounded"
             style="height: 42px; width: auto;">
        <span class="navbar-brand-text ps-2 fs-4 fw-bold">
          <?= ChurchMetaData::getChurchName() ?: 'ChurchCRM' ?>
        </span>
      </a>
      <div class="collapse navbar-collapse" id="sidebar-menu">
        <ul class="navbar-nav pt-xl-3">
          <?php MenuRenderer::renderMenu(); ?>
        </ul>
      </div>
    </div>
  </aside>

  <!-- ============================================================ -->
  <!-- Page wrapper                                                  -->
  <!-- ============================================================ -->
  <div class="page-wrapper">

  <!-- ============================================================ -->
  <!-- Topbar                                                        -->
  <!-- ============================================================ -->
  <header class="navbar navbar-expand-md d-print-none sticky-top">
    <div class="container-xl">

      <button class="navbar-toggler" type="button"
              data-bs-toggle="collapse" data-bs-target="#navbar-menu"
              aria-controls="navbar-menu" aria-expanded="false"
              aria-label="<?= gettext('Toggle search') ?>">
        <i class="ti ti-search"></i>
      </button>

      <!-- Right-side nav items -->
      <div class="navbar-nav flex-row order-md-last ms-auto">

        <!-- System Update Notification -->
        <?php
        $showUpdateMenu = isset($_SESSION['systemUpdateAvailable']) && $_SESSION['systemUpdateAvailable'] === true;
        $updateVersion  = $_SESSION['systemUpdateVersion'] ?? null;
        ?>
        <div class="nav-item dropdown <?= $showUpdateMenu ? '' : 'd-none' ?>" id="systemUpdateMenuItem">
          <a class="nav-link px-0" data-bs-toggle="dropdown" href="#"
             id="upgradeMenu" title="<?= gettext('New Release') ?>">
            <i class="ti ti-download"></i>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <?php if (AuthenticationManager::getCurrentUser()->isAdmin()) { ?>
            <a href="<?= SystemURLs::getRootPath() ?>/admin/system/upgrade" class="dropdown-item"
               title="<?= gettext('New Release') ?>">
              <i class="ti ti-confetti me-2"></i><?= gettext('New Release') ?>
              <?php if ($updateVersion) { ?>
                <span id="upgradeToVersion" class="ms-1">
                  <?= $updateVersion->MAJOR ?>.<?= $updateVersion->MINOR ?>.<?= $updateVersion->PATCH ?>
                </span>
              <?php } ?>
            </a>
            <?php } ?>
            <a href="https://github.com/ChurchCRM/CRM/releases/latest" target="_blank"
               class="dropdown-item" title="<?= gettext('Release Notes') ?>">
              <i class="ti ti-book me-2"></i><?= gettext('Release Notes') ?>
            </a>
          </div>
        </div>

        <!-- Locale -->
        <div class="nav-item dropdown ms-1">
          <a class="nav-link px-0" data-bs-toggle="dropdown" href="#">
            <i class="fi fi-<?= $localeInfo->getCountryFlagCode() ?> fi-squared"></i>
            <?php if ($localeInfo->shouldShowTranslationBadge()) { ?>
            <span class="badge bg-warning text-dark ms-1" title="<?= gettext('Translation incomplete') ?>">!</span>
            <?php } ?>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <span class="dropdown-item disabled">
              <i class="fi fi-<?= $localeInfo->getCountryFlagCode() ?> me-2"></i>
              <?= $localeInfo->getName() ?> [<?= $localeInfo->getLocale() ?>]
              <?php if ($localeInfo->shouldShowTranslationPercentage()) { ?>
              <span class="badge bg-<?= $localeInfo->getTranslationPercentage() < 90 ? 'warning' : 'success' ?> ms-1">
                <?= $localeInfo->getTranslationPercentage() ?>%
              </span>
              <?php } ?>
            </span>
            <div class="dropdown-divider"></div>
            <a href="https://poeditor.com/join/project?hash=RABdnDSqAt" class="dropdown-item" target="_blank">
              <i class="ti ti-users me-2"></i><?= gettext("Help translate this project") ?>
            </a>
          </div>
        </div>

        <!-- Cart -->
        <div class="nav-item dropdown ms-1">
          <a class="nav-link px-0 position-relative" data-bs-toggle="dropdown" href="#">
            <i class="fa-duotone fa-solid fa-cart-shopping"></i>
            <?php if (Cart::countPeople() > 0): ?>
            <span class="badge bg-info position-absolute top-0 end-0 small" id="iconCount"><?= Cart::countPeople() ?></span>
            <?php else: ?>
            <span class="badge bg-info position-absolute top-0 end-0 small d-none" id="iconCount">0</span>
            <?php endif; ?>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <span id="cart-dropdown-menu"></span>
          </div>
        </div>

        <!-- Support -->
        <div class="nav-item dropdown ms-1">
          <a class="nav-link px-0" data-bs-toggle="dropdown" href="#" id="supportMenu">
            <i class="ti ti-headset"></i>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <a href="<?= SystemURLs::getSupportURL() ?>" target="help" class="dropdown-item"
               title="<?= gettext('Documentation') ?>">
              <i class="ti ti-book me-2"></i><?= gettext('Documentation') ?>
            </a>
            <div class="dropdown-divider"></div>
            <a href="#" id="reportIssue" class="dropdown-item"
               data-bs-toggle="modal" data-bs-target="#IssueReportModal"
               title="<?= gettext('Report an issue') ?>">
              <i class="ti ti-bug me-2"></i><?= gettext('Report an issue') ?>
            </a>
            <a href="https://discord.gg/tuWyFzj3Nj" target="_blank" class="dropdown-item"
               title="<?= gettext('Discord Chat') ?>">
              <i class="fa-brands fa-discord me-2"></i><?= gettext('Discord Chat') ?>
            </a>
            <div class="dropdown-divider"></div>
            <a href="https://docs.churchcrm.io/contributing" target="_blank" class="dropdown-item"
               title="<?= gettext('Contributing') ?>">
              <i class="fa-brands fa-github me-2"></i><?= gettext('Documentation') ?>
            </a>
          </div>
        </div>

        <!-- User -->
        <?php
        $currentUser     = AuthenticationManager::getCurrentUser();
        $currentUserName = $currentUser->getName();
        $userRole        = $currentUser->isAdmin() ? gettext('Administrator') : gettext('Member');

        // Generate server-side SVG placeholder (data URI) so the avatar shows
        // immediately on page load while the client-side avatar loader runs.
        $nameParts = preg_split('/\s+/', trim($currentUserName));
        if (empty($nameParts) || $nameParts[0] === '') {
          $userInitials = '';
        } elseif (count($nameParts) === 1) {
          $userInitials = mb_strtoupper(mb_substr($nameParts[0], 0, 2));
        } else {
          $userInitials = mb_strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[count($nameParts) - 1], 0, 1));
        }

        $avatarColors = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#00f2fe', '#43e97b', '#fa709a', '#fee140'];
        $colorIndex = crc32($currentUserName) % count($avatarColors);
        $avatarColor = $avatarColors[$colorIndex];

        $photo = new Photo('person', $currentUser->getPersonId());
        $hasUploadedPhoto = $photo->hasUploadedPhoto();
        $personId = $currentUser->getPersonId();
        $avatarApiUrl = SystemURLs::getRootPath() . '/api/person/' . $personId . '/photo';
        ?>
        <div class="nav-item dropdown">
            <a href="#" class="nav-link d-flex align-items-center gap-2 lh-1 text-reset ps-2"
             data-bs-toggle="dropdown" aria-label="<?= gettext('Open user menu') ?>">
            <?php if ($hasUploadedPhoto) { ?>
              <img src="<?= $avatarApiUrl ?>" class="avatar photo-small rounded-circle" alt="<?= htmlspecialchars($currentUserName) ?>">
            <?php } else { ?>
              <span class="avatar avatar-sm" style="background-color: <?= $avatarColor ?>; color: #fff; flex-shrink: 0;">
                <span class="avatar-title"><?= htmlspecialchars($userInitials) ?></span>
              </span>
            <?php } ?>
            <div class="d-none d-xl-block ps-2">
              <div><?= htmlspecialchars($currentUserName) ?></div>
              <div class="mt-1 small text-secondary"><?= $userRole ?></div>
            </div>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <a href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $currentUser->getPersonId() ?>"
               class="dropdown-item">
              <i class="ti ti-user me-2"></i><?= gettext("Profile") ?>
            </a>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/user/current/changepassword" class="dropdown-item">
              <i class="ti ti-key me-2"></i><?= gettext('Change Password') ?>
            </a>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= $currentUser->getPersonId() ?>"
               class="dropdown-item">
              <i class="ti ti-settings me-2"></i><?= gettext('Change Settings') ?>
            </a>
            <div class="dropdown-divider"></div>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/user/current/manage2fa" class="dropdown-item">
              <i class="ti ti-shield me-2"></i><?= gettext("Manage Two-Factor Authentication") ?>
            </a>
            <div class="dropdown-divider"></div>
            <a href="<?= SystemURLs::getRootPath() ?>/session/end" class="dropdown-item">
              <i class="ti ti-logout me-2"></i><?= gettext('Sign out') ?>
            </a>
          </div>
        </div>

      </div><!-- /.navbar-nav.order-md-last -->

      <!-- Search -->
      <div class="collapse navbar-collapse" id="navbar-menu">
        <div style="position: relative; width: min(480px, 100%);">
          <div class="input-icon">
            <span class="input-icon-addon">
              <i class="ti ti-search"></i>
            </span>
            <input type="search" id="globalSearch" class="form-control"
                   placeholder="<?= gettext('Search people, families, groups…') ?>"
                   autocomplete="off" spellcheck="false">
            <span class="input-icon-addon">
              <kbd title="<?= gettext('Press ? to focus search') ?>">?</kbd>
            </span>
          </div>
          <div id="globalSearchDropdown" class="dropdown-menu w-100"
               style="top: calc(100% + 2px); left: 0; position: absolute;"></div>
        </div>
      </div>

    </div><!-- /.container-xl -->
  </header>

<?php
    // Unified page header defaults (backward-compatible)
    $sPageSubtitle = $sPageSubtitle ?? '';
    $aBreadcrumbs = $aBreadcrumbs ?? [];
    $sPageHeaderButtons = $sPageHeaderButtons ?? '';
    $sSettingsCollapseId = $sSettingsCollapseId ?? '';
    ?>
    <div class="page-header">
      <div class="container-xl">
        <?php if (!empty($aBreadcrumbs) || !empty($sPageHeaderButtons)) : ?>
        <div class="row g-2 align-items-center mb-1 d-print-none">
          <div class="col">
            <?php if (!empty($aBreadcrumbs)) : ?>
            <ol class="breadcrumb mb-0" aria-label="breadcrumbs">
              <li class="breadcrumb-item">
                <a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard"><?= gettext('Home') ?></a>
              </li>
              <?php foreach ($aBreadcrumbs as $crumb) : ?>
                <?php if (!empty($crumb['active'])) : ?>
              <li class="breadcrumb-item active" aria-current="page"><?= $crumb['label'] ?></li>
                <?php else : ?>
              <li class="breadcrumb-item"><a href="<?= $crumb['url'] ?>"><?= $crumb['label'] ?></a></li>
                <?php endif; ?>
              <?php endforeach; ?>
            </ol>
            <?php endif; ?>
          </div>
          <?php if (!empty($sPageHeaderButtons)) : ?>
          <div class="col-auto ms-auto">
            <?= $sPageHeaderButtons ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="row g-2 align-items-center">
          <div class="col">
            <h2 class="page-title"><?= $sPageTitle ?></h2>
            <?php if (!empty($sPageSubtitle)) : ?>
            <div class="text-muted mt-1"><?= $sPageSubtitle ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div><!-- /.page-header -->
    <?php if (!empty($sSettingsCollapseId)) : ?>
    <div class="container-xl">
      <div class="collapse mb-3" id="<?= $sSettingsCollapseId ?>"></div>
    </div>
    <?php endif; ?>
    <div class="page-body">
      <div class="container-xl">
<?php
// Hydrate registry from session-cached remote notifications (no HTTP calls)
NotificationService::loadSessionNotifications();

// Render all active notifications as dismissible alerts
foreach (NotificationService::getNotifications() as $notification) {
?>
      <div class="alert alert-<?= InputUtils::escapeHTML($notification->getType()) ?> alert-dismissible"
           role="alert"
           <?= $notification->getId() ? 'data-notification-id="' . InputUtils::escapeAttribute($notification->getId()) . '"' : '' ?>
           <?= $notification->getDismissSettingKey() ? 'data-dismiss-key="' . InputUtils::escapeAttribute($notification->getDismissSettingKey()) . '"' : '' ?>>
        <div class="d-flex">
          <div><i class="ti ti-<?= InputUtils::escapeHTML($notification->getIcon()) ?> me-2"></i></div>
          <div>
            <strong><?= InputUtils::escapeHTML($notification->getTitle()) ?></strong>
            <?php if ($notification->getMessage()): ?>
              <div class="text-secondary"><?= InputUtils::escapeHTML($notification->getMessage()) ?></div>
            <?php endif; ?>
            <?php if ($notification->getUrl()): ?>
              <a href="<?= InputUtils::escapeAttribute($notification->getUrl()) ?>" class="alert-link">
                <?= gettext('Learn more') ?>
              </a>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($notification->getDismissSettingKey()): ?>
          <button type="button" class="btn-close js-dismiss-notification" data-bs-dismiss="alert" aria-label="<?= gettext('Dismiss') ?>"></button>
        <?php endif; ?>
      </div>
<?php } ?>
