<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\view\MenuRenderer;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Utils\PHPToMomentJSConverter;

$localeInfo = Bootstrapper::getCurrentLocale();

// Turn ON output buffering
ob_start();

require_once __DIR__ . '/Header-Security.php';

// Initialize plugin system for logged-in users
$pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
PluginManager::init($pluginsPath);

// Top level menu index counter
$MenuFirst = 1;
?>
<!DOCTYPE html>
<html>
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
    <div class="modal-dialog modal-lg">
      <div class="modal-content" id="bugForm">
        <form name="issueReport">
          <input type="hidden" name="pageName" value="<?= $_SERVER['REQUEST_URI'] ?>"/>
          <div class="modal-header">
            <h5 class="modal-title"><?= gettext('Issue Report!') ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info alert-dismissible">
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-hidden="true"></button>
              <h5><i class="ti ti-info-circle me-1"></i><?= gettext('Alert!') ?></h5>
              <?= gettext('When you click "Submit to GitHub" you will be directed to GitHub issues page with your system info prefilled.') ?>
              <?= gettext('No personally identifiable information will be submitted unless you purposefully include it.') ?>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="submitIssue"><?= gettext('Submit to GitHub') ?></button>
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
          root: "<?= SystemURLs::getRootPath() ?>",
          fullURL:"<?= SystemURLs::getURL() ?>",
          lang: "<?= $localeInfo->getLanguageCode() ?>",
          userId: "<?= AuthenticationManager::getCurrentUser()->getId() ?>",
          version: "<?= $_SESSION['sSoftwareInstalledVersion'] ?? 'unknown' ?>",
          systemLocale: "<?= $localeInfo->getSystemLocale() ?>",
          locale: "<?= $localeInfo->getLocale() ?>",
          shortLocale: "<?= $localeInfo->getShortLocale() ?>",
          timeZone: "<?= SystemConfig::getValue('sTimeZone') ?>",
          maxUploadSize: "<?= SystemService::getMaxUploadFileSize(true) ?>",
          maxUploadSizeBytes: "<?= SystemService::getMaxUploadFileSize(false) ?>",
          datePickerformat:"<?= SystemConfig::getValue('sDatePickerPlaceHolder') ?>",
          churchWebSite:"<?= SystemConfig::getValue('sChurchWebSite') ?>",
          systemConfigs: {
            sDateTimeFormat: "<?= PHPToMomentJSConverter::convertFormatString(SystemConfig::getValue('sDateTimeFormat'))?>",
          },
          iDashboardServiceIntervalTime:"<?= SystemConfig::getValue('iDashboardServiceIntervalTime') ?>",
          // Plugin configs from active plugins (via getClientConfig())
          plugins: <?= json_encode(PluginManager::getPluginsClientConfig(), JSON_FORCE_OBJECT) ?>,
          // Legacy: keep bEnableGravatarPhotos for backward compatibility with existing JS
          bEnableGravatarPhotos: <?= json_encode(PluginManager::getPluginsClientConfig()['gravatar']['enabled'] ?? false) ?>,
          plugin: {
              dataTable : {
                  "pageLength": <?= $tableSize ?>,
                  "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                  "language": {
                      "url": "<?= SystemURLs::getRootPath() ?>/locale/vendor/datatables/<?= $localeInfo->getDataTables() ?>.json"
                  },
                  responsive: true,
                  layout: {
                      topStart: 'search',
                      topEnd: 'buttons',
                      bottomStart: 'pageLength',
                      bottomEnd: ['info', 'paging']
                  },
                  buttons: [
                      'copy',
                      'csv',
                      'excel',
                      {
                          extend: 'pdf',
                          orientation: 'landscape',
                          pageSize: 'LEGAL'
                      },
                      'print'
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

  <!-- ============================================================ -->
  <!-- Sidebar (Tabler vertical navbar)                              -->
  <!-- ============================================================ -->
  <aside class="navbar navbar-vertical navbar-expand-lg navbar-dark" id="sidebar">
    <div class="container-fluid">
      <button class="navbar-toggler" type="button"
              data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
              aria-controls="sidebar-menu" aria-expanded="false"
              aria-label="<?= gettext('Toggle navigation') ?>">
        <span class="navbar-toggler-icon"></span>
      </button>
      <a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard" class="navbar-brand navbar-brand-autodark">
        <img src="<?= SystemURLs::getRootPath() ?>/Images/CRM_50x50.png"
             alt="<?= htmlspecialchars(ChurchMetaData::getChurchName() ?: gettext('ChurchCRM')) ?>"
             class="navbar-brand-image">
        <span class="navbar-brand-text ps-2">
          <?= ChurchMetaData::getChurchName() ?: gettext('ChurchCRM') ?>
        </span>
      </a>
      <div class="collapse navbar-collapse" id="sidebar-menu">
        <ul class="navbar-nav pt-lg-3">
          <?php MenuRenderer::renderMenu(); ?>
        </ul>
      </div>
    </div>
  </aside>

  <!-- ============================================================ -->
  <!-- Topbar (Tabler horizontal navbar with glassmorphism)          -->
  <!-- ============================================================ -->
  <header class="navbar navbar-expand-md navbar-light d-none d-lg-flex d-print-none sticky-top navbar-glass">
    <div class="container-xl">

      <button class="navbar-toggler" type="button"
              data-bs-toggle="collapse" data-bs-target="#navbar-menu">
        <span class="navbar-toggler-icon"></span>
      </button>

      <!-- Right-side nav items -->
      <div class="navbar-nav flex-row order-md-last">

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
          <div class="dropdown-menu dropdown-menu-end">
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
        <div class="nav-item dropdown">
          <a class="nav-link px-0" data-bs-toggle="dropdown" href="#">
            <i class="fi fi-<?= $localeInfo->getCountryFlagCode() ?> fi-squared"></i>
            <?php if ($localeInfo->shouldShowTranslationBadge()) { ?>
            <span class="badge bg-warning ms-1" title="<?= gettext('Translation incomplete') ?>">!</span>
            <?php } ?>
          </a>
          <div class="dropdown-menu dropdown-menu-end">
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
        <div class="nav-item dropdown">
          <a class="nav-link px-0" data-bs-toggle="dropdown" href="#">
            <i class="fa-duotone fa-solid fa-cart-shopping"></i>
            <span class="badge bg-info ms-1" id="iconCount"><?= Cart::countPeople() ?></span>
          </a>
          <div class="dropdown-menu dropdown-menu-end">
            <span id="cart-dropdown-menu"></span>
          </div>
        </div>

        <!-- Support -->
        <div class="nav-item dropdown">
          <a class="nav-link px-0" data-bs-toggle="dropdown" href="#" id="supportMenu">
            <i class="ti ti-headset"></i>
          </a>
          <div class="dropdown-menu dropdown-menu-end">
            <a href="<?= SystemURLs::getSupportURL() ?>" target="help" class="dropdown-item"
               title="<?= gettext('Help & Manual') ?>">
              <i class="ti ti-book me-2"></i><?= gettext('Help & Manual') ?>
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
              <i class="fa-brands fa-github me-2"></i><?= gettext('Contributing') ?>
            </a>
          </div>
        </div>

        <!-- User -->
        <div class="nav-item dropdown">
          <a class="nav-link d-flex lh-1 text-reset p-0 ms-2" data-bs-toggle="dropdown" href="#">
            <i class="ti ti-user-circle fs-2 me-1"></i>
            <span class="d-none d-xl-block ps-1"><?= AuthenticationManager::getCurrentUser()->getName() ?></span>
          </a>
          <div class="dropdown-menu dropdown-menu-end">
            <a href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= AuthenticationManager::getCurrentUser()->getPersonId() ?>"
               class="dropdown-item">
              <i class="ti ti-user me-2"></i><?= gettext("Profile") ?>
            </a>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/user/current/changepassword" class="dropdown-item">
              <i class="ti ti-key me-2"></i><?= gettext('Change Password') ?>
            </a>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= AuthenticationManager::getCurrentUser()->getPersonId() ?>"
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

        <!-- Fullscreen toggle -->
        <div class="nav-item">
          <a class="nav-link px-0" href="#" id="fullscreenToggle"
             title="<?= gettext('Fullscreen') ?>">
            <i class="ti ti-maximize"></i>
          </a>
        </div>

      </div><!-- /.navbar-nav.order-md-last -->

      <!-- Search -->
      <div class="collapse navbar-collapse" id="navbar-menu">
        <div class="d-flex flex-fill">
          <form action="#" method="get" class="navbar-form w-100 d-flex align-items-center">
            <div class="input-group w-100">
              <select class="form-control multiSearch"></select>
              <button class="btn btn-outline-secondary navbar-search-btn" type="button">
                <i class="ti ti-search"></i>
              </button>
            </div>
          </form>
        </div>
      </div>

    </div><!-- /.container-xl -->
  </header>

  <!-- ============================================================ -->
  <!-- Page wrapper                                                  -->
  <!-- ============================================================ -->
  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row g-2 align-items-center">
          <div class="col-auto">
            <h2 class="page-title"><?= $sPageTitle ?></h2>
          </div>
          <?php if (!empty($sBreadcrumb)) { ?>
          <div class="col-12">
            <ol class="breadcrumb" aria-label="breadcrumbs">
              <li class="breadcrumb-item">
                <a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard"><?= gettext('Home') ?></a>
              </li>
              <?= $sBreadcrumb ?>
            </ol>
          </div>
          <?php } ?>
        </div>
      </div>
    </div><!-- /.page-header -->
    <div class="page-body">
      <div class="container-xl">
