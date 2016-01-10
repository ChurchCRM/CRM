<?php

class SystemService {


    function queryDatabase($queryRequest)
    {
        $returnObject = new StdClass();
        $returnObject->query = $queryRequest;
        $returnObject->rows = array();
        $result=mysql_query($queryRequest);
        $returnObject->rowcount = mysql_num_rows($result);
        while($row=mysql_fetch_array($result)) {
            array_push($returnObject->rows,$row);
        }
        return $returnObject;
        
    }

}