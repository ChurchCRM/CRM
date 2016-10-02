<?php

namespace ChurchCRM\Service;

class ReportingService
{

  function queryDatabase($queryRequest)
  {
    requireUserGroupMembership("bAdmin");
    $returnObject = new \stdClass();
    $returnObject->query = $queryRequest;
    $returnObject->sql = $this->getQuerySQL($queryRequest->queryID, $queryRequest->queryParameters);
    $returnObject->rows = array();
    $returnObject->headerRow = null;

    $result = mysql_query($returnObject->sql);
    $returnObject->rowcount = mysql_num_rows($result);
    while ($row = mysql_fetch_assoc($result))
    {
      if (!isset($returnObject->headerRow))
      {
        $returnObject->headerRow = array();
        foreach ($row as $key => $value)
        {
          array_push($returnObject->headerRow, $key);
        }
      }
      array_push($returnObject->rows, $row);
    }
    return $returnObject;
  }

  function search($searchTerm)
  {
    $fetch = 'SELECT * from query_qry WHERE qry_Name LIKE \'%' . FilterInput($searchTerm) . '%\' LIMIT 15';
    $result = mysql_query($fetch);
    $reports = array();
    while ($row = mysql_fetch_array($result))
    {
      $row_array['id'] = $row['qry_ID'];
      $row_array['displayName'] = $row['qry_Name'];
      $row_array['uri'] = $this->getViewURI($row['qry_ID']);
      array_push($reports, $row_array);
    }
    return $reports;
  }

  function getViewURI($Id)
  {
    //return  $_SESSION['sRootPath']."/FamilyView.php?FamilyID=".$Id;
    return $_SESSION['sRootPath'] . "/ReportList.php";
  }

  function getReportJSON($reports)
  {
    if ($reports)
    {
      return '{"reports": ' . json_encode($reports) . '}';
    }
    else
    {
      return false;
    }
  }

  function getQuerySQL($qry_ID, $qry_Parameters)
  {
    requireUserGroupMembership("bAdmin");
    $sSQL = "SELECT qry_SQL FROM query_qry where qry_ID=" . FilterInput($qry_ID,"int");
    $rsQueries = RunQuery($sSQL);
    $query = mysql_fetch_assoc($rsQueries);
    $sql = $query['qry_SQL'];
    foreach ($qry_Parameters as $parameter)
    {
      $sql = str_replace("~" . $parameter->qrp_alias . "~", $parameter->value, $sql);
    }
    return $sql;
  }

  function getQuery($qry_ID = null, $qry_Args = null)
  {
    requireUserGroupMembership("bAdmin");
    if ($qry_ID == null)
    {
      $sSQL = "SELECT qry_ID,qry_Name,qry_Description FROM query_qry ORDER BY qry_Name";
      $rsQueries = RunQuery($sSQL);
      $result = array();
      while ($row = mysql_fetch_assoc($rsQueries))
      {
        array_push($result, $row);
      }
      return $result;
    }
    else
    {
      if ($qry_Args == null)
      {
        $sSQL = "SELECT qry_ID,qry_Name,qry_Description FROM query_qry where qry_ID=" . FilterInput($qry_ID,"int");
        $rsQueries = RunQuery($sSQL);
        $result = array();
        while ($row = mysql_fetch_assoc($rsQueries))
        {
          array_push($result, $row);
        }
        return $result;
      }
      elseif (is_array($qry_Args))
      {
        
      }
    }
  }

  function getQueryParameters($qry_ID = null)
  {
    requireUserGroupMembership("bAdmin");
    $sSQL = "SELECT * FROM queryparameters_qrp WHERE qrp_qry_ID=" . FilterInput($qry_ID,"int");
    $rsQueries = RunQuery($sSQL);
    $result = array();
    while ($row = mysql_fetch_assoc($rsQueries))
    {
      if ($row['qrp_OptionSQL'])
      {
        $optionSQLResultArray = array();
        $optionSQLResults = RunQuery($row['qrp_OptionSQL']);
        while ($r2 = mysql_fetch_assoc($optionSQLResults))
        {
          array_push($optionSQLResultArray, $r2);
        }
        $row["qrp_OptionSQL_Results"] = $optionSQLResultArray;
      }
      array_push($result, $row);
    }
    return $result;
  }

  function getQueriesJSON($queries)
  {
    if ($queries)
    {
      return '{"queries": ' . json_encode($queries) . '}';
    }
    else
    {
      return false;
    }
  }

}
