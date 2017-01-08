<?php

namespace ChurchCRM\Service;

use ChurchCRM\Tasks\ChurchAddress;
use ChurchCRM\Tasks\ChurchNameTask;
use ChurchCRM\Tasks\EmailTask;
use ChurchCRM\Tasks\FamilyWorkPhoneTask;
use ChurchCRM\Tasks\HttpsTask;
use ChurchCRM\Tasks\IntegrityCheckTask;
use ChurchCRM\Tasks\iTask;
use ChurchCRM\Tasks\LatestReleaseTask;
use ChurchCRM\Tasks\RegisteredTask;
use ChurchCRM\Tasks\PersonGenderDataCheck;

class TaskService
{
  /**
   * @var ObjectCollection|iTask[]
   */
  private $taskClasses;

  public function __construct()
  {

    $this->taskClasses = [
      new ChurchNameTask(),
      new ChurchAddress(),
      new EmailTask(),
      new HttpsTask(),
      new IntegrityCheckTask(),
      new LatestReleaseTask(),
      new RegisteredTask(),
      new FamilyWorkPhoneTask(),
      new PersonGenderDataCheck()
    ];
  }

  public function getCurrentUserTasks()
  {
    $tasks = [];
    foreach ($this->taskClasses as $taskClass) {
      if ($taskClass->isActive()) {
        array_push($tasks, ['title' => $taskClass->getTitle(),
          'link' => $taskClass->getLink(),
          'admin' => $taskClass->isAdmin(),
          'desc' => $taskClass->getDesc()]);
      }
    }
    return $tasks;
  }

}
