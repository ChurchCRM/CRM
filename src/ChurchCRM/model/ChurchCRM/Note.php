<?php

namespace ChurchCRM;

use ChurchCRM\Base\Note as BaseNote;
use ChurchCRM\dto\SystemURLs;

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
    public function setEntered($enteredBy)
    {
        $this->setDateEntered(new \DateTime());
        $this->setEnteredBy($enteredBy);
    }

    public function getEditLink()
    {
        $url = SystemURLs::getRootPath().'/NoteEditor.php?NoteID='.$this->getId().'&';

        if ($this->getPerId() != '') {
            $url = $url.'PersonID='.$this->getPerId();
        } else {
            $url = $url.'FamilyID='.$this->getFamId();
        }

        return $url;
    }

    public function getDeleteLink()
    {
        return SystemURLs::getRootPath().'/NoteDelete.php?NoteID='.$this->getId();
    }

    public function getDisplayEditedDate($format = 'Y-m-d h:i:s')
    {
        if (!empty($this->getDateLastEdited())) {
            return $this->getDateLastEdited($format);
        } else {
            return $this->getDateEntered($format);
        }
    }

    public function getDisplayEditedBy()
    {
        if ($this->getEditedBy() != '') {
            return $this->getEditedBy();
        } else {
            return $this->getEnteredBy();
        }
    }

    public function isPrivate()
    {
        return $this->getPrivate() != '0';
    }

    public function isVisable($personId)
    {
        return !$this->isPrivate() || $this->getPrivate() == $personId;
    }
}
