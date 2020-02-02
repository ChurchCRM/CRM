<?php
/*******************************************************************************
 *
 *  filename    : Include/Header.php
 *  website     : http://www.churchcrm.io
 *  description : page header used for most pages
 *
 *  Copyright 2001-2004 Phillip Hullquist, Deane Barker, Chris Gebhardt, Michael Wilt
 *  Copyright 2017 Philippe Logel
 ******************************************************************************/

use ChurchCRM\Service\SystemService;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\view\MenuRenderer;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\Cart;
use ChurchCRM\Service\TaskService;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;

$taskService = new TaskService();

//
// Turn ON output buffering
ob_start();

require_once 'Header-function.php';
require_once 'Header-Security.php';

// Top level menu index counter
$MenuFirst = 1;
?>
<!DOCTYPE HTML>
<html>
<head>
  <meta charset="UTF-8"/>
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <?php require 'Header-HTML-Scripts.php'; ?>
</head>

<body class="hold-transition <?= AuthenticationManager::GetCurrentUser()->getStyle() ?> sidebar-mini">
<!-- Site wrapper -->
<div class="wrapper">
  <?php
  Header_modals();
  Header_body_scripts();

  $loggedInUserPhoto = SystemURLs::getRootPath().'/api/person/'.AuthenticationManager::GetCurrentUser()->getId().'/thumbnail';
  $MenuFirst = 1;
  ?>

  <header class="main-header">
    <!-- Logo -->
    <a href="<?= SystemURLs::getRootPath() ?>/Menu.php" class="logo">
      <!-- mini logo for sidebar mini 50x50 pixels -->
      <span class="logo-mini"><b>C</b>RM</span>
      <!-- logo for regular state and mobile devices -->
      <?php
      $headerHTML = '<b>Church</b>CRM';
      $sHeader = SystemConfig::getValue("sHeader");
      if (!empty($sHeader)) {
          $headerHTML = html_entity_decode($sHeader, ENT_QUOTES);
      }
      ?>
      <span class="logo-lg"><?= $headerHTML ?></span>
    </a>
    <!-- Header Navbar: style can be found in header.less -->
    <nav class="navbar navbar-static-top">
      <!-- Sidebar toggle button-->
      <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
        <span class="sr-only"><?= gettext('Toggle navigation') ?></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </a>

      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">
            <!-- Cart Functions: style can be found in dropdown.less -->
            <li id="CartBlock" class="dropdown notifications-menu">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" title="<?= gettext('Your Cart') ?>">
                    <i class="fa fa-shopping-cart"></i>
                    <span id="iconCount" class="label label-success"><?= Cart::CountPeople() ?></span>
                </a>
                <ul class="dropdown-menu" id="cart-dropdown-menu"></ul>
            </li>

          <!-- User Account: style can be found in dropdown.less -->
          <li class="dropdown user user-menu">
            <a href="#" class="dropdown-toggle" id="dropdown-toggle" data-toggle="dropdown" title="<?= gettext('Your settings and more') ?>">
              <img src="<?= SystemURLs::getRootPath()?>/api/person/<?= AuthenticationManager::GetCurrentUser()->getPersonId() ?>/thumbnail" class="user-image initials-image" alt="User Image">
              <span class="hidden-xs"><?= AuthenticationManager::GetCurrentUser()->getName() ?> </span>

            </a>
            <ul class="hidden-xxs dropdown-menu">
              <li class="user-header" id="yourElement" style="height:auto">
                <table border=0 width="100%">
                <tr style="border-bottom: 1pt solid white;">
                <td valign="middle" width=110>
                  <img width="80" src="<?= SystemURLs::getRootPath()?>/api/person/<?= AuthenticationManager::GetCurrentUser()->getPersonId() ?>/thumbnail" class="initials-image img-circle no-border" alt="User Image">
                </td>
                <td valign="middle" align="left" >
                  <a href="<?= SystemURLs::getRootPath()?>/PersonView.php?PersonID=<?= AuthenticationManager::GetCurrentUser()->getPersonId() ?>" class="item_link">
                      <p ><i class="fa fa-home"></i> <?= gettext("Profile") ?></p></a>
                  <a href="<?= SystemURLs::getRootPath() ?>/v2/user/current/changepassword" class="item_link" id="change-password">
                      <p ><i class="fa fa-key"></i> <?= gettext('Change Password') ?></p></a>
                  <a href="<?= SystemURLs::getRootPath() ?>/SettingsIndividual.php" class="item_link">
                      <p ><i class="fa fa-gear"></i> <?= gettext('Change Settings') ?></p></a>
                  <?php
                    if (LocalAuthentication::GetIsTwoFactorAuthSupported()) {
                        ?>
                  <a href="<?= SystemURLs::getRootPath() ?>/v2/user/current/enroll2fa" class="item_link">
                      <p ><i class="fa fa-gear"></i> <?= gettext("Manage 2 Factor Authentication") ?></p></a>
                  <?php
                    }
                  ?>
                  <a href="<?= SystemURLs::getRootPath() ?>/session/end" class="item_link">
                      <p ><i class="fa fa-sign-out"></i> <?= gettext('Sign out') ?></p></a>
                </td>
                </tr>
                </table>
                <p style="color:#fff"><b><?= AuthenticationManager::GetCurrentUser()->getName() ?></b></p>
              </li>
            </ul>
          </li>
          <li class="dropdown">
            <a href="#" class="dropdown-toggle" id="dropdown-toggle" data-toggle="dropdown" title="<?= gettext('Help & Support') ?>">
              <i class="fa fa-support"></i>
            </a>
            <ul class="dropdown-menu">
              <li class="hidden-xxs">
                <a href="<?= SystemURLs::getSupportURL() ?>" target="_blank" title="<?= gettext('Help & Manual') ?>">
                  <i class="fa fa-question-circle"></i> <?= gettext('Help & Manual') ?>
                </a>
              </li>
              <li class="hidden-xxs">
                <a href="#" data-toggle="modal" data-target="#IssueReportModal" title="<?= gettext('Report an issue') ?>">
                  <i class="fa fa-bug"></i> <?= gettext('Report an issue') ?>
                </a>
              </li>
              <li class="hidden-xxs">
                <a href="https://gitter.im/ChurchCRM/CRM" target="_blank" title="<?= gettext('Developer Chat') ?>">
                  <i class="fa fa-commenting-o"></i> <?= gettext('Developer Chat') ?>
                </a>
              </li>
              <li class="hidden-xxs">
                <a href="https://github.com/ChurchCRM/CRM/wiki/Contributing" target="_blank" title="<?= gettext('Contributing') ?>">
                  <i class="fa fa-github"></i> <?= gettext('Contributing') ?>
                </a>
              </li>
            </ul>
          </li>
          <?php
          $tasks = $taskService->getCurrentUserTasks();
          $taskSize = count($tasks);
          ?>
          <li class="dropdown settings-dropdown">
            <a href="#" data-toggle="control-sidebar" title="<?= gettext('Your tasks') ?>">
              <i class="fa fa-tasks"></i>
              <span class="label label-danger"><?= $taskSize ?></span>
            </a>
          </li>
        </ul>
      </div>
    </nav>
  </header>
  <!-- =============================================== -->

  <!-- Left side column. contains the sidebar -->
  <aside class="main-sidebar">
    <!-- sidebar: style can be found in sidebar.less -->
    <section class="sidebar">
      <!-- search form -->
      <form action="#" method="get" class="sidebar-form">

        <select class="form-control multiSearch">
        </select>

      </form>
      <!-- /.search form -->
      <!-- sidebar menu: : style can be found in sidebar.less -->
      <ul class="sidebar-menu" data-widget="tree">
        <?php MenuRenderer::RenderMenu(); ?>
      </ul>
    </section>
  </aside>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <section class="content-header">
      <h1><?= $sPageTitle; ?></h1>
    </section>
    <!-- Main content -->
    <section class="content">
