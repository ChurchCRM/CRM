<?php
/*******************************************************************************
 *
 *  filename    : Graphs/funds1day.php
 *  last change : 2003-05-28
 *  description : Display pie chart of donation to funds breakdown for one day
 *
 *  http://www.infocentral.org/
 *  Copyright 2003 Michael Slemen, Chris Gebhardt
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

require "../Include/Config.php";
require "../Include/Functions.php";
require "../Include/ReportFunctions.php";

if (!$_SESSION['bFinance'])
{
	Redirect("Menu.php");
	exit;
}

$sDate = FilterInput($_GET['date'],'char',10);

// JPGraph seems to be fixed.. no longer needed
// setlocale(LC_ALL, 'C');

// Include JPGraph library and the pie chart drawing modules
LoadLib_JPGraph(pie,pie3d);

$sSQL = "SELECT fun_Name as Fund, SUM(dna_Amount) as Total
	FROM donations_don
	LEFT JOIN donationamounts_dna ON donations_don.don_ID = donationamounts_dna.dna_don_ID
	LEFT JOIN donationfund_fun ON donationamounts_dna.dna_fun_ID = donationfund_fun.fun_ID
	WHERE don_Date = '$sDate'
	GROUP BY fun_ID ORDER BY fun_Name ASC";

$result = RunQuery($sSQL);
$i = 0;

while ($row = mysql_fetch_array($result)) {
	extract($row);
	$funds[$i] = $Fund;
	$totals[$i] = $Total;
	$funds[$i] = $funds[$i] . " (" . formatNumber($Total, 'money') . ")";
	$i++;
};

// Start Graphing ---------------------------->

// Create the graph.
$graph = new PieGraph(550,200);
$graph->SetShadow();

// Set A title for the plot
$graph->title->Set(gettext("Total by Fund for") . " $sDate");
$graph->title->SetFont(FF_FONT1,FS_BOLD,16);
$graph->title->SetColor("darkblue");
$graph->legend->Pos(0.02,0.15);

// Create the bar plot
$p1 = new PiePlot3d($totals) ;
$p1->SetTheme("sand");
$p1->SetCenter(0.285);
$p1->SetSize(85);

// Adjust projection angle
$p1->SetAngle(45);

// Adjsut angle for first slice
$p1->SetStartAngle(315);

// As a shortcut you can easily explode one numbered slice with
//$p1->ExplodeSlice(1);

// Use absolute values (type==1)
$p1->SetLabelType(PIE_VALUE_ABS);

// Display the slice values
$p1->value->SetFormat($aLocaleInfo["currency_symbol"] . ' %d');
$p1->value->Show();

// Set font for legend
$p1->value->SetFont(FF_FONT1,FS_NORMAL,12);
$p1->SetLegends($funds);

// Add the plots to the graph
$graph->Add($p1);

// Display the graph
$graph->Stroke();

?>
