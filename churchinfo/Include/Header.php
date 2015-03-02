<?php
/*******************************************************************************
*
*  filename    : Include/Header.php
*  website     : http://www.churchdb.org
*  description : page header used for most pages
*
*  Copyright 2001-2004 Phillip Hullquist, Deane Barker, Chris Gebhardt, Michael Wilt
*
*  LICENSE:
*  (C) Free Software Foundation, Inc.
*
*  ChurchInfo is free software; you can redistribute it and/or modify
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


?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<?php Header_head_metatag(); ?>


</head>
<body onload="javascript:scrollToCoordinates()">
<?php 

Header_body_scripts();

if ($iNavMethod != 2)	{
	Header_body_menu();
}
else {
	Header_body_nomenu();
}
?>
