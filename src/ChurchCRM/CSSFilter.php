<?php

Namespace ChurchCRM;

class CSSFilter implements \DotsUnited\BundleFu\Filter\FilterInterface {
  
  private $documentRoot;
  private $file;
  
  function __construct($DocumentRoot) {
    $this->documentRoot = $DocumentRoot;
  }
  public function filter($content) {
    $content =  preg_replace_callback ('/url\(\'\.\.\/fonts\/(fontawesome-webfont.*?)\'\)/', array($this,'processWoff'), $content);
    return $content;
  }
  protected function processImport($matches) {
    $contents = '/* --------- ' . $matches[1] . ' --------- */' . PHP_EOL.
            file_get_contents($this->documentRoot."/skin/".$matches[1]);
    return $contents;
  }
  
  protected function processWoff($matches) {
    return "url('/CRM/skin/external/font-awesome/fonts/".$matches[1]."')";
    print_r($matches);
    die();
    return "format('".$matches[1]."'),url(CRM/skin/adminlte/bootstrap/css/".$matches[2].")";


  }
  public function filterFile($content, $file, \SplFileInfo $fileInfo, $bundleUrl, $bundlePath) {
    $this->file = $fileInfo;
    //$content =  preg_replace_callback ('/format\(\'(.*?)\'\),url\((.*?)\)/', array($this,'processWoff'), $content);
    
    $content =  preg_replace_callback ('/@import url\((.*?\.css)\);/', array($this,'processImport'), $content);
    
    return $content;
  }

}