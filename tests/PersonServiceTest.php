<?php

class PersonServiceTest extends PHPUnit_Framework_TestCase
{
    protected $backupGlobals = false;

    public function testSearch()
    {
        $_SESSION = [];
        $personService = new PersonService();
        $results = $personService->search('admin');
        $this->assertNotEmpty($results);
    }

    public function testdeleteUploadedPhoto()
    {
        $_SESSION = [];
        $personService = new PersonService();
        $deleted = $personService->deleteUploadedPhoto(1);
        $this->assertFalse($deleted);
    }
}
