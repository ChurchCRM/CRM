<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

class TaskService
{
  private $installedVersion;
  private $latestVersion;

  public function __construct()
  {
    $this->latestVersion =  $_SESSION['latestVersion'];
    $this->installedVersion = $_SESSION['sSoftwareInstalledVersion'];
  }

  function getAdminTasks() {
    requireUserGroupMembership("bAdmin");
    if (file_exists(SystemURLs::getDocumentRoot()."/integrityCheck.json"))
    {
      $integrityCheckData = json_decode(file_get_contents(SystemURLs::getDocumentRoot()."/integrityCheck.json"));
    }

    $tasks = array();
    if (SystemConfig::getValue("bRegistered") != 1) {
      array_push($tasks, $this->addTask(gettext("Register Software"), SystemURLs::getRootPath()."/Register.php", true));
    }

    if(!isset($_SERVER['HTTPS'])) {
      array_push($tasks, $this->addTask(gettext("Configure HTTPS"), "http://docs.churchcrm.io/en/latest/", true));
    }
    
    if (SystemConfig::getValue("sChurchName") == "Some Church") {
      array_push($tasks, $this->addTask(gettext("Update Church Info"), SystemURLs::getRootPath()."/SystemSettings.php", true));
    }

    if (SystemConfig::getValue("sChurchAddress") == "") {
      array_push($tasks, $this->addTask(gettext("Set Church Address"), SystemURLs::getRootPath()."/SystemSettings.php", true));
    }

    if (SystemConfig::getValue("sSMTPHost") == "") {
      array_push($tasks, $this->addTask(gettext("Set Email Settings"), SystemURLs::getRootPath()."/SystemSettings.php", true));
    }

    if ($this->latestVersion != null && $this->latestVersion["name"] != $this->installedVersion) {
      array_push($tasks, $this->addTask(gettext("New Release") . " " . $this->latestVersion["name"], SystemURLs::getRootPath()."/UpgradeCRM.php", true));
    }
    
    if($integrityCheckData == null || $integrityCheckData->status == "failure") {
      array_push($tasks, $this->addTask(gettext("Application Integrity Check Failed"), SystemURLs::getRootPath()."/IntegrityCheck.php", true));
    }

    return $tasks;
  }

  function getCurrentUserTasks() {
    $tasks = array();
    if ($_SESSION['bAdmin']) {
      $tasks = $this->getAdminTasks();
    }
    return $tasks;
  }

  function addTask($title, $link, $admin = false) {
    return  array("title" => $title, "link" => $link, "admin" => $admin);
  }

}
