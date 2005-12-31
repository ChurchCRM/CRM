<?php
/*******************************************************************************
 *
 *  filename    : /Include/EnvelopeFunctions.php
 *  website     : http://www.churchdb.org
 *  copyright   : Copyright 2006 Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

function EnvelopeAssignAllFamilies ($bMembersOnly)
{
	$sSQL = "SELECT per_fam_ID, per_LastName FROM person_per";
	if ($bMembersOnly)
		$sSQL .= " WHERE per_cls_ID=" . FindMemberClassID ();
	$sSQL .= " ORDER BY per_LastName";
	$rsPeople = RunQuery ($sSQL);

	$ind = 0;
	$famArr = array ();
	while ($aRow = mysql_fetch_array($rsPeople))
	{
		extract($aRow);
		$famArr[$ind++] = $per_fam_ID;
	}

	$famUnique = array_unique ($famArr);

	$envelopeNo = 1;
	foreach ($famUnique as $oneFam) {
		$sSQL = "UPDATE family_fam SET fam_Envelope='" . $envelopeNo++ . "' WHERE fam_ID='" . $oneFam . "';";
		RunQuery ($sSQL);
	}
	if ($bMembersOnly)
		return (gettext ("Assigned envelope numbers to all families with at least one member."));
	else
		return (gettext ("Assigned envelope numbers to all families."));
}
