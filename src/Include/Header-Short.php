<?php

use ChurchCRM\dto\SystemConfig;

require_once 'Header-function.php';
require_once 'Header-Security.php';

// Turn ON output buffering
ob_start();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>

<head>
  <?php require_once 'Header-HTML-Scripts.php'; ?>
</head>

<body>

<table height="100%" width="100%" cellpadding="5" cellspacing="0" class="mx-auto">
  <tr>
    <td class="align-top text-center" width="100%">
      <table width="98%">
        <tr>
          <td class="align-top">
            <br>

            <p class="PageTitle"><?= gettext($sPageTitle) ?></p>
