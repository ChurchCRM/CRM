<?php

class SystemService {


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

}