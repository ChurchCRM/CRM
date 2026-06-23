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

        // Only include note items when the user has Notes read permission.
        // Plain-auth (no Notes flag) sees no note items at all — notes require
        // an explicit capability grant (Notes=1 or Admin). Non-note items such
        // as calendar events and system audit entries are always included.
        if ($this->currentUser->canReadNotes()) {
            $familyNotes = NoteQuery::create()->findByFamId($familyID);
            foreach ($familyNotes as $dbNote) {
                $item = $this->noteToTimelineItem($dbNote);
                if ($item !== null) {
                    $timeline[$item['key']] = $item;
                }
            }
        }

        return $this->sortTimeline($timeline);
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

        // Plain-auth users (no Notes permission) see no note items.
        if (!$this->currentUser->canReadNotes()) {
            return $timeline;
        }

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
     * Convert a Note ORM object to a timeline item array, applying the new
     * visibility policy from #9036:
     *
     * - Public notes: visible to all authenticated users with Notes access.
     * - Private notes (author == current user): full content, edit link shown.
     * - Private notes (Admin viewing other's note): full content, edit link shown.
     *   (Admins can now read and edit any private note — old [Private Note] placeholder
     *   is removed. This is a user-visible behavior change called out in the PR.)
     * - Private notes (Notes=1 non-admin viewing other's note): omitted (return null).
     *
     * The private badge ($item['isPrivate']) is preserved so the UI can still render
     * the "Private" chip for notes the current user can see.
     *
     * @return mixed|null
     */
    public function noteToTimelineItem(Note $dbNote)
    {
        // isVisibleTo() handles: public→true, private-own→true, private-admin→true, else→false.
        if (!$dbNote->isVisibleTo($this->currentUser)) {
            return null;
        }

        $displayEditedBy = $this->resolveAuthorName($dbNote);
        $editLink   = $dbNote->getEditLink();
        $deleteLink = 'api-delete-note-' . $dbNote->getId();

        $item = $this->createTimeLineItem(
            $dbNote->getId(),
            $dbNote->getType(),
            $dbNote->getDisplayEditedDate(),
            $dbNote->getDisplayEditedDate('Y'),
            gettext('by') . ' ' . $displayEditedBy,
            '',
            $dbNote->getText(),
            $editLink,
            $deleteLink,
        );

        // Override key to use 24-hour format so krsort orders notes and events correctly
        $item['key'] = $dbNote->getDisplayEditedDate('Y-m-d H:i:s') . '-' . $dbNote->getId();

        if ($dbNote->isPrivate()) {
            $item['isPrivate'] = true;
        }

        return $item;
    }

    private function resolveAuthorName(Note $dbNote): string
    {
        if ($dbNote->getDisplayEditedBy() === Person::SELF_REGISTER) {
            return gettext('Self Registration');
        }
        if ($dbNote->getDisplayEditedBy() === Person::SELF_VERIFY) {
            return gettext('Self Verification');
        }
        $editor = PersonQuery::create()->findPk($dbNote->getDisplayEditedBy());

        return $editor !== null ? $editor->getFullName() : gettext('Unknown');
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
        'delete-note' => 'system',
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
        $item['id'] = $id;
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
            case 'delete-note':
                $item['style'] = 'fa-trash';
                $item['color'] = 'danger';
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
