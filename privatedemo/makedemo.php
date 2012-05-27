<?php
/*******************************************************************************
 *
 *  filename    : makedemo.php
 *  description : Allows the user to fill out a form to receive a private demo
 *                to facilitate evaluation.  If the captcha is successful this
 *                form creates the record for this user and passes control to
 *                presentdemo.php
 *
 *  http://www.churchdb.org/
 *  Copyright 2011-2012 Michael Wilt
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
 ******************************************************************************/

require_once ('Config.php');
session_name ("privatedemo");
session_start ();
require_once ('Functions.php');

$include_path = dirname(__FILE__);

require_once ( './class.captcha_x.php');

//Was the form submitted?
if (isset($_POST["Submit"]))
{
	$firstName = $_POST['FirstName'];
	$lastName = $_POST['LastName'];
    $remoteAddr = $_SERVER['REMOTE_ADDR'];
    $Organization  = $_POST['Organization'];
	$Address1 = $_POST['Address1'];
	$Address2 = $_POST['Address2'];
	$City = $_POST['City'];
	$State = $_POST['State'];
	$Zip = $_POST['Zip'];
	$Phone = $_POST['Phone'];
	$Email = $_POST['Email'];
	$UserName = $_POST['UserName'];
	$Password = $_POST['Password'];
	$ConfirmPassword = $_POST['ConfirmPassword'];
	
    $captcha = &new captcha_x ();

    if ( ! $captcha->validate ( $_POST['captcha'])) {
        print "<h1>Incorrect verification code.</h1>";
    } else if ($Password != $ConfirmPassword) {
        print "<h1>Passwords do not match.</h1>";
    } else if ($firstName == '' or $lastName == '' or $Organization == '' or $Email == '' or $UserName == '' or $Password == '') {
    	print "<h2>Please fill out the form completely.</h2>";
    } else {
		$cnTempDB = mysql_connect($sSERVERNAME,$sUSER,$sPASSWORD)
	        or die ('Cannot connect to the MySQL server because: ' . mysql_error());
		mysql_select_db($sDATABASE)
	        or die ('Cannot select the MySQL database because: ' . mysql_error());

		$sSQL = "INSERT INTO AdminContact (";
		$sSQL .= "ac_username, ac_password, ac_ip, ac_lastname, ac_firstname, ac_organization, ";
		$sSQL .= "ac_address1, ac_address2, ac_city, ac_state, ac_zip, ac_phone, ac_email)";
		$sSQL .= "VALUES ('$UserName', '$Password', '$remoteAddr', '$lastName', '$firstName', '$Organization', ";
		$sSQL .= "'$Address1', '$Address2', '$City', '$State', '$Zip', '$Phone', '$Email')"; 
		$resIns = mysql_query ($sSQL);
		//Get the key back
		$sSQL = "SELECT MAX(ac_id) AS iNewID FROM AdminContact";
		$rsLastEntry = mysql_query($sSQL);
		extract(mysql_fetch_array($rsLastEntry));
		session_write_close ();
	        Redirect ("presentdemo.php?ContactID=".$iNewID);
		exit;
    }
} else {
	$firstName = "";
	$lastName = "";
    $remoteAddr = "";
    $Organization  = "";
	$Address1 = "";
	$Address2 = "";
	$City = "";
	$State = "";
	$Zip = "";
	$Phone = "";
	$Email = "";
	$UserName = "";
	$Password = "";
	$ConfirmPassword = "";
}
?>

<html>
<head>
<meta http-equiv="Content-Language" content="en-us">
<title>Personal demo registration</title>
</head>
<body>

