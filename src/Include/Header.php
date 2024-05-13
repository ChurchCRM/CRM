<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\TaskService;
use ChurchCRM\view\MenuRenderer;

$taskService = new TaskService();

// Turn ON output buffering
ob_start();

require_once 'Header-function.php';
if (SystemConfig::debugEnabled()) {
    require_once 'Header-Security.php';
}

// Top level menu index counter
$MenuFirst = 1;
?>
<!DOCTYPE HTML>
<html>
<head>
  <meta charset="UTF-8"/>
  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <?php require 'Header-HTML-Scripts.php'; ?>
</head>

<body class="hold-transition <?= AuthenticationManager::getCurrentUser()->getStyle() ?> sidebar-mini">
<!-- Site wrapper -->
<div class="wrapper">
  <?php
    Header_modals();
    Header_body_scripts();

    $loggedInUserPhoto = SystemURLs::getRootPath() . '/api/person/' . AuthenticationManager::getCurrentUser()->getId() . '/thumbnail';
    $MenuFirst = 1;
    ?>

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?= SystemURLs::getRootPath()?>/" class="nav-link">Home</a>
            </li>


        </ul>

        <!-- Right navbar links -->
        <span class="navbar-nav ml-auto">

            <!-- Support Dropdown Menu -->
            <li class="nav-item dropdown d-none" id="systemUpdateMenuItem">
                <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="true" id="upgradeMenu" title="<?= gettext('New Release') ?>">
                    <i class="fas fa-download"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="left: inherit; right: 0px;">
                    <?php if (AuthenticationManager::getCurrentUser()->isAdmin()) { ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/UpgradeCRM.php" class="dropdown-item" title="<?= gettext('New Release') ?>">
                        <i class="fa-solid fa-champagne-glasses"></i> <?= gettext('New Release') ?> <span id="upgradeToVersion"></span>
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
                    <i class="fi fi-squared"></i>
                    <span class="badge badge-danger navbar-badge" id="translationPer"></span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="left: inherit; right: 0px;">
                    <a href="https://poeditor.com/join/project?hash=RABdnDSqAt" class="dropdown-item">
                        <i class="fas fa-people-carry"></i> <?= gettext("Help translate this project")?>
                    </a>
                    <?php if (AuthenticationManager::getCurrentUser()->isAdmin()) { ?>
                    <div class="dropdown-divider"></div>
                    <a href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= AuthenticationManager::getCurrentUser()->getPersonId() ?>" class="dropdown-item">
                        <i class="fas fa-user-edit"></i> <span id="translationInfo"></span>
                    </a>
                    <?php } ?>
                </div>
            </li>

            <!-- Cart Functions: style can be found in dropdown.less -->
            <li class="nav-item dropdown show">
                <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="true">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="badge badge-info navbar-badge"><?= Cart::countPeople() ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="left: inherit; right: 0px;">
                    <span id="cart-dropdown-menu"></span>
                </div>
            </li>


            <!-- Support Dropdown Menu -->
            <li class="nav-item dropdown show">
                <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="true" id="supportMenu">
                    <i class="fas fa-headset"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="left: inherit; right: 0px;">
                    <a href="<?= SystemURLs::getSupportURL() ?>" target="help" class="dropdown-item" title="<?= gettext('Help & Manual') ?>">
                        <i class="fas fa-book-reader"></i> <?= gettext('Help & Manual') ?>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" id="reportIssue" class="dropdown-item" data-toggle="modal" data-target="#IssueReportModal"  title="<?= gettext('Report an issue') ?>">
                        <i class="fas fa-bug"></i> <?= gettext('Report an issue') ?>
                    </a>
                    <a href="https://gitter.im/ChurchCRM/CRM" target="_blank" class="dropdown-item" title="<?= gettext('Developer Chat') ?>">
                        <i class="far fa-comment-dots"></i> <?= gettext('Developer Chat') ?>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="https://github.com/ChurchCRM/CRM/wiki/Contributing" target="_blank" class="dropdown-item" title="<?= gettext('Contributing') ?>">
                        <i class="fab fa-github"></i> <?= gettext('Contributing') ?>
                    </a>
                </div>
            </li>

            <?php
            $tasks = $taskService->getCurrentUserTasks();
            $taskSize = count($tasks);

            ?>
            <li class="nav-item">
                <a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#" role="button">
                    <i class="far fa-bell"></i>
                    <span class="badge badge-warning navbar-badge"><?= $taskSize ?></span>
                </a>
            </li>

            <!-- Support Dropdown Menu -->
            <li class="nav-item dropdown show">
                <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="true">
                    <i class="fas fa-user"></i> <?= AuthenticationManager::getCurrentUser()->getName() ?>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="left: inherit; right: 0px;">
                    <a href="<?= SystemURLs::getRootPath()?>/PersonView.php?PersonID=<?= AuthenticationManager::getCurrentUser()->getPersonId() ?>" class="dropdown-item">
                      <i class="fa fa-home"></i> <?= gettext("Profile") ?></a>
                  <a href="<?= SystemURLs::getRootPath() ?>/v2/user/current/changepassword" class="dropdown-item">
                      <i class="fa fa-key"></i> <?= gettext('Change Password') ?></a>
                  <a href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= AuthenticationManager::getCurrentUser()->getPersonId() ?>" class="dropdown-item">
                      <i class="fas fa-cogs"></i> <?= gettext('Change Settings') ?></a>
                  <?php if (LocalAuthentication::getIsTwoFactorAuthSupported()) { ?>
                      <div class="dropdown-divider"></div>
                      <a href="<?= SystemURLs::getRootPath() ?>/v2/user/current/enroll2fa" class="dropdown-item">
                          <i class="fa fa-gear"></i> <?= gettext("Manage 2 Factor Authentication") ?></a>
                  <?php } ?>
                     <div class="dropdown-divider"></div>
                    <a href="<?= SystemURLs::getRootPath() ?>/session/end" class="dropdown-item">
                      <i class="fas fa-sign-out-alt"></i> <?= gettext('Sign out') ?></a>

                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                    <i class="fas fa-expand-arrows-alt"></i>
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

        <!-- search form -->
      <form action="#" method="get" class="sidebar-form">

        <select class="form-control multiSearch">
        </select>

      </form>
      <!-- /.search form -->
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
                  <div class="col-sm-6">
                      <h1><?= $sPageTitle; ?></h1>
                  </div>
                  <div class="col-sm-6">
                      <ol class="breadcrumb float-sm-right">
                          <li class="breadcrumb-item"><a href="<?= SystemURLs::getRootPath()?>"><?= _("Home")?></a></li>
                          <li class="breadcrumb-item active"><?= $sPageTitle; ?></li>
                      </ol>
                  </div>
              </div>
          </div><!-- /.container-fluid -->
      </section>
    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
