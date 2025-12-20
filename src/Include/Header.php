<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\MenuRenderer;

$localeInfo = Bootstrapper::getCurrentLocale();

// Turn ON output buffering
ob_start();

require_once __DIR__ . '/Header-function.php';
require_once __DIR__ . '/Header-Security.php';

// Top level menu index counter
$MenuFirst = 1;
?>
<!DOCTYPE HTML>
<html>
<head>
  <meta charset="UTF-8"/>
  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <?php require_once __DIR__ . '/Header-HTML-Scripts.php'; ?>
</head>

<body class="hold-transition <?= AuthenticationManager::getCurrentUser()->getStyle() ?> sidebar-mini">
<!-- Site wrapper -->
<div class="wrapper">
  <?php
    Header_modals();
    Header_body_scripts();

    $MenuFirst = 1;
    ?>

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav" style="flex: 1 1 auto;">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fa-solid fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?= SystemURLs::getRootPath()?>/" class="nav-link">Home</a>
            </li>
            <li class="nav-item" style="flex: 1 1 auto; min-width: 150px; max-width: 600px; margin-left: 10px;">
                <form action="#" method="get" class="navbar-form" style="display: flex; align-items: center; width: 100%;">
                    <div class="input-group" style="width: 100%;">
                        <select class="form-control multiSearch">
                        </select>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" style="border-left: 0;">
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
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="left: inherit; right: 0px;">
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
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="left: inherit; right: 0px;">
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
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="left: inherit; right: 0px;">
                    <span id="cart-dropdown-menu"></span>
                </div>
            </li>

            <!-- Support Dropdown Menu -->
            <li class="nav-item dropdown show">
                <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="true" id="supportMenu">
                    <i class="fa-solid fa-headset"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="left: inherit; right: 0px;">
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
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="left: inherit; right: 0px;">
                    <a href="<?= SystemURLs::getRootPath()?>/PersonView.php?PersonID=<?= AuthenticationManager::getCurrentUser()->getPersonId() ?>" class="dropdown-item">
                      <i class="fa-solid fa-home"></i> <?= gettext("Profile") ?></a>
                  <a href="<?= SystemURLs::getRootPath() ?>/v2/user/current/changepassword" class="dropdown-item">
                      <i class="fa-solid fa-key"></i> <?= gettext('Change Password') ?></a>
                  <a href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= AuthenticationManager::getCurrentUser()->getPersonId() ?>" class="dropdown-item">
                      <i class="fa-solid fa-cogs"></i> <?= gettext('Change Settings') ?></a>
                  <?php if (LocalAuthentication::getIsTwoFactorAuthSupported()) { ?>
                      <div class="dropdown-divider"></div>
                      <a href="<?= SystemURLs::getRootPath() ?>/v2/user/current/enroll2fa" class="dropdown-item">
                          <i class="fa-solid fa-gear"></i> <?= gettext("Manage 2 Factor Authentication") ?></a>
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
          <?php
            $headerHTML = '<b>Church</b>CRM';
            $sHeader = SystemConfig::getValue("sHeader");
            if (!empty($sHeader)) {
                $headerHTML = html_entity_decode($sHeader, ENT_QUOTES);
            }
            ?>
          <span class="brand-text font-weight-light"><?= $headerHTML ?></span>
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
