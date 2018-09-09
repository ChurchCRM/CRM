<?php

Namespace ChurchCRM;

class CSSFilter implements \DotsUnited\BundleFu\Filter\FilterInterface {
  
  private $documentRoot;
  
  function __construct($DocumentRoot) {
    $this->documentRoot = $DocumentRoot;
  }
  public function filter($content) {
    
    return $content;
  }
  protected function processImport($matches) {
    $contents = '/* --------- ' . $matches[1] . ' --------- */' . PHP_EOL.
            file_get_contents($this->documentRoot."/skin/".$matches[1]);
    return $contents;

  }
  public function filterFile($content, $file, \SplFileInfo $fileInfo, $bundleUrl, $bundlePath) {
    $pattern = '/@import url\((.*?\.css)\);/';
    return preg_replace_callback ($pattern, array($this,'processImport'), $content);
  }

}