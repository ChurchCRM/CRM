<?php

namespace ChurchCRM\Authorization;

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\NoteRecipientQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Row-level read and write rules for protected households and confidential notes.
 *
 * A grant such as Notes or EditRecords is necessary but not sufficient: callers
 * keep their existing role and scope checks and add the checks here.
 *
 * Protected household (family_fam.fam_Confidential): readable by admins and by
 * members of the household. Persons inherit the flag from their family.
 *
 * Confidential note (note_nte.nte_Confidential): readable by the author and by
 * the users listed in note_recipient_nrc; editable and deletable by the author
 * only. Admin status grants nothing here.
 */
final class RecordAccessPolicy
{
    public function canReadFamily(User $user, int $familyId): bool
    {
        if ($familyId <= 0 || $user->isAdmin()) {
            return true;
        }
        if ($this->ownFamilyId($user) === $familyId) {
            return true;
        }

        $family = FamilyQuery::create()->findPk($familyId);

        return $family === null || !$family->isConfidential();
    }

    public function canReadPerson(User $user, int $personId): bool
    {
        if ($personId <= 0 || $user->isAdmin() || $personId === (int) $user->getPersonId()) {
            return true;
        }

        $person = PersonQuery::create()->findPk($personId);

        return $person === null || $this->canReadFamily($user, (int) $person->getFamId());
    }

    public function canSeeNote(User $user, Note $note): bool
    {
        if (!$note->isConfidential()) {
            return true;
        }
        if ($note->getEnteredBy() === $user->getId()) {
            return true;
        }

        return NoteRecipientQuery::create()
            ->filterByNoteId($note->getId())
            ->filterByUserId($user->getId())
            ->exists();
    }

    public function canModifyNote(User $user, Note $note): bool
    {
        if ($note->getEnteredBy() === $user->getId()) {
            return true;
        }

        return !$note->isConfidential() && $user->isAdmin();
    }

    /**
     * Ids of protected households the user may not read. Empty for admins.
     *
     * @return int[]
     */
    public function hiddenFamilyIds(User $user): array
    {
        if ($user->isAdmin()) {
            return [];
        }

        $ids = FamilyQuery::create()
            ->filterByConfidential(true)
            ->filterById($this->ownFamilyId($user), Criteria::NOT_EQUAL)
            ->select('Id')
            ->find()
            ->toArray();

        return array_map('intval', $ids);
    }

    public function ownFamilyId(User $user): int
    {
        $person = $user->getPerson();

        return $person === null ? 0 : (int) $person->getFamId();
    }
}
