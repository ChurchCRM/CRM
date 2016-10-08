<?php

namespace ChurchCRM\Service;
use ChurchCRM\ConfigQuery;

class ConfigService
{
  function __construct() {
    //initialize the query cache.
    $value = ConfigQuery::create()
      ->setQueryKey("ConfigService")
      ->filterByName($name)
      ->findOne();
   }
  
  function getRawConfig($name)
  {
    return ConfigQuery::create()
      ->setQueryKey("ConfigService")
      ->filterByName($name)
      ->findOne()
      ->getValue();
  }
  
}