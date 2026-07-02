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

        // Fetch ALL note-table rows for the family (notes AND system/audit events).
        // The note table stores both user-entered notes (type='note') and system
        // audit entries (type='create', 'edit', 'photo', 'group', 'delete-note', …).
        //
        // Visibility rules (#9036):
        //   - plain-auth (no Notes flag): system/audit items only; user notes stripped.
        //   - Notes=1 non-admin: user notes filtered by isVisibleTo() (own private notes
        //     visible, other users' private notes hidden); all system/audit items visible.
        //   - Admin: everything visible.
        $familyNotes = NoteQuery::create()->findByFamId($familyID);
        foreach ($familyNotes as $dbNote) {
            // Strip user-entered note items for plain-auth users.
            // System/audit types (create, edit, photo, …) are never gated on Notes.
            if ($dbNote->getType() === 'note' && !$this->currentUser->canReadNotes()) {
                continue;
            }
            $item = $this->noteToTimelineItem($dbNote);
            if ($item !== null) {
                $timeline[$item['key']] = $item;
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

        // Fetch note-table rows for this person, optionally filtered by type.
        // When $noteType is specified (e.g. 'note' for the public notes panel)
        // we skip the fetch entirely for plain-auth users since all rows in that
        // subset would be stripped anyway. When $noteType is null (full timeline),
        // we still fetch so that system/audit entries (type≠'note') are included
        // for plain-auth users.
        $canReadNotes = $this->currentUser->canReadNotes();
        if ($noteType === 'note' && !$canReadNotes) {
            return $timeline;
        }

        $personQuery = NoteQuery::create()
            ->filterByPerId($personID);
        if ($noteType !== null) {
            $personQuery->filterByType($noteType);
        }
        foreach ($personQuery->find() as $dbNote) {
            // Strip user-entered note items for plain-auth users.
            // System/audit types (create, edit, photo, …) bypass the Notes gate.
            if ($dbNote->getType() === 'note' && !$canReadNotes) {
                continue;
            }
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
     * Convert a Note ORM object to a timeline item array, applying the
     * visibility policy from #9036 (as corrected: canReadPrivateNotes() → isAdmin()):
     *
     * - Public notes: visible to all authenticated users with Notes access.
     * - Private notes (author == current user): full content, edit link shown.
     * - Private notes (admin viewing another user's note): full content and edit
     *   link shown (admin sees everything via canReadPrivateNotes() → isAdmin()).
     * - Private notes (Notes=1 non-admin non-author): redacted placeholder
     *   ("Private note by {author}") with no content and no edit/delete link.
     * - Private notes (plain-auth, no Notes flag): omitted entirely (return null) —
     *   note-type items are already stripped upstream before this method is reached.
     *
     * The private badge ($item['isPrivate']) is preserved so the UI can still render
     * the "Private" chip for notes the current user can see.
     *
     * @return mixed|null
     */
    public function noteToTimelineItem(Note $dbNote)
    {
        // isVisibleTo() handles: public→true, private-own→true, else→false.
        if (!$dbNote->isVisibleTo($this->currentUser)) {
            // Private note the current user cannot read. Show a redacted placeholder
            // ("Private note by {author}") to anyone with Notes access so they know a
            // private note exists here; only admins receive the delete action. Users
            // without Notes access had note items stripped upstream, so return null.
            if ($dbNote->isPrivate() && $this->currentUser->canReadNotes()) {
                return $this->redactedPrivateNoteItem($dbNote);
            }

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

    /**
     * Build a redacted timeline item for a private note the current user may NOT read.
     * Shows "Private note by {author}" with no content and no edit link. Admins also
     * get the delete action so they can moderate the note without reading it; other
     * users with Notes access see the placeholder only.
     *
     * @return array<string, mixed>
     */
    private function redactedPrivateNoteItem(Note $dbNote): array
    {
        $authorName = $this->resolveAuthorName($dbNote);
        $deleteLink = $this->currentUser->isAdmin() ? 'api-delete-note-' . $dbNote->getId() : '';

        $item = $this->createTimeLineItem(
            $dbNote->getId(),
            $dbNote->getType(),
            $dbNote->getDisplayEditedDate(),
            $dbNote->getDisplayEditedDate('Y'),
            gettext('by') . ' ' . $authorName,
            '',
            sprintf(gettext('Private note by %s'), $authorName),
            '',           // no edit link — content is not readable
            $deleteLink,  // delete only for admins (moderation)
        );

        $item['key'] = $dbNote->getDisplayEditedDate('Y-m-d H:i:s') . '-' . $dbNote->getId();
        $item['isPrivate'] = true;
        $item['redacted']  = true;

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
