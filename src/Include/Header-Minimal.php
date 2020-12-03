<?php

use ChurchCRM\dto\SystemConfig;

if (SystemConfig::debugEnabled()) {
    require_once 'Header-Security.php';
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>

<head>
  <meta http-equiv="pragma" content="no-cache">
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8">

  <?php require 'Header-HTML-Scripts.php'; ?>
</head>

<body>
