<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;

require_once __DIR__ . '/Header-Security.php';

$localeInfo = Bootstrapper::getCurrentLocale();

// Resolve theme attributes from user settings
$_themeUser = AuthenticationManager::getCurrentUser();
$_themeAttrs = '';
$_themeStyle = $_themeUser->getSettingValue('ui.style');
if ($_themeStyle === 'dark') {
    $_themeAttrs .= ' data-bs-theme="dark"';
}
$_themePrimary = $_themeUser->getSettingValue('ui.theme.primary');
if ($_themePrimary !== '') {
    $_themeAttrs .= ' data-bs-theme-primary="' . htmlspecialchars($_themePrimary) . '"';
}
?>
<!DOCTYPE html>
<html<?= $localeInfo->isRTL() ? ' dir="rtl"' : '' ?><?= $_themeAttrs ?>>

<head>
  <meta http-equiv="pragma" content="no-cache">
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8">

  <?php require_once __DIR__ . '/Header-HTML-Scripts.php'; ?>
</head>

<body>
