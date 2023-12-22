<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;

class TimelineService
{
    private User $currentUser;

    public function __construct()
    {
        $this->currentUser = AuthenticationManager::getCurrentUser();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getForFamily(int $familyID): array
    {
        $timeline = [];
        $familyNotes = NoteQuery::create()->findByFamId($familyID);
        foreach ($familyNotes as $dbNote) {
            $item = $this->noteToTimelineItem($dbNote);
            if ($item !== null) {
                $timeline[$item['key']] = $item;
            }
        }

        krsort($timeline);

        $sortedTimeline = [];
        foreach ($timeline as $date => $item) {
            $sortedTimeline[] = $item;
        }

        return $sortedTimeline;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function eventsForPerson(int $personID): array
    {
        $timeline = [];
        $eventsByPerson = EventAttendQuery::create()->findByPersonId($personID);
        foreach ($eventsByPerson as $personEvent) {
            $event = $personEvent->getEvent();
            if ($event != null) {
                $item = $this->createTimeLineItem(
                    $event->getId(),
                    'cal',
                    $event->getStart('Y-m-d h:i:s'),
                    $event->getTitle(),
                    '',
                    $event->getDesc(),
                    '',
                    ''
                );
                $timeline[$item['key']] = $item;
            }
        }

        return $timeline;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function notesForPerson(int $personID, ?string $noteType = null): array
    {
        $timeline = [];
        $personQuery = NoteQuery::create()
            ->filterByPerId($personID);
        if ($noteType !== null) {
            $personQuery->filterByType($noteType);
        }
        foreach ($personQuery->find() as $dbNote) {
            $item = $this->noteToTimelineItem($dbNote);
            if ($item !== null) {
                $timeline[$item['key']] = $item;
            }
        }

        return $timeline;
    }

    /**
     * @return array<string, mixed>[]
     */
    private function sortTimeline(array $timeline): array
    {
        krsort($timeline);

        $sortedTimeline = [];
        foreach ($timeline as $date => $item) {
            $sortedTimeline[] = $item;
        }

        return $sortedTimeline;
    }

    public function getNotesForPerson(int $personID): array
    {
        $timeline = $this->notesForPerson($personID, 'note');

        return $this->sortTimeline($timeline);
    }

    public function getForPerson(int $personID): array
    {
        $timeline = array_merge(
            $this->notesForPerson($personID, null),
            $this->eventsForPerson($personID)
        );

        return $this->sortTimeline($timeline);
    }

    /**
     * @param Note $dbNote
     *
     * @return mixed|null
     */
    public function noteToTimelineItem(Note $dbNote)
    {
        $item = null;
        if ($this->currentUser->isAdmin() || $dbNote->isVisible($this->currentUser->getPersonId())) {
            $displayEditedBy = gettext('Unknown');
            if ($dbNote->getDisplayEditedBy() === Person::SELF_REGISTER) {
                $displayEditedBy = gettext('Self Registration');
            } elseif ($dbNote->getDisplayEditedBy() === Person::SELF_VERIFY) {
                $displayEditedBy = gettext('Self Verification');
            } else {
                $editor = PersonQuery::create()->findPk($dbNote->getDisplayEditedBy());
                if ($editor != null) {
                    $displayEditedBy = $editor->getFullName();
                }
            }
            $item = $this->createTimeLineItem(
                $dbNote->getId(),
                $dbNote->getType(),
                $dbNote->getDisplayEditedDate(),
                $dbNote->getDisplayEditedDate('Y'),
                gettext('by') . ' ' . $displayEditedBy,
                '',
                $dbNote->getText(),
                $dbNote->getEditLink(),
                $dbNote->getDeleteLink()
            );
        }

        return $item;
    }

    public function createTimeLineItem(string $id, $type, string $datetime, $year, $header, $headerLink, $text, $editLink = '', $deleteLink = '')
    {
        $item['slim'] = true;
        $item['type'] = $type;
        switch ($type) {
            case 'create':
                $item['style'] = 'fa-plus-circle bg-blue';
                break;
            case 'edit':
                $item['style'] = 'fa-pencil bg-blue';
                break;
            case 'photo':
                $item['style'] = 'fa-camera bg-green';
                break;
            case 'group':
                $item['style'] = 'fa-users bg-gray';
                break;
            case 'cal':
                $item['style'] = 'fa-calendar bg-green';
                break;
            case 'verify':
                $item['style'] = 'fa-circle-check bg-teal';
                break;
            case 'verify-link':
                $item['style'] = 'fa-circle-check bg-teal';
                break;
            case 'verify-URL':
                $item['style'] = 'fa-circle-check bg-teal';
                break;
            case 'user':
                $item['style'] = 'fa-user-secret bg-gray';
                break;
            default:
                $item['slim'] = false;
                $item['style'] = 'fa-sticky-note bg-green';
                $item['editLink'] = $editLink;
                $item['deleteLink'] = $deleteLink;
        }
        $item['header'] = $header;
        $item['headerLink'] = $headerLink;
        $item['text'] = $text;

        $item['datetime'] = $datetime;
        $item['year'] = $year;
        $item['key'] = $datetime . '-' . $id;

        return $item;
    }
}