<h1>Personal demo registration</h1>
This form allows you to create a personal demo on our web site.  By using this form you agree to 
the following conditions:
<p>
Any data stored in your personal demo is not private or safeguarded in any way.  The purpose of this
demo is to facilitate evalution of ChurchInfo without having to worry about other experimentors 
interfering with your evaluation.
</p>
<p>
Your personal demo site may be deleted at any time to make room for other evaluations.  We will try to
keep it around long enough so you can show everyone in your organization who needs to see it.
</p>
<p>
No warranty is expressed or implied that the ChurchInfo software is fit for any particular purpose.
This includes your personal demo site, which is just a temporary installation of ChurchInfo to facilitate
your evaluation.
</p>
<p>
Installing ChurchInfo on your own computer or server space is really not that difficult, so please
plan to download and start your own installation once this evaluation shows promise.
</p>

<form id="form_privatedemo" method="POST" action="<?php echo $_SERVER['PHP_SELF'] ?>">

<table cellpadding="3">
	<tr>
		<td><?php echo gettext("First Name:"); ?></td>
		<td><input type="text" name="FirstName" id="FirstName" value="<?php echo $firstName;?>"></td>
	</tr>
	<tr>
		<td><?php echo gettext("Last Name:"); ?></td>
		<td><input type="text" name="LastName" id="LastName" value="<?php echo $lastName;?>"></td>
	</tr>
	<tr>
		<td><?php echo gettext("Organization:"); ?></td>
		<td><input type="text" name="Organization" id="Organization" value="<?php echo $Organization;?>"></td>
	</tr>
	<tr>
		<td><?php echo gettext("Address 1:"); ?></td>
		<td><input type="text" name="Address1" id="Address1" value="<?php echo $Address1;?>"></td>
	</tr>
	<tr>
		<td><?php echo gettext("Address 2:"); ?></td>
		<td><input type="text" name="Address2" id="Address2" value="<?php echo $Address2;?>"></td>
	</tr>
	<tr>
		<td><?php echo gettext("City:"); ?></td>
		<td><input type="text" name="City" id="City" value="<?php echo $City;?>"></td>
	</tr>
	<tr>
		<td><?php echo gettext("State:"); ?></td>
		<td><input type="text" name="State" id="State" value="<?php echo $State;?>"></td>
	</tr>
	<tr>
		<td><?php echo gettext("Zip:"); ?></td>
		<td><input type="text" name="Zip" id="Zip" value="<?php echo $Zip;?>"></td>
	</tr>
	<tr>
		<td><?php echo gettext("Phone:"); ?></td>
		<td><input type="text" name="Phone" id="Phone" value="<?php echo $Phone;?>"></td>
	</tr>
	<tr>
		<td><?php echo gettext("Email:"); ?></td>
		<td><input type="text" name="Email" id="Email" value="<?php echo $Email;?>"></td>
		<td>Be sure to provide a valid email address!  If you do not answer my email I may recycle your demo very quickly.</td>
	</tr>
	<tr>
		<td><?php echo gettext("User name:"); ?></td>
		<td><input type="text" name="UserName" id="UserName" value="<?php echo $UserName;?>"></td>
		<td>Note this user name is for demo administration, not for your demo.  Log into your demo as "admin"<td>
	</tr>
	<tr>
		<td><?php echo gettext("Password:"); ?></td>
		<td><input type="password" name="Password" id="Password" value="<?php echo $Password;?>"></td>
		<td>Note this password is for demo administration, not for your demo.  Log into your demo with password "churchinfoadmin"<td>
	</tr>
	<tr>
		<td><?php echo gettext("Confirm Password:"); ?></td>
		<td><input type="password" name="ConfirmPassword" id="ConfirmPassword" value="<?php echo $ConfirmPassword;?>"></td>
	</tr>
</table>

<p><span style='font-size:12.0pt'>
This verification step prevents spam robots from messing with our private demos.
Please enter the verification code displayed below.
</span></p>

    <table id="tbl_form_privatedemo">
        <tr>
            <td>
                <img src="<?php echo "server.php?" . rand(1,65535); ?>"
                    onclick="javasript:this.src='server.php?'+Math.random();"
                    alt="CAPTCHA image">
            </td>
            <td><input maxlength="4" size="4" name="captcha" type="text"></td>
        </tr>
    </table>
    <input type="submit" name="Submit" value="Submit Form (please press once only!)">
</form>
