<?php
	$sPageTitle = "About Church Web CRM";
	require "Include/Header.php";
?>

<div class="Help_Section">
	<div class="Help_Header">Basic Information</div>
	<table width="100%" class="LightShadedBox">
	<tr>
		<td width="10%" nowrap><b>Version:<b></td>
		<td><?php echo $_SESSION['sChurchInfoPHPVersion']; ?></td>
	</tr>
	<tr>
		<td width="10%" nowrap><b>Release Date:</b></td>
		<td><?php echo $_SESSION['sChurchInfoPHPDate']; ?></td>
	</tr>
	<tr>
		<td width="10%" nowrap><b>License:</b></td>
		<td>GPL (Free, Open Source)</td>
	</tr>
	<tr>
		<td width="10%" nowrap><b>Homepage:</b></td>
		<td><a href="http://churchcrm.io/">http://churchcrm.io/</a></td>
	</tr>
	<tr>
		<td width="10%" nowrap><b>Help Forums:</b></td>
		<td></td>
	</tr>
	</table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">Who developed Church Web CRM and Why?</div>
	<table width="100%" class="LightShadedBox"><tr><td>
	ChurchCRM is is based on ChurchInfo which was based on InfoCentral. The software was developed by 
	a team of volunteers, in their spare time, for the purpose of providing churches and with high-quality 
	free software. If you'd like to find out more or want to help out, checkout our <a href="https://github.com/ChurchCRM/CRM">github.com repo</a>
	</td></tr></table>
</div>

<?php
	require "Include/Footer.php";
?>
