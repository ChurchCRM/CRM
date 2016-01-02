<?php
/*******************************************************************************
 *
 *  filename    : RestoreDatabase.php
 *  last change : 2016-1-2
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
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
$sPageTitle = gettext("Restore Database");
require 'Include/Header.php';
?>



<form id="upload" action="upload.php" method="POST" enctype="multipart/form-data">

<fieldset>
<legend>HTML File Upload</legend>

<input type="hidden" id="MAX_FILE_SIZE" name="MAX_FILE_SIZE" value="300000" />

<div>
	<label for="fileselect">Files to upload:</label>
	<input type="file" id="fileselect" name="fileselect[]" multiple="multiple" />
	<div id="filedrag">or drop files here</div>
</div>

<div id="submitbutton">
	<button type="submit">Upload Files</button>
</div>

</fieldset>

</form>

<script type="text/javascript">
   
// file selection
function FileSelectHandler(e) {

	// cancel event and hover styling
	FileDragHover(e);

	// fetch FileList object
	var files = e.target.files || e.dataTransfer.files;

	// process all File objects
	for (var i = 0, f; f = files[i]; i++) {
		ParseFile(f);
		UploadFile(f);
	}

}

</script>

<!-- PACE -->
<script src="<?= $sURLPath; ?>/vendor/AdminLTE/plugins/pace/pace.min.js"></script>
<?php
require "Include/Footer.php";
?>