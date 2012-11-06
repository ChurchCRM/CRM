<?php
/*******************************************************************************
 *
 *  filename    : Help.php
 *  last change : 2012-11/05
 *  description : Online help system (eventually should be XML based)
 *
 *  http://www.churchdb.org/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *  Copyright 2012 Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Initialize.php";

// Valid pages to request via the 'page' GET variable. (prevent XSS)
$aValidPages = array('About', 'Admin', 'Cart', 'Class', 'Custom', 'Family', 'Geographic', 'Finances', 'Fundraiser', 'Groups', 'Notes', 'People', 'Properties', 'Reports', 'Types', 'Canvass', 'Events','Wiki');

if (in_array($_GET['page'], $aValidPages))
{
	$sPageName = "Help/" . substr($sLanguage,0,2) . "/" . $_GET['page'] . ".php";
	if (file_exists($sPageName))
		include $sPageName;
	else
		include "Help/en/" . $_GET['page'] . ".php";
}
else
	exit;

?>
