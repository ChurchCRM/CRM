<?php 

require '../vendor/autoload.php';

use Sabre\DAV;

class CRMFamilyFilesFolder extends DAV\Collection {

  private $myPath;

  function __construct($myPath) {

    $this->myPath = $myPath;

  }

  function getChildren() {

    $children = array();
    // Loop through the directory, and create objects for each node
    foreach(scandir($this->myPath) as $node) {

      // Ignoring files staring with .
      if ($node[0]==='.') continue;
      $children[] = $this->getChild($node);

    }
    return array(
        new MyDirectory("Families")
    );
    return $children;

  }

  function getChild($name) {

      $path = $this->myPath . '/' . $name;

      // We have to throw a NotFound exception if the file didn't exist
      if (!file_exists($path)) {
        throw new DAV\Exception\NotFound('The file with name: ' . $name . ' could not be found');
      }

      // Some added security
      if ($name[0]=='.')  throw new DAV\Exception\NotFound('Access denied');

      if (is_dir($path)) {

          return new MyDirectory($path);

      } else {

          return new MyFile($path);

      }

  }

  function childExists($name) {

        return file_exists($this->myPath . '/' . $name);

  }

  function getName() {

      return basename($this->myPath);

  }

}