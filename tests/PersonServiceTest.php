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
    
    public function testGetPhoto()
    {

        $personService = new PersonService();
        $photoURL = $personService->photo(1);
        $this->assertRegExp("/jpeg|jpg|png|gif/",$photoURL);
    }
    
}