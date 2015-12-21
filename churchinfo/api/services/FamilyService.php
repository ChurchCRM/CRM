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
}

?>