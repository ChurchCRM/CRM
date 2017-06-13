<?php
//require('mysql_table.php');
require ('fpdf.php');
require ('Config.php');
require ('Functions.php');
require ('ReportFunctions.php');
//require('../Include/mysql_table.php');


class PDF extends PDF_MySQL_Table
{
function Header()
{
	//Title
	$this->SetFont('Arial','',18);
	$this->Cell(0,6,'World populations',0,1,'C');
	$this->Ln(10);
	//Ensure table header is output
	parent::Header();
}
}

$pdf=new PDF();
$pdf->AddPage();
//First table: put all columns automatically
$pdf->Table('select per_FirstName from person_per');
$pdf->AddPage();

$pdf->Output();
?>
