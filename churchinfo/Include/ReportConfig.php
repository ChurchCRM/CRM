<?php
/*******************************************************************************
 *
 *  filename    : Include/ReportsConfig.php
 *  last change : 2003-03-14
 *  description : Configure report generation
 *
 *  http://www.infocentral.org/
 *  Copyright 2003 Chris Gebhardt
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//
// Paper size for all PDF report documents
// Sizes: A3, A4, A5, Letter, Legal, or a 2-element array for custom size
//
$paperFormat = "Letter";

//
// Yearly Donation Report Letter (Exemption Letter)
//

//  You may want to comment this out if you are using custom pre-printed letterhead paper.
$sExemptionLetter_Letterhead = "../Images/church_letterhead.png";

$sExemptionLetter_Intro = "We appreciate your financial support during the past year to the Unitarian Universalist Church of Nashua, New Hampshire. The following is a statement of your donations during the past year." ;
$sExemptionLetter_EndLine = "Thank you for your kind donations.<br><br>" ;
$sExemptionLetter_Closing = "<br>Sincerely,<br>" ;
$sExemptionLetter_Author = "<br>Jon Laselle<br>Treasurer" ;
$sExemptionLetter_FooterLine = "58 Lowell St. · Nashua, NH 03064 · Tel. (603) 882-1091 · http://www.uunashua.org";
$sExemptionLetter_Signature = "../Images/signature.png";

//
// Directory Report default settings.  These can be changed at report-creation time.
//

// Settings for the optional title page
$sChurchName = "Unitarian Universalist Church of Nashua";
$sChurchAddress = "58 Lowell St.";
$sChurchCity = "Nashua";
$sChurchState = "NH";
$sChurchZip = "03064";
$sChurchPhone = "(603) 882-1091";
$sDirectoryDisclaimer = "Every effort was made to insure the accuracy of this directory.  If there are any errors or omissions, please contact the church office.\n\nThis directory is for the use of the people of $sChurchName, and the information contained in it may not be used for business or commercial purposes.";

$bDirLetterHead = "../Images/church_letterhead.png";

// Include only these classifications in the directory, comma seperated
$sDirClassifications = "1,2,4,5";
// These are the family role numbers designated as head of house
$sDirRoleHead = "1,7";
// These are the family role numbers designated as spouse
$sDirRoleSpouse = "2";
// These are the family role numbers designated as child
$sDirRoleChild = "3";

// Donation Receipt
$sDonationReceipt_Thanks = "Thank you for your kind donation to the Unitarian Universalist Church of Nashua, NH.";
$sDonationReceipt_Closing = "Thank you!";

?>
