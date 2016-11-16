<?php
/*******************************************************************************
 *
 *  filename    : Include/Header.php
 *  website     : http://www.churchcrm.io
 *  description : page header used for most pages
 *
 *  Copyright 2001-2004 Phillip Hullquist, Deane Barker, Chris Gebhardt, Michael Wilt
 *
 *  LICENSE:
 *  (C) Free Software Foundation, Inc.
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 *  General Public License for more details.
 *
 *  http://www.gnu.org/licenses
 *
 *  This file best viewed in a text editor with tabs stops set to 4 characters
 *
 ******************************************************************************/
if (!$systemService->checkDatabaseVersion())  //either the DB is good, or the upgrade was successful.
{
  Redirect('CheckVersion.php');
  exit;
}

use ChurchCRM\Service\TaskService;

$taskService = new TaskService();

//
// Turn ON output buffering
ob_start();

require_once('Header-function.php');

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
<!-- Site wrapper -->
<div class="wrapper">
  <?php
  Header_modals();
  Header_body_scripts();

  $loggedInUserPhoto = $sRootPath . "/api/persons/" . $_SESSION['iUserID'] . "/photo";
  $MenuFirst = 1;
  ?>

  <header class="main-header">
    <!-- Logo -->
    <a href="<?= $sRootPath ?>/Menu.php" class="logo">
      <!-- mini logo for sidebar mini 50x50 pixels -->
      <span class="logo-mini"><b>C</b>RM</span>
      <!-- logo for regular state and mobile devices -->
      <?php
      $headerHTML = "<b>Church</b>CRM";
      if ($sHeader) {
        $headerHTML = html_entity_decode($sHeader, ENT_QUOTES);
      }
      ?>
      <span class="logo-lg"><?= $headerHTML ?></span>
    </a>
    <!-- Header Navbar: style can be found in header.less -->
    <nav class="navbar navbar-static-top">
      <!-- Sidebar toggle button-->
      <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
        <span class="sr-only"><?= gettext("Toggle navigation") ?></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </a>

      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">
          <li class="dropdown settings-dropdown">
            <a href="<?= $sRootPath ?>/CartView.php">
              <i class="fa fa-shopping-cart"></i>
              <span class="label label-success"><?= count($_SESSION['aPeopleCart']) ?></span>
            </a>
          </li>
          <!-- User Account: style can be found in dropdown.less -->
          <li class="dropdown user user-menu">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <img src="<?= $loggedInUserPhoto ?>" class="user-image" alt="User Image">
              <span class="hidden-xs"><?= $_SESSION['user']->getName() ?> </span>

            </a>
            <ul class="dropdown-menu">
              <!-- User image -->
              <li class="user-header">
                <img src="<?= $loggedInUserPhoto ?>" class="img-circle" alt="User Image">
                <p><?= $_SESSION['user']->getName() ?></p>
              </li>
              <!-- Menu Footer-->
              <li class="user-footer">
                <div class="pull-left">
                  <a href="<?= $sRootPath?>/UserPasswordChange.php"
                     class="btn btn-default btn-flat"><?= gettext("Change Password") ?></a>
                </div>
                <div class="pull-right">
                  <a href="<?= $sRootPath?>/SettingsIndividual.php"
                     class="btn btn-default btn-flat"><?= gettext("My Settings") ?></a>
                </div>
              </li>
            </ul>
          </li>
          <?php if ($_SESSION['bAdmin']) { ?>
            <li class="hidden-xxs">
              <a class="js-gitter-toggle-chat-button">
                <i class="fa fa-comments"></i>
              </a>
            </li>
            <li class="dropdown settings-dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                <i class="fa fa-cog"></i>
              </a>
              <ul class="dropdown-menu">
                <li class="user-body">
                  <?php addMenu("admin"); ?>
                </li>
              </ul>
            </li>
            <?php
            $tasks = $taskService->getCurrentUserTasks();
            $taskSize = count($tasks);
            ?>
            <li class="dropdown tasks-menu">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="true">
                <i class="fa fa-flag-o"></i>
                <span class="label label-danger"><?= $taskSize ?></span>
              </a>
              <ul class="dropdown-menu">
                <li class="header"><?= gettext("You have") ?> <?= $taskSize ?> <?= gettext("task(s)") ?></li>
                <li>
                  <!-- inner menu: contains the actual data -->
                  <div class="slimScrollDiv" style="position: relative; overflow: hidden; width: auto; height: 200px;">
                    <ul class="menu" style="overflow: hidden; width: 100%; height: 200px;">
                      <?php foreach ($tasks as $task) { ?>
                        <li><!-- Task item -->
                          <a href="<?= $task["link"] ?>">
                            <h3><?= $task["title"] ?>
                              <?php if ($task["admin"]) { ?>
                                <small class="pull-right"><i class="fa fa-fw fa-lock"></i></small>
                              <?php } ?>
                            </h3>
                          </a>
                        </li>
                        <!-- end task item -->
                      <?php } ?>
                    </ul>
                    <div class="slimScrollBar"
                         style="width: 3px; position: absolute; top: 11px; opacity: 0.4; display: none; border-radius: 7px; z-index: 99; right: 1px; height: 188.679px; background: rgb(0, 0, 0);"></div>
                    <div class="slimScrollRail"
                         style="width: 3px; height: 100%; position: absolute; top: 0px; display: none; border-radius: 7px; opacity: 0.2; z-index: 90; right: 1px; background: rgb(51, 51, 51);"></div>
                  </div>
                </li>
              </ul>
            </li>
          <?php } ?>
          <li class="hidden-xxs">
            <a href="http://docs.churchcrm.io" target="_blank">
              <i class="fa fa-support"></i>
            </a>
          </li>
          <li class="hidden-xxs">
            <a href="#" data-toggle="modal" data-target="#IssueReportModal">
              <i class="fa fa-bug"></i>
            </a>
          </li>
          <li class="hidden-xxs">
            <a href="<?= $sRootPath ?>/Login.php?Logoff=True">
              <i class="fa fa-power-off"></i>
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
          <a href="<?= $sRootPath . "/" ?>Menu.php">
            <i class="fa fa-dashboard"></i> <span><?= gettext("Dashboard") ?></span>
          </a>
        </li>
        <?php addMenu("root"); ?>
      </ul>
    </section>
  </aside>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <section class="content-header">
      <h1>
        <?php
        echo $sPageTitle . "\n";
        if ($sPageTitleSub != "") {
          echo "<small>" . $sPageTitleSub . "</small>";
        }
        ?>
      </h1>
      <ol class="breadcrumb">
        <li><a href="<?= $sRootPath . "/Menu.php" ?>"><i class="fa fa-dashboard"></i><?= gettext("Home") ?></a></li>
        <li class="active"><?= $sPageTitle ?></li>
      </ol>
    </section>
    <!-- Main content -->
    <section class="content">
      <?php if ($sGlobalMessage) { ?>
        <div class="main-box-body clearfix">
          <div class="callout callout-<?= $sGlobalMessageClass ?> fade in">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            <i class="fa fa-exclamation-triangle fa-fw fa-lg"></i>
            <?= $sGlobalMessage ?>
          </div>
        </div>
        <?php
      } ?>
