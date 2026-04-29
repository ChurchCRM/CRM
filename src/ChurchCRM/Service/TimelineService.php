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
        foreach ($timeline as $item) {
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
            if ($event === null) {
                continue;
            }

            // Strip Quill empty markup ("<p><br /></p>") and any HTML so the
            // description renders cleanly in the timeline.
            $descText = trim(strip_tags((string) $event->getDesc()));

            $item = $this->createTimeLineItem(
                (string) $event->getId(),
                'cal',
                $event->getStart('Y-m-d H:i:s') ?: '',
                $event->getStart('Y') ?: '',
                $event->getTitle() ?: gettext('Event'),
                $event->getViewURI(),
                $descText,
                '',
                ''
            );
            $timeline[$item['key']] = $item;
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
        foreach ($timeline as $item) {
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
     * @return mixed|null
     */
    public function noteToTimelineItem(Note $dbNote)
    {
        $item = null;
        $isVisible = $this->currentUser->isAdmin() || $dbNote->isVisible($this->currentUser->getPersonId());

        if ($isVisible) {
            $displayEditedBy = gettext('Unknown');
            if ($dbNote->getDisplayEditedBy() === Person::SELF_REGISTER) {
                $displayEditedBy = gettext('Self Registration');
            } elseif ($dbNote->getDisplayEditedBy() === Person::SELF_VERIFY) {
                $displayEditedBy = gettext('Self Verification');
            } else {
                $editor = PersonQuery::create()->findPk($dbNote->getDisplayEditedBy());
                if ($editor !== null) {
                    $displayEditedBy = $editor->getFullName();
                }
            }
            $text = $dbNote->getText();
            $editLink = $dbNote->getEditLink();
            $deleteLink = 'api-delete-note-' . $dbNote->getId();
        } elseif ($this->currentUser->isAdmin() && $dbNote->isPrivate()) {
            $text = gettext('[Private Note — visible only to creator]');
            $editLink = '';
            $deleteLink = 'api-delete-note-' . $dbNote->getId();
            $displayEditedBy = gettext('Unknown');
        } else {
            return null;
        }

        $item = $this->createTimeLineItem(
            $dbNote->getId(),
            $dbNote->getType(),
            $dbNote->getDisplayEditedDate(),
            $dbNote->getDisplayEditedDate('Y'),
            gettext('by') . ' ' . $displayEditedBy,
            '',
            $text,
            $editLink ?? '',
            $deleteLink ?? ''
        );

        if ($isVisible && $dbNote->isPrivate()) {
            $item['isPrivate'] = true;
        }

        return $item;
    }

    /**
     * Timeline categories used by the view-level filter chips. Notes are
     * the high-signal user content; calendar events and automatic system
     * activity are noisier and hidden by default on long timelines.
     *
     * @var array<string, string>
     */
    public const TYPE_CATEGORIES = [
        'cal'         => 'events',
        'event'       => 'events',
        'create'      => 'system',
        'edit'        => 'system',
        'photo'       => 'system',
        'group'       => 'system',
        'verify'      => 'system',
        'verify-link' => 'system',
        'verify-URL'  => 'system',
        'user'        => 'system',
    ];

    /**
     * Map a timeline item type to one of the filter-chip categories.
     * Anything not explicitly mapped is treated as a user note.
     */
    public static function categoryForType($type): string
    {
        return self::TYPE_CATEGORIES[(string)$type] ?? 'notes';
    }

    public function createTimeLineItem(string $id, $type, string $datetime, $year, $header, $headerLink, $text, $editLink = '', $deleteLink = '')
    {
        $item['slim'] = true;
        $item['type'] = $type;
        $item['category'] = self::categoryForType($type);
        switch ($type) {
            case 'create':
                $item['style'] = 'fa-circle-plus';
                $item['color'] = 'primary';
                break;
            case 'edit':
                $item['style'] = 'fa-pencil';
                $item['color'] = 'primary';
                break;
            case 'photo':
                $item['style'] = 'fa-camera';
                $item['color'] = 'success';
                break;
            case 'group':
                $item['style'] = 'fa-users';
                $item['color'] = 'secondary';
                break;
            case 'cal':
                $item['style'] = 'fa-calendar';
                $item['color'] = 'success';
                break;
            case 'event':
                $item['style'] = 'fa-calendar-check';
                $item['color'] = 'success';
                break;
            case 'verify':
            case 'verify-link':
            case 'verify-URL':
                $item['style'] = 'fa-circle-check';
                $item['color'] = 'info';
                break;
            case 'user':
                $item['style'] = 'fa-user-secret';
                $item['color'] = 'secondary';
                break;
            default:
                $item['slim'] = false;
                $item['style'] = 'fa-note-sticky';
                $item['color'] = 'warning';
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
