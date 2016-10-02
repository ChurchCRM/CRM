<?php
/*******************************************************************************
 *
 *  filename    : GenerateSeedData.php
 *  last change : 2015-12-24
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
$sPageTitle = "Generate Seed Data";
require 'Include/Header.php';
?>

<form id="SeedForm">
<div class="box">
	<div class="box-body">
		<div class="form-group">
			<label for="Num_Families"><?= gettext("Families to Seed")?></label>
			<input type="text" name="Num_Families" id="Num_Families" value="20">
			<button type="submit" class="btn btn-primary ajax" value="<?= gettext("Generate Seed Data")?>" id="SeedSubmit" name="SeedSubmit"><?= gettext("Generate Seed Data")?></button>
		</div>
	</div>
    <div class="box box-footer">
        <button type="button" class="btn btn-primary" value="Clear Results" name="ClearResults" onclick="javascript:$('#results').empty();"><?= gettext("Clear Results")?></button>
        <div id="results"></div>
    </div>
</div>
</form>
<script type="text/javascript">
    $('#SeedForm').submit(function(event) {
		event.preventDefault();
		console.log("submit pressed");
       //process the form
       $.ajax({
         type: 'GET', // define the type of HTTP verb we want to use (POST for our form)
         url: window.CRM.root + '/api/data/seed/families/' + $("#Num_Families").val(), // the url where we want to POST
            dataType    : 'json', // what type of data do we expect back from the server
            encode      : true,
            beforeSend  : function () { 
                $('#results').empty();
                $('#results').append('<div class="text-center"><i class="fa fa-spinner fa-spin"></i><h3>Loading Seed Data</h3></div>');
            }
        })
		 .done(function(data) {
			console.log(data);
             $('#results').empty();
			$('#results').append('<pre>'+JSON.stringify(data,null,4) +'</pre>');          
		  });
		 
		

        
    });



</script>

<!-- PACE -->
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/pace/pace.min.js"></script>
<?php
require "Include/Footer.php";
?>
