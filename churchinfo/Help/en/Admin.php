<?php
	$sPageTitle = "Administration";
	require "Include/Header.php";
?>

<div class="Help_Section">
	<div class="Help_Header">How do I add new Users?</div>
	<table width="100%" class="LightShadedBox"><tr><td>Users can be added by clicking on "Add New User" under "Admin" in the drop-down menu. A list of all non-users will appear and you can select the individual you wish to make a user. Select the rights and then click "Save".</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">What are the different rights available?</div>
	<table width="100%" class="LightShadedBox"><tr><td>
		The Rights are as follows:
		<ul>
			<li><b>Add Records:</b> This right allows records to be entered.<b>Edit Records:</b> This allows records to be changed.</li>
			<li><b>Edit Records:</b> This allows for records to be modified.</li>
			<li><b>Delete Records:</b> This allows for records to be deleted.</li>
			<li><b>Manage Properties and Classifications:</b> This allows for properties and classifications to be managed for the database.</li>
			<li><b>Manage Groups and Roles:</b> Groups can be added, edited, and deleted as well as roles edited with this option.</li>
			<li><b>Manage Donations and Finances:</b> Financial donations can be added, edited, and deleted with this option.</li>
			<li><b>View, Add, and Edit Notes:</b> Notes can be added, edited, and deleted with this option.</li>
			<li><b>Edit Self:</b> This allows editing of the user and family members only.  This option allows users to maintain their own data, especially email addresses and phone numbers which change frequently.</li>
			<li><b>Admin:</b> This option automatically selects all previous options.</li>
		</ul></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I edit Users?</div>
<table width="100%" class="LightShadedBox"><tr><td>Users can be edited by clicking on &quot;Edit Users&quot; under &quot;Admin&quot; in the drop-down
	  menu. A list of users will appear and you can select which individual you
	  wish to edit. Clicking &quot;Reset&quot; will reset the password for the next logon.
	  &quot;Edit&quot; allows the rights and style to be edited. &quot;Delete&quot; removes user
	  rights from the individual.
</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">What is the default password assigned to new Users?</div>
<table width="100%" class="LightShadedBox"><tr><td>In the subfolder Include, the file &quot;Config.php&quot;, one of the lines reads the
	  following: $sDefault_Pass = &quot;password&quot;. The word in the quotations
	  is the default password. This can be changed at any time by editing &quot;Config.php&quot;.</td></tr></table>
</div>

<?php
	require "Include/Footer.php";
?>
