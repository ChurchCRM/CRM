<?php

namespace ChurchCRM\dto;

class MenuItem {
  private $subItems;
  private $name;
  private $contentEnglish;
  private $content;
  private $uri;
  private $statusText;
  private $securityGroup;
  private $sortOrder;
  private $icon;
  
  public function __construct($name,$contentEnglish,$content,$uri,$statusText,$securityGroup,$sortOrder,$icon) {
    $this->name = $name;
    $this->content = $content;
    $this->contentEnglish = $contentEnglish;
    $this->uri = $uri;
    $this->statusText = $statusText;
    $this->securityGroup = $securityGroup;
    $this->sortOrder = $sortOrder;
    $this->icon = $icon;
  }
  
  public function addSubMenu(MenuItem $menuItem) {
    array_push($this->subItems, $menuItem);
  }
  
  public function getURI(){ 
    return $this->uri;
  }
  
  public function getContent() {
    return $this->content;
  }
  
  public function getIcon() {
    return $this->icon;
  }
          
}
