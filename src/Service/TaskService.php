<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;

class TaskService
{
  private $baseURL;
  private $installedVersion;
  private $latestVersion;

  public function __construct()
  {
    $this->baseURL = $_SESSION['sRootPath'];
    $this->latestVersion =  $_SESSION['latestVersion'];
    $this->installedVersion = $_SESSION['sSoftwareInstalledVersion'];
  }

  function getAdminTasks() {
    requireUserGroupMembership("bAdmin");
    $CRMInstallRoot = dirname(__DIR__);
    $integrityCheckData = json_decode(file_get_contents($CRMInstallRoot."/integrityCheck.json"));

    $tasks = array();
    if (SystemConfig::getValue("bRegistered") != 1) {
      array_push($tasks, $this->addTask(gettext("Register Software"), $this->baseURL."/Register.php", true));
    }

    if(!isset($_SERVER['HTTPS'])) {
      array_push($tasks, $this->addTask(gettext("Configure HTTPS"), "http://docs.churchcrm.io/en/latest/", true));
    }
    
    if (SystemConfig::getValue("sChurchName") == "Some Church") {
      array_push($tasks, $this->addTask(gettext("Update Church Info"), $this->baseURL."/SystemSettings.php", true));
    }

    if (SystemConfig::getValue("sChurchAddress") == "") {
      array_push($tasks, $this->addTask(gettext("Set Church Address"), $this->baseURL."/SystemSettings.php", true));
    }

    if (SystemConfig::getValue("sSMTPHost") == "") {
      array_push($tasks, $this->addTask(gettext("Set Email Settings"), $this->baseURL."/SystemSettings.php", true));
    }

    if ($this->latestVersion != null && $this->latestVersion["name"] != $this->installedVersion) {
      array_push($tasks, $this->addTask(gettext("New Release") . " " . $this->latestVersion["name"], $this->baseURL."/UpgradeCRM.php", true));
    }
    
    if($integrityCheckData->status == "failure") {
      array_push($tasks, $this->addTask(gettext("Application Integrity Check Failed"), $this->baseURL."/IntegrityCheck.php", true));
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
