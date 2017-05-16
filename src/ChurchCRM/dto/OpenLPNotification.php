<?php

namespace ChurchCRM\dto;


class OpenLPNotification
{
  protected $OLPAddress;
  protected $OLPUsername;
  protected $OLPPassword;
  protected $AlertText;
 
  public function __construct($OLPAddress,$OLPUsername,$OLPPassword)
  {
    $this->OLPAddress=$OLPAddress;
    $this->OLPUsername=$OLPUsername;
    $this->OLPPassword=$OLPPassword;
  }
  
  public function setAlertText($text)
  {
    $this->AlertText = (string)$text;
  }
  
  public function send()
  {
    $request = array(
        "request" => array(
            "text" =>$this->AlertText
        )
    );
    
    $url = $this->OLPAddress."/api/alert?data=".urlencode(json_encode($request));
    
    $response = file_get_contents($url);
    return $response;
  }
  
}