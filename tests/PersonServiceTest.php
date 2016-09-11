<?php

class PersonServiceTest extends PHPUnit_Framework_TestCase {
  protected $backupGlobals = FALSE;

  public function testSearch() {
    $_SESSION = array();
    $_SESSION['bAdmin'] = true;
    $personService = new PersonService();
    $results = $personService->search("admin");
    $this->assertNotEmpty($results);
  }

  public function testdeleteUploadedPhoto() {
    $_SESSION = array();
    $_SESSION['bAdmin'] = true;
    $personService = new PersonService();
    $deleted = $personService->deleteUploadedPhoto(1);
    $this->assertFalse($deleted);
  }

}
