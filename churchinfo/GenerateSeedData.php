<?php
/*******************************************************************************
 *
 *  filename    : GenerateSeedData.php
 *  last change : 2015-12-24
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
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
$sPageTitle = gettext("Generate Seed Data");
require 'Include/Header.php';
?>

<form id="SeedForm"  name="PledgeEditor">
<input type="text" name="Num_Families" id="Num_Families" value="20">
	<button type="submit" class="btn btn-primary" value="Generate Seed Data" id="SeedSubmit" name="SeedSubmit">Generate Seed Data</button>
	<button type="button" class="btn btn-primary" value="Clear Results" name="ClearResults" onclick="javascript:$('#results').empty();">Clear Results</button>
</form>
<div id="results">

</div>

<script>

$('#SeedForm').submit(function(event) {
		event.preventDefault();
		console.log("submit pressed");
        // get the form data
        // there are many ways to get this data using jQuery (you can use the class or id also)
var formData = {
	"families" : $("#Num_Families").val()
};
		console.log(JSON.stringify(formData));

       //process the form
       $.ajax({
            type        : 'POST', // define the type of HTTP verb we want to use (POST for our form)
            url         : 'api/data/seed/families', // the url where we want to POST
            data        :  JSON.stringify(formData), // our data object
            dataType    : 'json', // what type of data do we expect back from the server
            encode      : true
        })
		 .done(function(data) {
			console.log(data);
			$('#results').append('<pre>'+JSON.stringify(data,null,4) +'</pre>');          
		  });
		 
		

        
    });



</script>


<?php
require "Include/Footer.php";
?>