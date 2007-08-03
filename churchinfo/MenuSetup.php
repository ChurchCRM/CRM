<?php
/*******************************************************************************
 *
 *  filename    : MenuEditor.php
 *  last change : 2007-06-28
 *  website     : http://www.churchdb.org
 *  copyright   : Copyright 2007 Frederick To
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// Security: User must have Manage Groups permission
if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
	exit;
}

//Set the page title
$sPageTitle = gettext("Menu Item Editor");

function Start_Menu($menu) {

	echo "Menu $menu<br>";
	GetMenu($menu, 0);
}
function GetMenu($menu, $plvl) {
	global $cnInfoCentral;
	
	$query = "SELECT mid, name, ismenu, content, uri, statustext, session_var, session_var_in_text, session_var_in_uri, url_parm_name, security_grp, active FROM menuconfig_mcf WHERE parent = '$menu' ORDER BY sortorder";
	
	$rsMenu = mysql_query($query, $cnInfoCentral);
	$item_cnt = mysql_num_rows($rsMenu);
	$idx = 1;
	$ptr = 1;
	$lvl = $plvl + 1;
	while ($aRow = mysql_fetch_array($rsMenu)) {	
		GetMenuItem($aRow, $idx, $lvl);
		if ($ptr < $item_cnt) {
			$idx++;
		}
		$ptr++;
	}
}

function GetMenuItem($aMenu,$mIdx,$lvl) {
global $sRootPath, $sRowClass;

	$sRowClass = AlternateRowStyle($sRowClass);

	$link = ($aMenu['uri'] == "") ? "&nbsp;" : $sRootPath."/".$aMenu['uri'];
	$text = $aMenu['statustext'];
	if (!is_null($aMenu['session_var'])) {
		if (($link > "") & ($aMenu['session_var_in_uri'])) {
			if (strstr($link, "?")&&true) {
				$cConnector = "&";
			} else {
				$cConnector = "?"; 
			}
			$link .= $cConnector.$aMenu['url_parm_name']."=$"."_SESSION[".$aMenu['session_var']."]";
		}
		if (($text > "") & ($aMenu['session_var_in_text'])) {
			$text .= " ".$_SESSION[$aMenu['session_var']];
		}
	}
	$sContent = $aMenu['content'];
	if (strlen($sContent) < 1) {
		$sContent = "{".$aMenu['name']."}";
	}
	if ($aMenu['active']) {
		$sContent .= "&nbsp;&nbsp;(".gettext("Active").")";
	} else {
		$sContent .= "&nbsp;&nbsp;(".gettext("Inactive").")";
	}
	if (strlen($sContent) < 1) {
		$sContent = "{".$aMenu['name']."}";
	}
	echo "<tr class=\"$sRowClass\"><td>".str_repeat("&nbsp;",$lvl*3).$sContent."</td>"
		 ."<td>".$link."</td>";
	echo "<td><a class=\"smallText\" href=\"MenuEditor.php?mid=" . $aMenu['mid'] . "\">" . gettext("Edit") . "</a></td>";
	echo "<td>&nbsp;&nbsp;<a class=\"smallText\" href=\"MenuEditor.php?mid=" . $aMenu['mid'] . "&mode=Delete\">" . gettext("Delete") . "</a>&nbsp;&nbsp;</td>";
	if ($aMenu['ismenu']) {
		$sMenuName = $aMenu['name'];
		echo "<td><a class=\"smallText\" href=\"javascript:void(0)\" onClick=\"Newwin=window.open('MenuManager.php?menu=$sMenuName','Newwin','toolbar=no,status=no,scrollbars=yes,resizable=yes,width=400,height=500')\">" . gettext("Edit List Options") . "</a></td>";
		} else {
		echo "<td>&nbsp;</td>";
	}
	echo "</tr>";
	if ($aMenu['ismenu']) {
		GetMenu($aMenu['name'],$lvl);
	}
}

$sPageTitle = "Menu Setup:";
include "Include/Header.php";

$sRowClass = "RowColorA";
echo "<table border=0>";
Start_Menu("root");
echo "</table>";

include "Include/Footer.php";
?>