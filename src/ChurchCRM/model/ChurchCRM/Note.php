<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Base\Note as BaseNote;
use ChurchCRM\model\ChurchCRM\User;

/**
 * Skeleton subclass for representing a row from the 'note_nte' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Note extends BaseNote
{
    public function setEntered($enteredBy): void
    {
        $this->setDateEntered(new \DateTimeImmutable());
        $this->setEnteredBy($enteredBy);
    }

    public function getEditLink(): string
    {
        $url = SystemURLs::getRootPath() . '/NoteEditor.php?NoteID=' . $this->getId() . '&';

        if ($this->getPerId() !== 0) {
            $url = $url . 'PersonID=' . $this->getPerId();
        } else {
            $url = $url . 'FamilyID=' . $this->getFamId();
        }

        return $url;
    }

    public function getDeleteLink(): string
    {
        return 'data-delete-note-' . $this->getId();
    }

    public function getDisplayEditedDate(string $format = 'Y-m-d h:i:s'): string
    {
        if (!empty($this->getDateLastEdited())) {
            return $this->getDateLastEdited($format);
        } else {
            return $this->getDateEntered($format);
        }
    }

    public function getDisplayEditedBy(): int
    {
        if ($this->getEditedBy() !== 0) {
            return $this->getEditedBy();
        } else {
            return $this->getEnteredBy();
        }
    }

    public function isPrivate(): bool
    {
        return $this->getPrivate() !== 0;
    }

    /**
     * Returns true if the given user may see this note.
     *
     * Rules:
     * - Public notes are visible to all authenticated users with Notes access.
     * - The note's author always sees their own private note.
     * - Admins see all private notes (canReadPrivateNotes() → isAdmin()).
     * - Other Notes=1 non-admin, non-author users cannot see another user's
     *   private note (filtered out, not 403).
     *
     * Note-level visibility is enforced here; Notes role access is enforced at
     * the route/middleware layer (NotesReadAuthMiddleware / NotesRoleAuthMiddleware).
     */
    public function isVisibleTo(User $user): bool
    {
        if (!$this->isPrivate()) {
            return true;
        }
        // Author always sees their own private note
        if ($this->getEnteredBy() === $user->getId()) {
            return true;
        }
        // Everyone else (non-admin, non-author) is denied by canReadPrivateNotes() → false.
        // Admins pass (canReadPrivateNotes() → isAdmin()). Kept as a call so a future
        // ABAC delegate rule can grant per-record access.
        return $user->canReadPrivateNotes(
            $this->getPerId() !== 0 ? (int) $this->getPerId() : null,
            $this->getFamId() !== 0 ? (int) $this->getFamId() : null,
        );
    }

    /**
     * @deprecated Use isVisibleTo(User $user) instead.
     *             This method exists only for backward compatibility.
     */
    public function isVisible(int $userId): bool
    {
        // Public notes visible to everyone
        if (!$this->isPrivate()) {
            return true;
        }
        // Private notes visible only to creator (legacy: no admin override)
        return $this->getEnteredBy() === $userId;
    }
}
