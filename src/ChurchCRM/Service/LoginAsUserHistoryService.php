<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\LoginAsUserActionQuery;
use ChurchCRM\model\ChurchCRM\LoginAsUserSessionQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Portal\Auth\LoginAsUserEndReason;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * The Login as User history card (member sign-in design §6.5): the newest
 * sessions with every write made during them, on the person's page and on the
 * administrator's user page.
 */
final class LoginAsUserHistoryService
{
    public const LIMIT = 25;

    /**
     * @return list<array{id: int, actorName: string, personId: int, personName: string, started: \DateTimeInterface, ended: \DateTimeInterface|null, endReason: string|null, actions: list<array{time: \DateTimeInterface, method: string, path: string, status: int|null}>}>
     */
    public function forPerson(int $personId): array
    {
        return $this->present(LoginAsUserSessionQuery::create()->filterByPersonId($personId));
    }

    /**
     * Sessions the user ran as an administrator, and any in which their person was signed in as.
     *
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        return $this->present(LoginAsUserSessionQuery::create()->filterByActorUserId($userId)->_or()->filterByPersonId($userId));
    }

    /** @return list<array<string, mixed>> */
    private function present(LoginAsUserSessionQuery $query): array
    {
        $sessions = $query->orderByStarted(Criteria::DESC)->orderById(Criteria::DESC)->limit(self::LIMIT)->find();
        if ($sessions->count() === 0) {
            return [];
        }

        $sessionIds = [];
        $peopleIds = [];
        foreach ($sessions as $session) {
            $sessionIds[] = (int) $session->getId();
            $peopleIds[(int) $session->getPersonId()] = true;
            $peopleIds[(int) $session->getActorUserId()] = true;
        }

        $names = [];
        foreach (PersonQuery::create()->filterById(array_keys($peopleIds), Criteria::IN)->find() as $person) {
            $names[(int) $person->getId()] = $person->getFullName();
        }

        $actions = [];
        $actionRows = LoginAsUserActionQuery::create()
            ->filterBySessionId($sessionIds, Criteria::IN)
            ->orderByTime()
            ->orderById()
            ->find();
        foreach ($actionRows as $action) {
            $actions[(int) $action->getSessionId()][] = [
                'time' => $action->getTime(),
                'method' => (string) $action->getMethod(),
                'path' => (string) $action->getPath(),
                'status' => $action->getStatus() === null ? null : (int) $action->getStatus(),
            ];
        }

        $rows = [];
        foreach ($sessions as $session) {
            $actorId = (int) $session->getActorUserId();
            $personId = (int) $session->getPersonId();
            $rows[] = [
                'id' => (int) $session->getId(),
                'actorName' => $names[$actorId] ?? sprintf(gettext('User %d'), $actorId),
                'personId' => $personId,
                'personName' => $names[$personId] ?? sprintf(gettext('Person %d'), $personId),
                'started' => $session->getStarted(),
                'ended' => $session->getEnded(),
                'endReason' => LoginAsUserEndReason::tryFrom((string) $session->getEndReason())?->label(),
                'actions' => $actions[(int) $session->getId()] ?? [],
            ];
        }

        return $rows;
    }
}
