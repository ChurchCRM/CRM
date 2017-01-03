<?php

class SystemServiceTest extends PHPUnit_Framework_TestCase
{
    protected $backupGlobals = false;
    private $SystemService;

    public function getBackupArray($type)
    {
        $_SESSION = [];
        $_SESSION['bAdmin'] = true;
        $params = new StdClass();
        $params->iArchiveType = $type;
        $results = $this->SystemService->getDatabaseBackup($params);

        return $results;
    }

    public function testBackupTypes()
    {
        $_SESSION = [];
        $_SESSION['bAdmin'] = true;
        $this->SystemService = new SystemService();
    //test GZip Backup
    $results = $this->getBackupArray(0);
        $this->assertNotEmpty($results);
        $this->assertFileExists($results->saveTo);
    //Test ZIP Backup
    //$results= $this->getBackupArray(1);
    //$this->assertNotEmpty($results);
    //$this->assertFileExists($results->saveTo);
    //Test Plain Backup
    $results = $this->getBackupArray(2);
        $this->assertNotEmpty($results);
        $this->assertFileExists($results->saveTo);
    //Test tar.gz backup
    $results = $this->getBackupArray(3);
        $this->assertNotEmpty($results);
        $this->assertFileExists($results->saveTo);
    }
}
