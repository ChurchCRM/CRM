<?php
/*******************************************************************************
*
*  filename    : Include/Header.php
*  website     : http://www.churchdb.org
*  description : page header used for most pages
*
*  Copyright 2001-2004 Phillip Hullquist, Deane Barker, Chris Gebhardt, Michael Wilt
*
*  Additional Contributors:
*  2006 Ed Davis
*
*
*  Copyright Contributors
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/

//*****************Code added for bridging MRBS***********************************************
require_once('Header-function.php');

//  
// Turn ON output buffering
ob_start();

// Top level menu index counter
$MenuFirst = 1;


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>
<head>
<?php Header_head_metatag(); ?>
</head>
<body onload="javascript:scrollToCoordinates()">
<?php 

Header_Body_scripts();

if ($iNavMethod != 2)	{
	Header_body_menu();
}
else {
	Header_body_nomenu();
}
?>