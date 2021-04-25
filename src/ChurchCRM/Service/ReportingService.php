<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

class ReportingService
{

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
                $sSQL = 'SELECT qry_ID,qry_Name,qry_Description FROM query_qry where qry_ID='.InputUtils::LegacyFilterInput($qry_ID, 'int');
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

}
