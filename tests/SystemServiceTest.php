<?php

class SystemServiceTest extends PHPUnit_Framework_TestCase
{
    protected $backupGlobals = FALSE;
    public function testBackup()
    {
        $params = new StdClass();
        $params->iArchiveType=3;
        $SystemService = new SystemService();
        $results = $SystemService->getDatabaseBackup("admin");
        print_r($results);
        $this->assertNotEmpty($results);   
    }
    
}