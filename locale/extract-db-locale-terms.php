<?php

echo <<<HEREDOC
=====================================================
========== Building locale from DB started ==========
=====================================================


HEREDOC;

$filename = '..' . DIRECTORY_SEPARATOR . 'BuildConfig.json';

if (is_file($filename)) {
    $buildConfig = file_get_contents($filename);
    $config = json_decode($buildConfig, true);

    if (empty($config['Env']['local']['database'])) {
        echo <<<HEREDOC
ERROR: The file $filename does not have local db env, check $filename.example for schema
HEREDOC;
    } else {
        $localDBEnv = $config['Env']['local']['database'];

        $db_server = $localDBEnv['server'];
        $db_port = $localDBEnv['port'];
        $db_name = $localDBEnv['database'];
        $db_username = $localDBEnv['user'];
        $db_password = $localDBEnv['password'];

        $stringsDir = 'db-strings';
        $stringFiles = [];

        $db = new PDO(
            'mysql:host=' . $db_server . ':' . $db_port . ';dbname=' . $db_name . ';charset=utf8mb4',
            $db_username,
            $db_password
        );

        $query = <<<SQL
SELECT DISTINCT ucfg_tooltip AS term, "" AS translation, "userconfig_ucfg" AS cntx FROM userconfig_ucfg
UNION ALL
SELECT DISTINCT qry_Name AS term, "" AS translation, "query_qry" AS cntx FROM query_qry
UNION ALL
SELECT DISTINCT qry_Description AS term, "" AS translation, "query_qry" AS cntx FROM query_qry
UNION ALL
SELECT DISTINCT qpo_Display AS term, "" AS translation, "queryparameteroptions_qpo" AS cntx FROM queryparameteroptions_qpo
UNION ALL
SELECT DISTINCT qrp_Name AS term, "" AS translation, "queryparameters_qrp" AS cntx FROM queryparameters_qrp
UNION ALL
SELECT DISTINCT qrp_Description AS term, "" AS translation, "queryparameters_qrp" AS cntx FROM queryparameters_qrp
SQL;

        echo "DB read complete" . PHP_EOL;

        foreach ($db->query($query) as $row) {
            $stringFile = $stringsDir . DIRECTORY_SEPARATOR . $row['cntx'] . '.php';
            if (!is_file($stringFile)) {
                file_put_contents($stringFile, "<?php\r\n", FILE_APPEND);
                $stringFiles[] = $stringFile;
            }

            $rawDBTerm = $row['term'];
            $dbTerm = addslashes($rawDBTerm);
            file_put_contents($stringFile, "gettext('" . $dbTerm . "');\r\n", FILE_APPEND);
        }

        foreach ($stringFiles as $stringFile) {
            file_put_contents($stringFile, "\r\n?>" . "\r\n", FILE_APPEND);
        }

        // Create and populate settings-countries.php
        $stringFile = $stringsDir . DIRECTORY_SEPARATOR . 'settings-countries.php';
        require '../src/ChurchCRM/data/Countries.php';
        require '../src/ChurchCRM/data/Country.php';

        file_put_contents($stringFile, "<?php\r\n", FILE_APPEND);

        foreach (ChurchCRM\data\Countries::getNames() as $country) {
            $countryTerm = addslashes($country);
            file_put_contents($stringFile, "gettext('" . $countryTerm . "');\r\n", FILE_APPEND);
        }

        file_put_contents($stringFile, "\r\n?>" . "\r\n", FILE_APPEND);

        // Create and populate settings-locales.php
        $stringFile = $stringsDir . DIRECTORY_SEPARATOR . 'settings-locales.php';
        file_put_contents($stringFile, "<?php\r\n", FILE_APPEND);

        $localesFile = file_get_contents(implode(DIRECTORY_SEPARATOR, ['..', 'src', 'locale', 'locales.json']));
        $locales = json_decode($localesFile, true);

        foreach ($locales as $key => $value) {
            file_put_contents($stringFile, "gettext('" . $key . "');\r\n", FILE_APPEND);
        }

        file_put_contents($stringFile, "\r\n?>" . "\r\n", FILE_APPEND);

        echo $stringFile . ' updated' . PHP_EOL;
    }
} else {
    echo "ERROR: The file $filename does not exist" . PHP_EOL;
}

echo <<<HEREDOC

=====================================================
==========   Building locale from DB end   ==========
=====================================================


HEREDOC;
