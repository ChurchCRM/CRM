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

$sExemptionLetter_Institution = "<your institution name>";
$sExemptionLetter_Intro = "We appreciate your financial support during the past year to " . $sExemptionLetter_Institution . ". The following is a statement of your donations during the past year." ;
$sExemptionLetter_EndLine = "Thank you for your kind donations.<br><br>" ;
$sExemptionLetter_Closing = "<br>Sincerely,<br>" ;
$sExemptionLetter_Author = "<br>Your signature name<br>Your signature title" ;
$sExemptionLetter_FooterLine = "Your street address · Your city/state/zip · Tel. Your tel · http:Your URL";
$sExemptionLetter_Signature = "../Images/signature.png";

//
// Directory Report default settings.  These can be changed at report-creation time.
//

// Settings for the optional title page
$sChurchName = "Your church name";
$sChurchAddress = "Your church street address";
$sChurchCity = "Your city";
$sChurchState = "Your state";
$sChurchZip = "YOur zip";
$sChurchPhone = "Your phone";
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
$sDonationReceipt_Thanks = "Thank you for your kind donation to the " . $sExemptionLetter_Institution .".";
$sDonationReceipt_Closing = "Thank you!";

?>
