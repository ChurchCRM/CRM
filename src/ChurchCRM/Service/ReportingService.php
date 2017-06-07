<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;

class ReportingService
{
    public function queryDatabase($queryRequest)
    {
        requireUserGroupMembership('bAdmin');
        global $cnInfoCentral;
        $returnObject = new \stdClass();
        $returnObject->query = $queryRequest;
        $returnObject->sql = $this->getQuerySQL($queryRequest->queryID, $queryRequest->queryParameters);
        $returnObject->rows = [];
        $returnObject->headerRow = null;

        $result = mysqli_query($cnInfoCentral, $returnObject->sql);
        $returnObject->rowcount = mysqli_num_rows($result);
        while ($row = mysqli_fetch_assoc($result)) {
            if (!isset($returnObject->headerRow)) {
                $returnObject->headerRow = [];
                foreach ($row as $key => $value) {
                    array_push($returnObject->headerRow, $key);
                }
            }
            array_push($returnObject->rows, $row);
        }

        return $returnObject;
    }

    public function search($searchTerm)
    {
        global $cnInfoCentral;
        $fetch = 'SELECT * from query_qry WHERE qry_Name LIKE \'%'.FilterInput($searchTerm).'%\' LIMIT 15';
        $result = mysqli_query($cnInfoCentral, $fetch);
        $reports = [];
        while ($row = mysqli_fetch_array($result)) {
            $row_array['id'] = $row['qry_ID'];
            $row_array['displayName'] = $row['qry_Name'];
            $row_array['uri'] = $this->getViewURI($row['qry_ID']);
            array_push($reports, $row_array);
        }

        return $reports;
    }

    public function getViewURI($Id)
    {
        return SystemURLs::getRootPath().'/ReportList.php';
    }

    public function getReportJSON($reports)
    {
        if ($reports) {
            return '{"reports": '.json_encode($reports).'}';
        } else {
            return false;
        }
    }

    public function getQuerySQL($qry_ID, $qry_Parameters)
    {
        requireUserGroupMembership('bAdmin');
        $sSQL = 'SELECT qry_SQL FROM query_qry where qry_ID='.FilterInput($qry_ID, 'int');
        $rsQueries = RunQuery($sSQL);
        $query = mysqli_fetch_assoc($rsQueries);
        $sql = $query['qry_SQL'];
        foreach ($qry_Parameters as $parameter) {
            $sql = str_replace('~'.$parameter->qrp_alias.'~', $parameter->value, $sql);
        }

        return $sql;
    }

    public function getQuery($qry_ID = null, $qry_Args = null)
    {
        requireUserGroupMembership('bAdmin');
        if ($qry_ID == null) {
            $sSQL = 'SELECT qry_ID,qry_Name,qry_Description FROM query_qry ORDER BY qry_Name';
            $rsQueries = RunQuery($sSQL);
            $result = [];
            while ($row = mysqli_fetch_assoc($rsQueries)) {
                array_push($result, $row);
            }

            return $result;
        } else {
            if ($qry_Args == null) {
                $sSQL = 'SELECT qry_ID,qry_Name,qry_Description FROM query_qry where qry_ID='.FilterInput($qry_ID, 'int');
                $rsQueries = RunQuery($sSQL);
                $result = [];
                while ($row = mysqli_fetch_assoc($rsQueries)) {
                    array_push($result, $row);
                }

                return $result;
            } elseif (is_array($qry_Args)) {
            }
        }
    }

    public function getQueryParameters($qry_ID = null)
    {
        requireUserGroupMembership('bAdmin');
        $sSQL = 'SELECT * FROM queryparameters_qrp WHERE qrp_qry_ID='.FilterInput($qry_ID, 'int');
        $rsQueries = RunQuery($sSQL);
        $result = [];
        while ($row = mysqli_fetch_assoc($rsQueries)) {
            if ($row['qrp_OptionSQL']) {
                $optionSQLResultArray = [];
                $optionSQLResults = RunQuery($row['qrp_OptionSQL']);
                while ($r2 = mysqli_fetch_assoc($optionSQLResults)) {
                    array_push($optionSQLResultArray, $r2);
                }
                $row['qrp_OptionSQL_Results'] = $optionSQLResultArray;
            }
            array_push($result, $row);
        }

        return $result;
    }

    public function getQueriesJSON($queries)
    {
        if ($queries) {
            return '{"queries": '.json_encode($queries).'}';
        } else {
            return false;
        }
    }
}
