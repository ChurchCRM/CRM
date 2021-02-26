<?php
/*******************************************************************************
 *
 *  filename    : /Include/ReportFunctions.php
 *  last change : 2003-03-20
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2003 Chris Gebhardt
  *
 ******************************************************************************/

// MakeSalutation: this utility is used to figure out how to address a family
// for correspondence.
function MakeSalutationUtility($famID)
{
    // Make it put the name if there is only one individual in the family
    // Make it put two first names and the last name when there are exactly two people in the family (e.g. "Nathaniel and Jeanette Brooks")
    // Make it put two whole names where there are exactly two people with different names (e.g. "Doug Philbrook and Karen Andrews")
    // When there are more than two people in the family I don't have any way to know which people are children, so I would have to just use the family name (e.g. "Grossman Family").
    $sSQL = 'SELECT * FROM family_fam WHERE fam_ID='.$famID;
    $rsFamInfo = RunQuery($sSQL);

    if (mysqli_num_rows($rsFamInfo) == 0) {
        return 'Invalid Family'.$famID;
    }

    $aFam = mysqli_fetch_array($rsFamInfo);
    extract($aFam);

    $sSQL = 'SELECT * FROM person_per WHERE per_fam_ID='.$famID.' ORDER BY per_fmr_ID';
    $rsMembers = RunQuery($sSQL);
    $numMembers = mysqli_num_rows($rsMembers);

    $numChildren = 0;
    $indNotChild = 0;
    for ($ind = 0; $ind < $numMembers; $ind++) {
        $member = mysqli_fetch_array($rsMembers);
        extract($member);
        if ($per_fmr_ID == 3) {
            $numChildren++;
        } else {
            $aNotChildren[$indNotChild++] = $member;
        }
    }

    $numNotChildren = $numMembers - $numChildren;

    if ($numNotChildren == 1) {
        extract($aNotChildren[0]);

        return $per_FirstName.' '.$per_LastName;
    } elseif ($numNotChildren == 2) {
        $firstMember = mysqli_fetch_array($rsMembers);
        extract($aNotChildren[0]);
        $firstFirstName = $per_FirstName;
        $firstLastName = $per_LastName;
        $secondMember = mysqli_fetch_array($rsMembers);
        extract($aNotChildren[1]);
        $secondFirstName = $per_FirstName;
        $secondLastName = $per_LastName;
        if ($firstLastName == $secondLastName) {
            return $firstFirstName.' & '.$secondFirstName.' '.$firstLastName;
        } else {
            return $firstFirstName.' '.$firstLastName.' & '.$secondFirstName.' '.$secondLastName;
        }
    } else {
        return $fam_Name.' Family';
    }
}
