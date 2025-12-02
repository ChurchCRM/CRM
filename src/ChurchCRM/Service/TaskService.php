<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Notification\UiNotification;
use ChurchCRM\Tasks\PreUpgradeTaskInterface;
use ChurchCRM\Tasks\TaskInterface;
use ChurchCRM\Tasks\UnsupportedDepositCheck;

/**
 * Service for non-admin tasks that need to run per-page.
 * Admin tasks have been moved to AdminService and display on the admin dashboard only.
 */
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
        // Only non-admin tasks remain here
        // Admin tasks are now in AdminService and shown on admin dashboard
        $this->taskClasses = [
            new UnsupportedDepositCheck(),
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
                    'desc' => $taskClass->getDesc(),
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
