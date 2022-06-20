<?php

namespace ChurchCRM\Service;

use ChurchCRM\EventAttendQuery;
use ChurchCRM\Note;
use ChurchCRM\NoteQuery;
use ChurchCRM\Person;
use ChurchCRM\PersonQuery;
use ChurchCRM\Authentication\AuthenticationManager;

class TimelineService
{
    /* @var $currentUser \ChurchCRM\User */
    private $currentUser;

    public function __construct()
    {
        $this->currentUser = AuthenticationManager::GetCurrentUser();
    }

    public function getForFamily($familyID)
    {
        $timeline = [];
        $familyNotes = NoteQuery::create()->findByFamId($familyID);
        foreach ($familyNotes as $dbNote) {
            $item = $this->noteToTimelineItem($dbNote);
            if (!is_null($item)) {
                $timeline[$item['key']] = $item;
            }
        }

        krsort($timeline);

        $sortedTimeline = [];
        foreach ($timeline as $date => $item) {
            array_push($sortedTimeline, $item);
        }

        return $sortedTimeline;
    }

    private function eventsForPerson($personID)
    {
        $timeline = [];
        $eventsByPerson = EventAttendQuery::create()->findByPersonId($personID);
        foreach ($eventsByPerson as $personEvent) {
            $event = $personEvent->getEvent();
            if ($event != null) {
                $item = $this->createTimeLineItem($event->getId(), 'cal',
                    $event->getStart('Y-m-d h:i:s'),
                    $event->getTitle(), '',
                    $event->getDesc(), '', '');
                $timeline[$item['key']] = $item;
            }
        }
        return $timeline;
    }

    private function notesForPerson($personID, $noteType)
    {
        $timeline = [];
        $personQuery = NoteQuery::create()
            ->filterByPerId($personID);
        if ($noteType != null) {
            $personQuery->filterByType($noteType);
        }
        foreach ($personQuery->find() as $dbNote) {
            $item = $this->noteToTimelineItem($dbNote);
            if (!is_null($item)) {
                $timeline[$item['key']] = $item;
            }
        }

        return $timeline;
    }

    private function sortTimeline($timeline)
    {
        krsort($timeline);

        $sortedTimeline = [];
        foreach ($timeline as $date => $item) {
            array_push($sortedTimeline, $item);
        }

        return $sortedTimeline;
    }

    public function getNotesForPerson($personID)
    {
        $timeline = $this->notesForPerson($personID, 'note');

        return $this->sortTimeline($timeline);
    }

    public function getForPerson($personID)
    {
        $timeline = array_merge(
            $this->notesForPerson($personID, null),
            $this->eventsForPerson($personID)
        );

        return $this->sortTimeline($timeline);
    }

    /**
     * @param $dbNote Note
     *
     * @return mixed|null
     */
    public function noteToTimelineItem($dbNote)
    {
        $item = null;
        if ($this->currentUser->isAdmin() || $dbNote->isVisable($this->currentUser->getPersonId())) {
            $displayEditedBy = gettext('Unknown');
            if ($dbNote->getDisplayEditedBy() == Person::SELF_REGISTER) {
                $displayEditedBy = gettext('Self Registration');
            } else if ($dbNote->getDisplayEditedBy() == Person::SELF_VERIFY) {
                $displayEditedBy = gettext('Self Verification');
            } else {
                $editor = PersonQuery::create()->findPk($dbNote->getDisplayEditedBy());
                if ($editor != null) {
                    $displayEditedBy = $editor->getFullName();
                }
            }
            $item = $this->createTimeLineItem($dbNote->getId(), $dbNote->getType(), $dbNote->getDisplayEditedDate(),
                $dbNote->getDisplayEditedDate("Y"),gettext('by') . ' ' . $displayEditedBy, '', $dbNote->getText(),
                $dbNote->getEditLink(), $dbNote->getDeleteLink());
        }

        return $item;
    }

    public function createTimeLineItem($id, $type, $datetime, $year, $header, $headerLink, $text, $editLink = '', $deleteLink = '')
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
            case 'group':
                $item['style'] = 'fa-users bg-gray';
                break;
            case 'cal':
                $item['style'] = 'fa-calendar bg-green';
                break;
            case 'verify':
                $item['style'] = 'fa-circle-check bg-teal';
            case 'verify-link':
                $item['style'] = 'fa-circle-check bg-teal';
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
        $item['key'] = $datetime.'-'.$id;

        return $item;
    }
}
