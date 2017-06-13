<?php
define('FPDF_FONTPATH', 'font/');
require '../Include/fpdf.php';
require '../Include/Config.php';
require '../Include/Functions.php';
require '../Include/ReportFunctions.php';
require('../Include/mysql_table.php');


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
$pdf->Table('select * from person_per order by per_FirstName');
$pdf->AddPage();
//Second table: specify 3 columns
$pdf->AddCol('per_FirstName',20,'','per_FisrtName');
$pdf->AddCol('per_LastName',40,'per_LastName');
$pdf->AddCol('per_CellPhone',40,'Pop (2001)','per_CellPhone');
$prop=array('HeaderColor'=>array(255,150,100),
            'color1'=>array(210,245,255),
            'color2'=>array(255,255,210),
            'padding'=>2);
$pdf->Table('select per_FirstName, per_LastName as per_FirstName, per_CellPhone from person_per',$prop);
$pdf->Output();

?>