<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Notification\UiNotification;
use ChurchCRM\Tasks\CheckExecutionTimeTask;
use ChurchCRM\Tasks\CheckUploadSizeTask;
use ChurchCRM\Tasks\ChurchAddress;
use ChurchCRM\Tasks\ChurchNameTask;
use ChurchCRM\Tasks\EmailTask;
use ChurchCRM\Tasks\HttpsTask;
use ChurchCRM\Tasks\IntegrityCheckTask;
use ChurchCRM\Tasks\PersonClassificationDataCheck;
use ChurchCRM\Tasks\PersonGenderDataCheck;
use ChurchCRM\Tasks\PersonRoleDataCheck;
use ChurchCRM\Tasks\PHPPendingDeprecationVersionCheckTask;
use ChurchCRM\Tasks\PHPZipArchiveCheckTask;
use ChurchCRM\Tasks\PrerequisiteCheckTask;
use ChurchCRM\Tasks\PreUpgradeTaskInterface;
use ChurchCRM\Tasks\SecretsConfigurationCheckTask;
use ChurchCRM\Tasks\TaskInterface;
use ChurchCRM\Tasks\UnsupportedDepositCheck;
use ChurchCRM\Tasks\UpdateFamilyCoordinatesTask;

class TaskService
{
    /**
     * @var TaskInterface[]
     */
    private array $taskClasses;
    private array $notificationClasses = [
        //  new LatestReleaseTask()
    ];

    public function __construct()
    {
        $this->taskClasses = [
            new PrerequisiteCheckTask(),
            new ChurchNameTask(),
            new ChurchAddress(),
            new EmailTask(),
            new HttpsTask(),
            new IntegrityCheckTask(),
            new PersonGenderDataCheck(),
            new PersonClassificationDataCheck(),
            new PersonRoleDataCheck(),
            new UpdateFamilyCoordinatesTask(),
            new CheckUploadSizeTask(),
            new CheckExecutionTimeTask(),
            new UnsupportedDepositCheck(),
            new SecretsConfigurationCheckTask(),
            new PHPPendingDeprecationVersionCheckTask(),
            new PHPZipArchiveCheckTask(),
        ];
    }

    public function getCurrentUserTasks(): array
    {
        $tasks = [];
        foreach ($this->taskClasses as $taskClass) {
            if ($taskClass->isActive() && (!$taskClass->isAdmin() || ($taskClass->isAdmin() && AuthenticationManager::getCurrentUser()->isAdmin()))) {
                $tasks[] = [
                    'title' => $taskClass->getTitle(),
                    'link' => $taskClass->getLink(),
                    'admin' => $taskClass->isAdmin(),
                    'desc' => $taskClass->getDesc()
                ];
            }
        }

        return $tasks;
    }

    public function getTaskNotifications(): array
    {
        $tasks = [];
        foreach ($this->notificationClasses as $taskClass) {
            if ($taskClass->isActive()) {
                $tasks[] = new UiNotification($taskClass->getTitle(), 'wrench', $taskClass->getLink(), $taskClass->getDesc(), $taskClass->isAdmin() ? 'warning' : 'info', 12000, 'bottom', 'left');
            }
        }

        return $tasks;
    }

    public function getActivePreUpgradeTasks(): array
    {
        return array_filter($this->taskClasses, fn ($k): bool => $k instanceof PreUpgradeTaskInterface && $k->isActive());
    }
}
