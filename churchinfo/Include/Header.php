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

//
// Turn ON output buffering
ob_start();

require_once ('Header-function.php');

// Top level menu index counter
$MenuFirst = 1;

$sURLPath = $_SESSION['sURLPath'];
?>
<!DOCTYPE HTML>
<html>
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" type="text/css" href="<?= $sURLPath ?>/vendor/almasaeed2010/adminlte/bootstrap/css/bootstrap.min.css">
    <!-- google font libraries -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
    <!-- Ionicons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/dist/css/AdminLTE.min.css" />

    <!-- AdminLTE Skins. Choose a skin from the css/skins
         folder instead of downloading all of them to reduce the load. -->
    <link rel="stylesheet" href="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/dist/css/skins/_all-skins.min.css">

    <link rel="stylesheet" href="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/select2/select2.min.css">

    <link rel="stylesheet" href="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datepicker/datepicker3.css">
    <link rel="stylesheet" href= "<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/timepicker/bootstrap-timepicker.css">

    <!-- Custom ChurchCRM styles -->
    <link rel="stylesheet" href="<?= $sURLPath; ?>/Include/ChurchCRM.css">

    <!-- jQuery 2.1.4 -->
    <script src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/jQuery/jQuery-2.1.4.min.js"></script>

    <!-- jQuery 2.1.4 -->
    <script src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/jQueryUI/jquery-ui.min.js"></script>

    <!-- AdminLTE Select2 -->
    <script src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/select2/select2.full.min.js"></script>
    <!-- AdminLTE DatePicker -->
    <script src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datepicker/bootstrap-datepicker.js"></script>
     <!-- AdminLTE TimePicker -->
    <script src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/timepicker/bootstrap-timepicker.js"></script>
    
    <script>
    $(document).ajaxError(function(evt,xhr,settings) {
        console.log(evt);
        console.log(xhr);
        console.log(settings);
                console.log("API Fail");
                $(".modal").modal('hide');
                $("#APIError").modal('show');
                $("#APIEndpoint").text("["+settings.type+"] "+settings.url); 
                $("#APIErrorText").text(xhr.responseText);
    });
    
    </script>

    <?php Header_head_metatag(); ?>
</head>
<body class="hold-transition <?= $_SESSION['sStyle']; ?> sidebar-mini">
    <!-- Site wrapper -->
    <div class="wrapper">
    <?php
        Header_error_modal();
        Header_body_scripts();
        Header_body_menu();
    ?>
