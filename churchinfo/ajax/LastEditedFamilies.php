<?php
/**
 * Created by IntelliJ IDEA.
 * User: gdawoud
 * Date: 12/22/2014
 * Time: 9:11 PM
 */
// Include the function library
require '../Include/Config.php';
require '../Include/Functions.php';

$sSQL = "select * from family_fam order by fam_DateLastEdited desc  LIMIT 10;";
$rsLastFamilies = RunQuery($sSQL);

$return_arr = array();

while ($row = mysql_fetch_array($rsLastFamilies)) {
    $row_array['id'] = $row['fam_ID'];
    $row_array['name'] = $row['fam_Name'];
    $row_array['address'] = $row['fam_Address1'];
    $row_array['city'] = $row['fam_City'];

    array_push($return_arr,$row_array);
}

echo json_encode($return_arr);




