<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
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
  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <?php require_once __DIR__ . '/Header-HTML-Scripts.php'; ?>
  <?= PluginManager::getPluginHeadContent() ?>
</head>

<body class="hold-transition <?= AuthenticationManager::getCurrentUser()->getStyle() ?> sidebar-mini">
<!-- Site wrapper -->
<div class="wrapper">
  <!-- Issue Report Modal -->
    <div id="IssueReportModal" class="modal fade" role="dialog">
        <div class="modal-dialog modal-lg">
            <!-- Modal content-->
            <div class="modal-content" id="bugForm">
                <form name="issueReport">
                    <input type="hidden" name="pageName" value="<?= $_SERVER['REQUEST_URI'] ?>"/>
                    <div class="modal-header">
                        <h5 class="modal-title"><?= gettext('Issue Report!') ?></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">x</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                            <h5><i class="icon fa-solid fa-info"></i>Alert!</h5>
                            <?= gettext('When you click "Submit to GitHub" you will be directed to GitHub issues page with your system info prefilled.') ?> <?= gettext('No personally identifiable information will be submitted unless you purposefully include it.') ?>
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

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav flex-grow-1">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fa-solid fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?= SystemURLs::getRootPath()?>/" class="nav-link">Home</a>
            </li>
            <li class="nav-item navbar-search-item">
                <form action="#" method="get" class="navbar-form d-flex align-items-center w-100">
                    <div class="input-group w-100">
                        <select class="form-control multiSearch">
                        </select>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary navbar-search-btn" type="button">
                                <i class="fa-solid fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </li>
        </ul>

        <!-- Right navbar links -->
        <span class="navbar-nav ml-auto">

            <!-- System Update Notification Menu -->
            <?php
            $showUpdateMenu = isset($_SESSION['systemUpdateAvailable']) && $_SESSION['systemUpdateAvailable'] === true;
            $updateVersion = $_SESSION['systemUpdateVersion'] ?? null;
            ?>
            <li class="nav-item dropdown <?= $showUpdateMenu ? '' : 'd-none' ?>" id="systemUpdateMenuItem">
                <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="true" id="upgradeMenu" title="<?= gettext('New Release') ?>">
                    <i class="fa-solid fa-download"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <?php if (AuthenticationManager::getCurrentUser()->isAdmin()) { ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/system/upgrade" class="dropdown-item" title="<?= gettext('New Release') ?>">
                        <i class="fa-solid fa-champagne-glasses"></i> <?= gettext('New Release') ?>
                        <?php if ($updateVersion) { ?>
                            <span id="upgradeToVersion"><?= $updateVersion->MAJOR ?>.<?= $updateVersion->MINOR ?>.<?= $updateVersion->PATCH ?></span>
                        <?php } ?>
                    </a>
                    <?php } ?>
                    <a href="https://github.com/ChurchCRM/CRM/releases/latest" target="_blank" class="dropdown-item" title="<?= gettext('Release Notes') ?>">
                        <i class="fa-solid fa-book-open-reader"></i> <?= gettext('Release Notes') ?>
                    </a>
                </div>
            </li>

            <!-- Locale Dropdown Menu -->
            <li class="nav-item dropdown show">
                <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="true">
                    <i class="fi fi-<?= $localeInfo->getCountryFlagCode() ?> fi-squared"></i>
                    <?php if ($localeInfo->shouldShowTranslationBadge()) { ?>
                    <span class="badge badge-warning navbar-badge" title="<?= gettext('Translation incomplete') ?>">!</span>
                    <?php } ?>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span class="dropdown-item dropdown-header">
                        <i class="fi fi-<?= $localeInfo->getCountryFlagCode() ?>"></i>
                        <?= $localeInfo->getName() ?> [<?= $localeInfo->getLocale() ?>]
                        <?php if ($localeInfo->shouldShowTranslationPercentage()) { ?>
                        <span class="badge badge-<?= $localeInfo->getTranslationPercentage() < 90 ? 'warning' : 'success' ?> ml-1"><?= $localeInfo->getTranslationPercentage() ?>%</span>
                        <?php } ?>
                    </span>
                    <div class="dropdown-divider"></div>
                    <a href="https://poeditor.com/join/project?hash=RABdnDSqAt" class="dropdown-item" target="_blank">
                        <i class="fa-solid fa-people-carry"></i> <?= gettext("Help translate this project")?>
                    </a>
                </div>
            </li>

            <!-- Cart Functions: style can be found in dropdown.less -->
            <li class="nav-item dropdown show">
                <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="true">
                    <i class="fa-solid fa-shopping-cart"></i>
                    <span class="badge badge-info navbar-badge" id="iconCount"><?= Cart::countPeople() ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span id="cart-dropdown-menu"></span>
                </div>
            </li>

            <!-- Support Dropdown Menu -->
            <li class="nav-item dropdown show">
                <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="true" id="supportMenu">
                    <i class="fa-solid fa-headset"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <a href="<?= SystemURLs::getSupportURL() ?>" target="help" class="dropdown-item" title="<?= gettext('Help & Manual') ?>">
                        <i class="fa-solid fa-book-reader"></i> <?= gettext('Help & Manual') ?>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" id="reportIssue" class="dropdown-item" data-toggle="modal" data-target="#IssueReportModal"  title="<?= gettext('Report an issue') ?>">
                        <i class="fa-solid fa-bug"></i> <?= gettext('Report an issue') ?>
                    </a>
                    <a href="https://gitter.im/ChurchCRM/CRM" target="_blank" class="dropdown-item" title="<?= gettext('Developer Chat') ?>">
                        <i class="fa-regular fa-comment-dots"></i> <?= gettext('Developer Chat') ?>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="https://github.com/ChurchCRM/CRM/wiki/Contributing" target="_blank" class="dropdown-item" title="<?= gettext('Contributing') ?>">
                        <i class="fab fa-github"></i> <?= gettext('Contributing') ?>
                    </a>
                </div>
            </li>

            <!-- Support Dropdown Menu -->
            <li class="nav-item dropdown show">
                <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="true">
                    <i class="fa-solid fa-user"></i> <?= AuthenticationManager::getCurrentUser()->getName() ?>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <a href="<?= SystemURLs::getRootPath()?>/PersonView.php?PersonID=<?= AuthenticationManager::getCurrentUser()->getPersonId() ?>" class="dropdown-item">
                      <i class="fa-solid fa-home"></i> <?= gettext("Profile") ?></a>
                  <a href="<?= SystemURLs::getRootPath() ?>/v2/user/current/changepassword" class="dropdown-item">
                      <i class="fa-solid fa-key"></i> <?= gettext('Change Password') ?></a>
                  <a href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= AuthenticationManager::getCurrentUser()->getPersonId() ?>" class="dropdown-item">
                      <i class="fa-solid fa-cogs"></i> <?= gettext('Change Settings') ?></a>
                  <?php if (LocalAuthentication::getIsTwoFactorAuthSupported()) { ?>
                      <div class="dropdown-divider"></div>
                      <a href="<?= SystemURLs::getRootPath() ?>/v2/user/current/manage2fa" class="dropdown-item">
                          <i class="fa-solid fa-shield"></i> <?= gettext("Manage Two-Factor Authentication") ?></a>
                  <?php } ?>
                     <div class="dropdown-divider"></div>
                    <a href="<?= SystemURLs::getRootPath() ?>/session/end" class="dropdown-item">
                      <i class="fa-solid fa-sign-out-alt"></i> <?= gettext('Sign out') ?></a>

                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                    <i class="fa-solid fa-expand-arrows-alt"></i>
                </a>
            </li>
        </span>
    </nav>

  <!-- =============================================== -->

  <!-- Left side column. contains the sidebar -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Logo -->
      <a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard" class="brand-link">
          <!-- mini logo for sidebar mini 50x50 pixels -->
          <img src="<?= SystemURLs::getRootPath() ?>/Images/CRM_50x50.png" alt="ChurchCRM Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
          <!-- logo for regular state and mobile devices -->
                    <span class="brand-text font-weight-light"><?= ChurchMetaData::getChurchName() ?: gettext('ChurchCRM') ?></span>
      </a>
    <!-- sidebar: style can be found in sidebar.less -->
    <div class="sidebar">
      <!-- sidebar menu: : style can be found in sidebar.less -->

        <nav class="mt-2">
            <ul class="nav nav-pills  nav-child-indent nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <?php MenuRenderer::renderMenu(); ?>
            </ul>
        </nav>
    </div>
  </aside>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
      <section class="content-header">
          <div class="container-fluid">
              <div class="row mb-2">
                  <div class="col-sm-12">
                      <h1><?= $sPageTitle; ?></h1>
                  </div>
              </div>
          </div><!-- /.container-fluid -->
      </section>
    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
