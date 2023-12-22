<?php

/*******************************************************************************
 *
 *  filename    : /Include/EnvelopeFunctions.php
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2006 Michael Wilt
  *
 ******************************************************************************/

function EnvelopeAssignAllFamilies($bMembersOnly): string
{
    $sSQL = 'SELECT per_fam_ID, per_LastName FROM person_per';
    if ($bMembersOnly) {
        $sSQL .= ' WHERE per_cls_ID=' . FindMemberClassID();
    }
    $sSQL .= ' ORDER BY per_LastName';
    $rsPeople = RunQuery($sSQL);

    $ind = 0;
    $famArr = [];
    while ($aRow = mysqli_fetch_array($rsPeople)) {
        extract($aRow);
        $famArr[$ind++] = $per_fam_ID;
    }

    $famUnique = array_unique($famArr);

    $envelopeNo = 1;
    foreach ($famUnique as $oneFam) {
        $sSQL = "UPDATE family_fam SET fam_Envelope='" . $envelopeNo++ . "' WHERE fam_ID='" . $oneFam . "';";
        RunQuery($sSQL);
    }
    if ($bMembersOnly) {
        return gettext('Assigned envelope numbers to all families with at least one member.');
    } else {
        return gettext('Assigned envelope numbers to all families.');
    }
}
