<?php
/*******************************************************************************
 *
 *  filename    : Include/Header-Short.php
 *  last change : 2003-05-29
 *  description : page header (simplified version with no menubar)
 *
 *  http://www.infocentral.org/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Turn ON output buffering
ob_start();

?>
<html>

<head>
	<meta http-equiv="pragma" content="no-cache">
	<title>ChurchInfo: <?php echo $sPageTitle; ?></title>
	<link rel="stylesheet" type="text/css" href="Include/<?php echo $_SESSION['sStyle']; ?>">
</head>

<body>

<table height="100%" width="100%" border="0" cellpadding="5" cellspacing="0" align="center">
	<tr>
		<td valign="top" width="100%" align="center">
			<table width="98%" border="0">
				<tr>
					<td valign="top">
						<br>
						<p class="PageTitle"><?php echo $sPageTitle; ?></p>						
