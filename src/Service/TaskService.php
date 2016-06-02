<?php

class TaskService
{
  private $baseURL;

  public function __construct()
  {
    $this->baseURL = $_SESSION['sRootPath'];
  }

  function getAdminTasks() {
    requireUserGroupMembership("bAdmin");
    $sSQL = "SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg";
    $rsConfig = mysql_query($sSQL);			// Can't use RunQuery -- not defined yet
    if ($rsConfig) {
      while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
        $$cfg_name = $cfg_value;
      }
    }

    $tasks = array();
    if ($bRegistered != 1) {
      array_push($tasks, $this->addTask("Register Software", "/Register.php", true));
    }
    if ($sChurchName == "Some Church") {
      array_push($tasks, $this->addTask("Update Church Info", "/SystemSettings.php", true));
    }
    if ($sSMTPHost == "") {
      array_push($tasks, $this->addTask("Set Email Settings", "/SystemSettings.php", true));
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
    return  array("title" => $title, "link" => $this->baseURL . $link, "admin" => $admin);
  }

}
