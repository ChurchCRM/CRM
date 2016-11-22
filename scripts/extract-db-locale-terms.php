<?php

$db_username='churchcrm';
$db_password = 'churchcrm';
$db_name ='churchcrm';
$stringsDir = "db-strings";
$stringFiles = array();

$db = new PDO('mysql:host=localhost;dbname='.$db_name.';charset=utf8mb4',$db_username ,$db_password );
$query = 'select DISTINCT cfg_tooltip as term, "" as translation, "config_cfg" as cntx from config_cfg 
union all
select DISTINCT ucfg_tooltip as term, "" as translation, "userconfig_ucfg" as cntx from userconfig_ucfg
union all
select DISTINCT qry_Name as term, "" as translation, "query_qry" as cntx   from query_qry
union all
select DISTINCT qry_Description as term, "" as translation, "query_qry" as cntx    from query_qry
union all
select DISTINCT qpo_Display as term, "" as translation, "queryparameteroptions_qpo" as cntx from queryparameteroptions_qpo
union all
select DISTINCT qrp_Name as term, "" as translation, "queryparameters_qrp" as cntx from queryparameters_qrp
union all
select DISTINCT qrp_Description term, "" as translation, "queryparameters_qrp" as cntx from queryparameters_qrp
union all
select DISTINCT content_english as term, "" as translation, "menuconfig_mcf" as cntx    from menuconfig_mcf;';
foreach ($db->query($query) as $row)
{
  $stringFile = $stringsDir."/".$row['cntx'].".php";
  if (!file_exists($stringFile))
  {
    file_put_contents($stringFile, "<?php\r\n",FILE_APPEND);
    array_push($stringFiles, $stringFile);
  }
  file_put_contents($stringFile, "gettext(\"".addslashes($row['term'])."\");\r\n",FILE_APPEND);
}
foreach ($stringFiles as $stringFile)
{
  file_put_contents($stringFile, "\r\n?>",FILE_APPEND);
}
