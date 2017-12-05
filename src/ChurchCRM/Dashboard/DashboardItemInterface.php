<?php

namespace ChurchCRM\Dashboard;

interface DashboardItemInterface {
  public static function getDashboardItemName();
  public static function getDashboardItemRenderControl();
  public static function shouldInclude($PageName);
  public static function getDashboardItemValue();
}
