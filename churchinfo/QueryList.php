<?php
/*******************************************************************************
 *
 *  filename    : QueryList.php
 *  last change : 2003-01-07
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002 Deane Barker
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
require "Service/ReportingService.php";

//Set the page title
$sPageTitle = gettext("Query Listing");

$reportingService = new ReportingService();

require "Include/Header.php";?>

<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/skin/adminlte/plugins/datatables/dataTables.bootstrap.css">
<script src="<?= $sURLPath; ?>/skin/adminlte/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= $sURLPath; ?>/skin/adminlte/plugins/datatables/dataTables.bootstrap.js"></script>


<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/skin/adminlte/plugins/datatables/extensions/TableTools/css/dataTables.tableTools.css">
<script type="text/javascript" language="javascript" src="<?= $sURLPath; ?>/skin/adminlte/plugins/datatables/extensions/TableTools/js/dataTables.tableTools.min.js"></script>

<div class="box box-body">

<select id="querySelect">
<?php
$queries = $reportingService->getQuery();
foreach($queries as $query)
{
	// Filter out finance-related queries if the user doesn't have finance permissions
	if ($_SESSION['bFinance'] || !in_array($qry_ID,$aFinanceQueries))
	{
		// Display the query name and description
		echo "<option value=\"".$query['qry_ID']. "\">" . $query['qry_Name'] . " - ". $query['qry_Description']. "</option>";
	}
}
?>
</select>
<br><br>
Query Parameters:
<div id="Parameters">
    
</div>
<!--Query Text:
<textarea id="queryText" class="form-control" name="queryText" <?php if (!$_SESSION['bAdmin']) { echo "disabled"; }?>></textarea>!-->
<br>

<input type="button" class="btn btn-success" id="submitQuery" name="submitQuery" value="Submit Query"/>

</div>
<div class="box">
<div class="box-header">
<h3 class="box-title">Query Results</h3><h3 class="box-title" id="numRows"></h3>
</div>
<div class="box-body">
<table class="table" id="queryResults">
</table>
</div>
</div>
<script>
$(document).ready(function() {
    $("#querySelect").select2();
});

var queryID;

$("#querySelect").on("select2:select", function (e) { 
console.log(e);
 $("#queryText").empty();
$.ajax({
    method: "GET",
    dataType: 'json',
    url: window.CRM.root + "/api/queries/" + e.params.data.id + "/details",
    data: JSON.stringify()
    }).done(function(data){
        console.log(data);
        queryID = data.Query[0].qry_ID;
        $("#queryText").val(JSON.stringify(data.Query));
         $("#Parameters").html(" ");
        $.each(data.Parameters, function(index,value){
            $("<label for=\""+value.qrp_Alias+"\">"+value.qrp_Name+" - "+value.qrp_Description+"</label>").appendTo("#Parameters");
            $("<input class=\"queryParameter\" type=\"text\" name=\""+value.qrp_Alias+"\"></input>").appendTo("#Parameters");
            $("<br>").appendTo("#Parameters");
        })
    });
});

$("#submitQuery").on("click",function (e){
    console.log(e); 
    $("#queryResults").empty();
    $("#numRows").empty();
    var queryParameterData = $(".queryParameter").map(function() { return {"qrp_alias":this.name,"value":this.value}}).get();
    console.log(queryParameterData);
    $.ajax({
    method: "POST",
    dataType: 'json',
    url: window.CRM.root + "/api/queries/" + queryID,
    data: JSON.stringify({"queryID":queryID,"queryParameters":queryParameterData})
    }).done(function(data){
        console.log(data);
        $("#numRows").html(" ("+data.rowcount+") ");
        var thead=$("<thead>");
        var tr= $("<tr>");
        $.each(data.headerRow, function(column,value) {
            $("<th>"+value+"</th>").appendTo(tr);
        });
        thead.append(tr);
        $("#queryResults").append(thead);
        
        $.each(data.rows,function (row,value){
             var tr= $("<tr>");
            $.each(value, function(column,value) {
                $("<td>"+value+"</td>").appendTo(tr);
            });
            $("#queryResults").append(tr);
        });
        $("#queryResults").dataTable();
    });
});
</script>
<?php
require "Include/Footer.php";

?>
