<?php
/*******************************************************************************
 *
 *  filename    : Graphs/donByMonth.php
 *  last change : 2003-03-20
 *  description : Display bar graph of donations by month for the past year.
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

$sSQL= "SELECT date_format(don_Date, '%m') as month, sum(dna_Amount) as Total,
	date_format(don_Date, '%Y') as year,
	date_format(don_Date, '%b %Y') as monthyear
	FROM donations_don
	LEFT JOIN donationamounts_dna ON donations_don.don_ID = donationamounts_dna.dna_don_ID
	GROUP BY monthyear
	HAVING year = '" . FilterInput($_GET["year"],'int') . "'
	ORDER BY month ASC"  ;

$result = RunQuery($sSQL);
$i = 0 ;

while ($row = mysql_fetch_array($result)) {
	extract($row) ;
	$months[$i] = strftime ("%b", mktime (0, 0, 0, $month, 1, 1978));

	// $totals[$i] = formatNumber($Total,'integer') ;
	$totals[$i] = $Total;
	//$funds[$i] = $funds[$i]." ($"."$Total)" ;
	$i++ ;
};

// JPGraph seems to be fixed.. no longer needed
// setlocale(LC_ALL, 'C');

function formatNumber_money($value)
{
	return formatNumber($value,'intmoney');
}

// Include JPGraph library and the bar graph drawing module
LoadLib_JPGraph(bar);

// Start Graphing ---------------------------->

// Create the graph and setup the basic parameters
$graph = new Graph(475,200,'auto');
$graph->img->SetMargin(90,30,40,40);
$graph->SetScale("textint");
$graph->title->SetColor("darkblue");
$graph->SetMarginColor('white');
$graph->SetShadow();

// Add some grace to the top so that the scale doesn't
// end exactly at the max value.
//$graph->yaxis->scale->SetGrace(5);

// Setup X-axis labels
$graph->xaxis->SetTickLabels($months);
$graph->xaxis->SetFont(FF_FONT1);
$graph->xaxis->SetColor('darkblue','black');

$graph->yaxis->SetLabelFormatCallback('formatNumber_money');

// Setup "hidden" y-axis by given it the same color
// as the background
$graph->yaxis->SetColor('black','black');
$graph->ygrid->SetColor('white');

// Setup graph title ands fonts
$graph->title->Set(gettext("Summary of donations for") . " $year");
$graph->title->SetFont(FF_FONT1,FS_BOLD,16);
//$graph->subtitle->Set('(With "hidden" y-axis)');

// Create a bar pot
$bplot = new BarPlot($totals);

$bplot->SetFillColor('darkblue');
$bplot->SetColor('black');
$bplot->SetWidth(0.5);
$bplot->SetShadow('darkgray');

// Setup the values that are displayed on top of each bar
$bplot->value->Show();
// Must use TTF fonts if we want text at an arbitrary angle
$bplot->value->SetFont(FF_FONT1,FS_NORMAL,8);
$bplot->value->SetFormatCallback('formatNumber');
// $bplot->value->SetFormat($aLocaleInfo["currency_symbol"] . ' %d');
// $bplot->value->SetFormat('%01.0f');
// Dark blue for positive values and darkred for negative values
$bplot->value->SetColor("darkblue","darkred");
$graph->Add($bplot);

// Finally stroke the graph
$graph->Stroke();
?>









