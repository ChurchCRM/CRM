<?php

class PersonServiceTest extends PHPUnit_Framework_TestCase
{
    protected $backupGlobals = FALSE;
    public function testSearch()
    {

        $personService = new PersonService();
        $results = $personService->search("admin");
        $this->assertNotEmpty($results);   
    }

    public function testgetPerson()
    {

        $personService = new PersonService();
        $results = $personService->get(1);
        $this->assertNotEmpty($results);
    }
    
    public function testGetPhoto()
    {

        $personService = new PersonService();
        $photoURL = $personService->getPhoto(1);
        $this->assertRegExp("/jpeg|jpg|png|gif/",$photoURL);
    }

    public function testdeleteUploadedPhoto()
    {

        $personService = new PersonService();
        $deleted = $personService->deleteUploadedPhoto(1);
        $this->assertFalse($deleted);
    }
    
}