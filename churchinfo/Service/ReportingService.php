<?php

class ReportingService {
    
    function queryDatabase($queryRequest)
    {
        $returnObject = new StdClass();
        $returnObject->query = $queryRequest;
        $returnObject->rows = array();
        $returnObject->headerRow = null;
        $result=mysql_query($queryRequest);
        $returnObject->rowcount = mysql_num_rows($result);
        while($row=mysql_fetch_assoc($result)) {
            if (!isset($returnObject->headerRow))
            {
                $returnObject->headerRow= array();
                foreach ($row as $key => $value)
                {
                    array_push($returnObject->headerRow,$key);
                }
            }
            array_push($returnObject->rows,$row);
        }
        return $returnObject;
        
    }
    
    function search($searchTerm) {
        $fetch = 'SELECT * from query_qry WHERE qry_Name LIKE \'%'.$searchTerm.'%\' LIMIT 15';
        $result=mysql_query($fetch);
        $reports = array();
        while($row=mysql_fetch_array($result)) {
            $row_array['id']=$row['qry_ID'];
            $row_array['displayName']=$row['qry_Name'];
            $row_array['uri'] = $this->getViewURI($row['qry_ID']);
            array_push($reports,$row_array);
        }
        return $reports;
    }
    
    function getViewURI($Id)
    {
        //return  $_SESSION['sRootPath']."/FamilyView.php?FamilyID=".$Id;
        return $_SESSION['sRootPath']."/ReportList.php";
    }
    
    function getReportJSON($reports) {
        if ($reports)
        {
            return '{"reports": ' . json_encode($reports) . '}';
        }
        else
        {
              return false;
        }
    }
    
    function getQuery($qry_ID = null, $qry_Args=null)
    {
        if ($qry_ID == null )
        {
            $sSQL = "SELECT qry_ID,qry_Name,qry_Description FROM query_qry ORDER BY qry_Name";
            $rsQueries = RunQuery($sSQL);
            $result = array();
            while ($row=mysql_fetch_assoc($rsQueries))
            {
                array_push($result,$row);
            }
            return $result;
        }
        else
        {
            if($qry_Args==null)
            {
                $sSQL = "SELECT qry_ID,qry_Name,qry_Description FROM query_qry where qry_ID=".$qry_ID;
                $rsQueries = RunQuery($sSQL);
                $result = array();
                while ($row=mysql_fetch_assoc($rsQueries))
                {
                    array_push($result,$row);
                }
                return $result;
            }
            else
            {
                
            }
            
        }
    }
    
    function getQueriesJSON($queries) {
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