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
  <script>
    function displayMessage(endpoint, message) {
      $(".modal").modal('hide');
      $("#APIError").modal('show');
      $("#APIEndpoint").text(endpoint);
      $("#APIErrorText").text(message);
    }

    $(document).ajaxError(function (evt, xhr, settings) {
      var CRMResponse = JSON.parse(xhr.responseText).error;
      displayMessage("[" + settings.type + "] " + settings.url, " " +CRMResponse.text);
    });
  </script>
</head>

<body class="hold-transition <?= $_SESSION['sStyle'] ?> sidebar-mini">
<!-- Site wrapper -->
<div class="wrapper">
    <?php
        Header_modals();
        Header_body_scripts();
        Header_body_menu();
    ?>
