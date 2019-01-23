<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\Notification\UiNotification;
use ChurchCRM\Tasks\CheckUploadSizeTask;
use ChurchCRM\Tasks\ChurchAddress;
use ChurchCRM\Tasks\ChurchNameTask;
use ChurchCRM\Tasks\EmailTask;
use ChurchCRM\Tasks\HttpsTask;
use ChurchCRM\Tasks\IntegrityCheckTask;
use ChurchCRM\Tasks\iTask;
use ChurchCRM\Tasks\LatestReleaseTask;
use ChurchCRM\Tasks\PersonClassificationDataCheck;
use ChurchCRM\Tasks\PersonGenderDataCheck;
use ChurchCRM\Tasks\PersonRoleDataCheck;
use ChurchCRM\Tasks\PrerequisiteCheckTask;
use ChurchCRM\Tasks\RegisteredTask;
use ChurchCRM\Tasks\UpdateFamilyCoordinatesTask;
use ChurchCRM\Tasks\CheckExecutionTimeTask;
use ChurchCRM\Tasks\UnsupportedDepositCheck;
use ChurchCRM\Tasks\UnsupportedPaymentDataCheck;

class TaskService
{
    /**
     * @var ObjectCollection|iTask[]
     */
    private $taskClasses;
    private $notificationClasses;

    public function __construct()
    {
        $this->taskClasses = [
            new PrerequisiteCheckTask(),
            new ChurchNameTask(),
            new ChurchAddress(),
            new EmailTask(),
            new HttpsTask(),
            new IntegrityCheckTask(),
            new LatestReleaseTask(),
            new RegisteredTask(),
            new PersonGenderDataCheck(),
            new PersonClassificationDataCheck(),
            new PersonRoleDataCheck(),
            new UpdateFamilyCoordinatesTask(),
            new CheckUploadSizeTask(),
            new CheckExecutionTimeTask(),
            new UnsupportedDepositCheck(),
            new UnsupportedPaymentDataCheck()
        ];

        $this->notificationClasses = [
          //  new LatestReleaseTask()
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

    public function getTaskNotifications()
    {
        $tasks = [];
        foreach ($this->notificationClasses as $taskClass) {
            if ($taskClass->isActive()) {
                array_push($tasks,
                    new UiNotification($taskClass->getTitle(), "wrench", $taskClass->getLink(), $taskClass->getDesc(), ($taskClass->isAdmin() ? "warning" : "info"), "12000", "bottom", "left"));
            }
        }
        return $tasks;
    }

}
