<?php
/*******************************************************************************
 *
 *  filename    : Include/LoadConfigs.php
 *  website     : http://www.churchcrm.io
 *  description : global configuration
 *                   The code in this file used to be part of part of Config.php
 *
 *  Copyright 2001-2005 Phillip Hullquist, Deane Barker, Chris Gebhardt,
 *                      Michael Wilt, Timothy Dearborn
 *
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
 *  This file best viewed in a text editor with tabs stops set to 4 characters.
 *  Please configure your editor to use soft tabs (4 spaces for a tab) instead
 *  of hard tab characters.
 *
 ******************************************************************************/
if (!function_exists("mysql_failure")) {
  function mysql_failure($message)
  {
    require("Include/HeaderNotLoggedIn.php");
    ?>
    <div class='container'>
      <h3>ChurchCRM – Setup failure</h3>

      <div class='alert alert-danger text-center' style='margin-top: 20px;'>
        <?= $message ?>
      </div>
    </div>
    <?php
    require("Include/FooterNotLoggedIn.php");
    exit();
  }
}

// Establish the database connection
$cnInfoCentral = mysql_connect($sSERVERNAME, $sUSER, $sPASSWORD)
or mysql_failure("Could not connect to MySQL on <strong>" . $sSERVERNAME . "</strong> as <strong>" . $sUSER . "</strong>. Please check the settings in <strong>include/Config.php</strong>.");

mysql_select_db($sDATABASE)
or mysql_failure("Could not connect to the MySQL database <strong>" . $sDATABASE . "</strong>. Please check the settings in <strong>include/Config.php</strong>.");

$sql = "SHOW TABLES FROM `$sDATABASE`";
$tablecheck = mysql_num_rows(mysql_query($sql));

if (!$tablecheck) {
  $query = '';
  $restoreQueries = file(dirname(__file__). '/../mysql/install/Install.sql', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($restoreQueries as $line) {
    if ($line != '' && strpos($line, '--') === false) {
      $query .= " $line";
      if (substr($query, -1) == ';') {
        mysql_query($query);
        $query = '';
      }
    }
  }
}

// Initialize the session
session_name('CRM@' . $sRootPath);
session_start();

// Avoid consecutive slashes when $sRootPath = '/'
if (strlen($sRootPath) < 2) $sRootPath = '';

// Some webhosts make it difficult to use DOCUMENT_ROOT.  Define our own!
$sDocumentRoot = dirname(dirname(__FILE__));

$version = mysql_fetch_row(mysql_query("SELECT version()"));

if (substr($version[0], 0, 3) >= "4.1") {
  mysql_query("SET NAMES 'utf8'");
}

// Read values from config table into local variables
// **************************************************
$sSQL = "SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value "
  . "FROM config_cfg WHERE cfg_section='General'";
$rsConfig = mysql_query($sSQL);         // Can't use RunQuery -- not defined yet
if ($rsConfig) {
  while (list($cfg_name, $value) = mysql_fetch_row($rsConfig)) {
    $$cfg_name = $value;
  }
}

if (isset($_SESSION['iUserID'])) {      // Not set on Login.php
  // Load user variables from user config table.
  // **************************************************
  $sSQL = "SELECT ucfg_name, ucfg_value AS value "
    . "FROM userconfig_ucfg WHERE ucfg_per_ID='" . $_SESSION['iUserID'] . "'";
  $rsConfig = mysql_query($sSQL);     // Can't use RunQuery -- not defined yet
  if ($rsConfig) {
    while (list($ucfg_name, $value) = mysql_fetch_row($rsConfig)) {
      $$ucfg_name = $value;
      $_SESSION[$ucfg_name] = $value;
    }
  }
}

$sMetaRefresh = '';  // Initialize to empty

require_once("winlocalelist.php");

if (!function_exists("stripos")) {
  function stripos($str, $needle)
  {
    return strpos(strtolower($str), strtolower($needle));
  }
}

if (!(stripos(php_uname('s'), "windows") === false)) {
  $sLanguage = $lang_map_windows[strtolower($sLanguage)];
}

$sLang_Code = $sLanguage;

putenv("LANG=$sLang_Code");
setlocale(LC_ALL, $sLang_Code, $sLang_Code . ".utf8", $sLang_Code . ".UTF8", $sLang_Code . ".utf-8", $sLang_Code . ".UTF-8");

if (isset($sTimeZone)) {
  date_default_timezone_set($sTimeZone);
}

// Get numeric and monetary locale settings.
$aLocaleInfo = localeconv();

// This is needed to avoid some bugs in various libraries like fpdf.
setlocale(LC_NUMERIC, 'C');

// patch some missing data for Italian.  This shouldn't be necessary!
if ($sLanguage == 'it_IT') {
  $aLocaleInfo['thousands_sep'] = '.';
  $aLocaleInfo['frac_digits'] = '2';
}

if (function_exists('bindtextdomain')) {
  $domain = 'messages';
  $sLocaleDir = dirname(__FILE__). '/../locale';

  bind_textdomain_codeset($domain, 'UTF-8');
  bindtextdomain($domain, $sLocaleDir);
  textdomain($domain);
}
?>
