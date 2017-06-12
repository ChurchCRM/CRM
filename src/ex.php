<?php
require('mysql_table.php');
require 'Include/Config.php';
require 'Include/Functions.php';
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;



class PDF extends PDF_MySQL_Table
{
function Header()
{
	//Title
	$this->SetFont('Arial','',18);
	$this->Cell(0,6,'Assembleia Geral',0,1,'C');
	$this->Ln(10);
	//Ensure table header is output
	parent::Header();
}
}

//Connect to database
mysql_connect('localhost','root','');
mysql_select_db('srm');

$pdf=new PDF();
$pdf->AddPage();
$pdf->SetXY(10,30);
//First table: put all columns automatically

$pdf->Table('SELECT CONCAT(per_FirstName, per_LastName) as Membro, per_FriendDate as Assinatura from srm.person_per where per_cls_ID =1 ORDER BY per_FirstName');

$pdf->Output();
?>
