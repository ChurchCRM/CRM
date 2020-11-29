<?php

use ChurchCRM\dto\SystemConfig;

require_once 'Header-function.php';
if (SystemConfig::debugEnabled()) {
    require_once 'Header-Security.php';
}

// Turn ON output buffering
ob_start();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>

<head>
  <?php require 'Header-HTML-Scripts.php'; ?>
</head>

<body>

<table height="100%" width="100%" border="0" cellpadding="5" cellspacing="0" align="center">
  <tr>
    <td valign="top" width="100%" align="center">
      <table width="98%" border="0">
        <tr>
          <td valign="top">
            <br>

            <p class="PageTitle"><?= gettext($sPageTitle) ?></p>
