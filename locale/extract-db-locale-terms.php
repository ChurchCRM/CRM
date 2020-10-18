<?php

echo "=============================================== \n";
echo "========== Building locale from DB started === \n";
echo "=============================================== \n\n";

$filename = "../BuildConfig.json";

if (file_exists($filename)) {

    $buildConfig = file_get_contents($filename);
    $config = json_decode($buildConfig, true);

    if (empty($config["Env"]["local"]["database"])) {
        echo "ERROR: The file $filename does not have local db env, check ".$filename.".example for schema\n";
    } else {
        $localDBEnv = $config["Env"]["local"]["database"];

        $db_server = $localDBEnv["server"];
        $db_port = $localDBEnv["port"];

        $db_name = $localDBEnv["database"];
        $db_username = $localDBEnv["user"];
        $db_password = $localDBEnv["password"];

        $stringsDir = 'db-strings';
        $stringFiles = [];

        $db = new PDO('mysql:host=' . $db_server . ':' . $db_port . ';dbname=' . $db_name . ';charset=utf8mb4', $db_username, $db_password);
        $query = 'select DISTINCT ucfg_tooltip as term, "" as translation, "userconfig_ucfg" as cntx from userconfig_ucfg
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
select DISTINCT qry_Name as term, "" as translation, "query_qry" as cntx from query_qry
union all
select DISTINCT qry_Description as term, "" as translation, "query_qry" as cntx from query_qry';

        echo "DB read complete \n";

        foreach ($db->query($query) as $row) {
            $stringFile = $stringsDir . '/' . $row['cntx'] . '.php';
            if (!file_exists($stringFile)) {
                file_put_contents($stringFile, "<?php\r\n", FILE_APPEND);
                array_push($stringFiles, $stringFile);
            }
            $rawDBTerm = $row['term'];
            $dbTerm = addslashes($rawDBTerm);
            file_put_contents($stringFile, "gettext('" . $dbTerm . "');\n", FILE_APPEND);
        }
        foreach ($stringFiles as $stringFile) {
            file_put_contents($stringFile, "\r\n?>", FILE_APPEND);
        }

        $stringFile = $stringsDir . '/settings-countries.php';
        require '../src/ChurchCRM/data/Countries.php';
        require '../src/ChurchCRM/data/Country.php';
        file_put_contents($stringFile, "<?php\r\n", FILE_APPEND);

        foreach (ChurchCRM\data\Countries::getNames() as $country) {
            file_put_contents($stringFile, 'gettext("' . addslashes($country) . "\");\r\n", FILE_APPEND);
        }
        file_put_contents($stringFile, "\r\n?>", FILE_APPEND);

        $stringFile = $stringsDir . '/settings-locales.php';
        file_put_contents($stringFile, "<?php\r\n", FILE_APPEND);
        $localesFile = file_get_contents("../src/locale/locales.json");
        $locales = json_decode($localesFile, true);
        foreach ($locales as $key => $value) {
            file_put_contents($stringFile, 'gettext("' . $key . "\");\r\n", FILE_APPEND);
        }
        file_put_contents($stringFile, "\r\n?>", FILE_APPEND);
        echo $stringFile . " updated";
    }
} else {
    echo "ERROR: The file $filename does not exist \n";

}
echo "\n\n=============================================== \n";
echo "========== Building locale from DB end === \n";
echo "=============================================== \n";
