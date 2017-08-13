<?php
/*******************************************************************************
 *
 *  filename    : Include/Header.php
 *  website     : http://www.churchcrm.io
 *  description : page header used for most pages
 *
 *  Copyright 2001-2004 Phillip Hullquist, Deane Barker, Chris Gebhardt, Michael Wilt

 ******************************************************************************/


use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

if (!$systemService->isDBCurrent()) {  //either the DB is good, or the upgrade was successful.
    Redirect('CheckVersion.php');
    exit;
}

use ChurchCRM\Service\TaskService;

$taskService = new TaskService();

//
// Turn ON output buffering
ob_start();

require_once 'Header-function.php';

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
  <?php
  require 'Header-HTML-Scripts.php';
  Header_head_metatag();
  ?>
</head>

<body class="hold-transition <?= $_SESSION['sStyle'] ?> sidebar-mini">
<?php
  Header_system_notifications();
 ?>
<!-- Site wrapper -->
<div class="wrapper">
  <?php
  Header_modals();
  Header_body_scripts();

  $loggedInUserPhoto = SystemURLs::getRootPath().'/api/persons/'.$_SESSION['iUserID'].'/thumbnail';
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
      <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
        <span class="sr-only"><?= gettext('Toggle navigation') ?></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </a>

      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">
            <!-- Cart Functions: style can be found in dropdown.less -->
            <li class="dropdown notifications-menu">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" title="<?= gettext('Your Cart') ?>">
                    <i class="fa fa-shopping-cart"></i>
                    <span id="iconCount" class="label label-success"><?= count($_SESSION['aPeopleCart']) ?></span>
                </a>
                <ul class="dropdown-menu">
                    <?php
                    if (count($_SESSION['aPeopleCart']) > 0) {
                        $isCartPage = (basename($_SERVER['PHP_SELF']) == 'CartView.php'); ?>
                        <li id="showWhenCartNotEmpty">
                            <!-- inner menu: contains the actual data -->
                            <ul class="menu">
                                <li>
                                    <a href="<?= SystemURLs::getRootPath() ?>/CartView.php">
                                        <i class="fa fa-shopping-cart text-green"></i> <?= gettext('View Cart') ?>
                                    </a>
                                </li>
                                <li>
                                    <a id="emptyCart"
                                       href="<?= SystemURLs::getRootPath() ?>/<?= ($isCartPage ? 'CartView.php?Action=EmptyCart' : '#') ?>">
                                        <i class="fa fa-trash text-danger"></i> <?= gettext('Empty Cart') ?>
                                    </a>
                                </li>
                                <li>
                                    <a href="CartToGroup.php">
                                        <i class="fa fa-object-ungroup text-info"></i> <?= gettext('Empty Cart to Group') ?>
                                    </a>
                                </li>
                                <li>
                                    <a href="CartToEvent.php">
                                        <i class="fa fa fa-users text-info"></i> <?= gettext('Empty Cart to Family') ?>
                                    </a>
                                </li>
                                <li>
                                    <a href="CartToEvent.php">
                                        <i class="fa fa fa-ticket text-info"></i> <?= gettext('Empty Cart to Event') ?>
                                    </a>
                                </li>
                                <li>
                                    <a href="MapUsingGoogle.php?GroupID=0">
                                        <i class="fa fa-map-marker text-info"></i> <?= gettext('Map Cart') ?>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <!--li class="footer"><a href="#">View all</a></li-->
                        <?php
                    } else {
                        echo '<li class="header">' . gettext("Your Cart is Empty") . '</li>';
                    }
                    ?>
                </ul>
            </li>

          <!-- User Account: style can be found in dropdown.less -->
          <li class="dropdown user user-menu">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown" title="<?= gettext('Your settings and more') ?>">
              <img data-src="<?= SystemURLs::getRootPath()?>/api/persons/<?= $_SESSION['user']->getPersonId() ?>/thumbnail" data-name="<?= $_SESSION['user']->getName() ?>" class="user-image initials-image" alt="User Image">
              <span class="hidden-xs"><?= $_SESSION['user']->getName() ?> </span>

            </a>
            <ul class="dropdown-menu">
              <!-- User image -->
              <li class="user-header">
                <img data-src="<?= SystemURLs::getRootPath()?>/api/persons/<?= $_SESSION['user']->getPersonId() ?>/thumbnail" data-name="<?= $_SESSION['user']->getName() ?>" class="initials-image img-circle no-border" alt="User Image">
                <p><?= $_SESSION['user']->getName() ?></p>
              </li>
              <!-- Menu Footer-->
              <li class="user-footer">
                <div class="pull-left">
                  <a href="<?= SystemURLs::getRootPath() ?>/UserPasswordChange.php"
                     class="btn btn-default btn-flat"><?= gettext('Change Password') ?></a>
                </div>
                <div class="pull-right">
                  <a href="<?= SystemURLs::getRootPath() ?>/SettingsIndividual.php"
                     class="btn btn-default btn-flat"><?= gettext('My Settings') ?></a>
                </div>
              </li>
            </ul>
          </li>
          <li class="hidden-xxs">
            <a href="<?= SystemURLs::getSupportURL() ?>" target="_blank" title="<?= gettext('Read the docs') ?>">
              <i class="fa fa-support"></i>
            </a>
          </li>
          <li class="hidden-xxs">
            <a href="#" data-toggle="modal" data-target="#IssueReportModal" title="<?= gettext('Report an issue') ?>">
              <i class="fa fa-bug"></i>
            </a>
          </li>
          <li class="hidden-xxs">
            <a href="<?= SystemURLs::getRootPath() ?>/Logoff.php" title="<?= gettext('Log off') ?>">
              <i class="fa fa-power-off"></i>
            </a>
          </li>
          <?php
          $tasks = $taskService->getCurrentUserTasks();
          $taskSize = count($tasks);
          ?>
          <li class="dropdown settings-dropdown">
            <a href="#" data-toggle="control-sidebar" title="<?= gettext('Your tasks') ?>">
              <i class="fa fa-gears"></i>
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

        <select class="form-control multiSearch" style="width:100%">
        </select>

      </form>
      <!-- /.search form -->
      <!-- sidebar menu: : style can be found in sidebar.less -->
      <ul class="sidebar-menu">
        <li>
          <a href="<?= SystemURLs::getRootPath() ?>/Menu.php">
            <i class="fa fa-dashboard"></i> <span><?= gettext('Dashboard') ?></span>
          </a>
        </li>
        <?php addMenu('root'); ?>
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
        <div class="main-box-body clearfix" style="display:none" id="globalMessage">
          <div class="callout fade in" id="globalMessageCallOut">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            <i class="fa fa-exclamation-triangle fa-fw fa-lg"></i><span id="globalMessageText"></span>
          </div>
        </div>
