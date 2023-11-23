<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Base\Note as BaseNote;

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
        return SystemURLs::getRootPath() . '/NoteDelete.php?NoteID=' . $this->getId();
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

    public function isVisible(int $personId): bool
    {
        return !$this->isPrivate() || $this->getPrivate() === $personId;
    }
}
