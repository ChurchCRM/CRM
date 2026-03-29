<?php

use ChurchCRM\Bootstrapper;

require_once __DIR__ . '/Header-Security.php';

$localeInfo = Bootstrapper::getCurrentLocale();
?>
<!DOCTYPE html>
<html<?= $localeInfo->isRTL() ? ' dir="rtl"' : '' ?>>

<head>
  <meta http-equiv="pragma" content="no-cache">
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8">

  <?php require_once __DIR__ . '/Header-HTML-Scripts.php'; ?>
</head>

<body>
