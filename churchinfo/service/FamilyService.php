<?php

class FamilyService {

    function search($searchTerm) {
       $fetch = 'SELECT fam_ID, fam_Name, fam_City, fam_State FROM family_fam WHERE family_fam.fam_Name LIKE \'%'.$searchTerm.'%\' LIMIT 15';
       $result=mysql_query($fetch);

        $families = array();
        while($row=mysql_fetch_array($result)) {
            $row_array['id']=$row['fam_ID'];
            $row_array['familyName']=$row['fam_Name'];
            $row_array['city']=$row['fam_City'];
            $row_array['displayName']=$row['fam_Name']." - ".$row['fam_City'];
			$row_array['uri'] = "FamilyView.php?FamilyID=".$row['fam_ID'];

            array_push($families,$row_array);
        }
        return $this->returnFamilies($families);
    }


    function lastEdited() {

        $sSQL = "select * from family_fam order by fam_DateLastEdited desc  LIMIT 10;";
        $rsLastFamilies = RunQuery($sSQL);

        $families = array();

        while ($row = mysql_fetch_array($rsLastFamilies)) {
            $row_array['id'] = $row['fam_ID'];
            $row_array['name'] = $row['fam_Name'];
            $row_array['address'] = $row['fam_Address1'];
            $row_array['city'] = $row['fam_City'];

            array_push($families,$row_array);
        }

        $this->returnFamilies($families);

    }

    function returnFamilies($families) {
        return '{"families": ' . json_encode($families) . '}';
    }
	
	function insertFamily($user)
    {
        $dWeddingDate = "NULL";
        $iCanvasser = 0;
        $nLatitude = 0;
        $nLongitude = 0;
        $nEnvelope = 0;
        $sSQL = "INSERT INTO family_fam (
						fam_Name,
						fam_Address1,
						fam_Address2,
						fam_City,
						fam_State,
						fam_Zip,
						fam_Country,
						fam_HomePhone,
						fam_WorkPhone,
						fam_CellPhone,
						fam_Email,
						fam_WeddingDate,
						fam_DateEntered,
						fam_EnteredBy,
						fam_SendNewsLetter,
						fam_OkToCanvass,
						fam_Canvasser,
						fam_Latitude,
						fam_Longitude,
						fam_Envelope)
					VALUES ('" .
            FilterInput($user->name->last) . "','" .
            FilterInput($user->location->street) . "','" .
            "NULL','" .
           FilterInput( $user->location->city) . "','" .
            FilterInput($user->location->state) . "','" .
           FilterInput( $user->location->zip) . "','" .
            "USA','" .
           FilterInput($user->phone) . "','" .
             "NULL','" .
            FilterInput($user->cell) . "','" .
            FilterInput($user->email) . "'," .
             date('Y-m-d', $user->registered) . ",'" .
            date("YmdHis") . "'," .
            $_SESSION['iUserID'] . "," .
            "FALSE," .
            "FALSE,'" .
            $iCanvasser . "'," .
            $nLatitude . "," .
            $nLongitude . "," .
            $nEnvelope . ")";
        RunQuery($sSQL);
        $sSQL = "SELECT MAX(fam_ID) AS iFamilyID FROM family_fam";

        $rsLastEntry = RunQuery($sSQL);
        extract(mysql_fetch_array($rsLastEntry));
        return $iFamilyID;

    }
}

?>