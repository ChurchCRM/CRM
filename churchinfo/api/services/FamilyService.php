<?php

class FamilyService {

    function search($searchTerm) {
       $fetch = 'SELECT fam_ID, fam_Name, fam_City, fam_State FROM family_fam WHERE family_fam.fam_Name LIKE \'%'.$searchTerm.'%\' LIMIT 15';
       $result=mysql_query($fetch);

        $families = array();
        while($row=mysql_fetch_array($result)) {
            $row_array['id']=$row['fam_ID'];
            $row_array['fam_name']=$row['fam_Name'];

            array_push($families,$row_array);
        }
        $this->returnFamilies($families);
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
        echo '{"families": ' . json_encode($families) . '}';
    }
	
	function getFamilyStringByEnvelope($iEnvelope)
	{
		$sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam WHERE fam_Envelope=" . $iEnvelope;
		$rsFamilies = RunQuery($sSQL);
		$familyArray = array();
		while ($aRow = mysql_fetch_array($rsFamilies)) {
			extract($aRow);
			$name = $fam_Name;
			if (isset ($aHead[$fam_ID])) 
			{
				$name .= ", " . $aHead[$fam_ID];
			}
			$name .= " " . FormatAddressLine($fam_Address1, $fam_City, $fam_State);

			$familyArray = array("fam_ID"=> $fam_ID, "Name" => $name);
		}
		echo json_encode($familyArray);
	}
	
	function setFamilyCheckingAccountDetails($tScanString,$iFamily) {
	//Set the Routing and Account Number for a family
		$routeAndAccount = $micrObj->FindRouteAndAccount ($tScanString); // use routing and account number for matching
		$sSQL = "UPDATE family_fam SET fam_scanCheck=\"" . $routeAndAccount . "\" WHERE fam_ID = " . $iFamily;
		RunQuery($sSQL);
	}
	}
}

?>