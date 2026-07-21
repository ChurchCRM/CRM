<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonVolunteerOpportunity;
use ChurchCRM\model\ChurchCRM\PersonVolunteerOpportunityQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class PersonService
{
    /**
     * @return array<mixed, array<'address'|'displayName'|'familyID'|'familyRole'|'firstName'|'id'|'lastName'|'role'|'photoURI'|'title'|'uri', mixed>>
     */
    public function search(string $searchTerm, bool $includeFamilyRole = true): array
    {
        $searchLikeString = '%' . $searchTerm . '%';
        $people = PersonQuery::create()
            ->filterByFirstName($searchLikeString, Criteria::LIKE)
            ->_or()->filterByMiddleName($searchLikeString, Criteria::LIKE)
            ->_or()->filterByLastName($searchLikeString, Criteria::LIKE)
            ->_or()->filterByEmail($searchLikeString, Criteria::LIKE)
            ->limit(15)->find();
        $return = [];
        foreach ($people as $person) {
            $values['id'] = $person->getId();
            $values['familyID'] = $person->getFamId();
            $values['firstName'] = $person->getFirstName();
            $values['lastName'] = $person->getLastName();
            $values['displayName'] = $person->getFullName();
            $values['uri'] = $person->getViewURI();
            $values['photoURI'] = $person->getPhoto()->getPhotoURI();
            $values['title'] = $person->getTitle();
            $values['address'] = $person->getAddress();
            $values['role'] = $person->getFamilyRoleName();

            if ($includeFamilyRole) {
                $familyRole = '(';
                if ($values['familyID']) {
                    if ($person->getFamilyRole()) {
                        $familyRole .= $person->getFamilyRoleName();
                    } else {
                        $familyRole .= gettext('Part');
                    }
                    $familyRole .= gettext(' of the') . ' <a href="people/family/' . $values['familyID'] . '">' . $person->getFamily()->getName() . '</a> ' . gettext('family') . ' )';
                } else {
                    $familyRole = gettext('(No assigned family)');
                }
                $values['familyRole'] = $familyRole;
            }
            $return[] = $values;
        }

        return $return;
    }

    /**
     * @return array<mixed, array<int|string, mixed>>
     */
    public function getPeopleEmailsAndGroups(): array
    {
        // Get people with emails
        $people = PersonQuery::create()
            ->filterByEmail('', Criteria::NOT_EQUAL)
            ->orderById()
            ->find();

        $result = [];
        foreach ($people as $person) {
            $personData = [
                'id' => $person->getId(),
                'email' => $person->getEmail(),
                'firstName' => $person->getFirstName(),
                'lastName' => $person->getLastName(),
            ];

            // Get group memberships for this person
            $groupMemberships = $person->getPerson2group2roleP2g2rs();
            foreach ($groupMemberships as $membership) {
                $group = $membership->getGroup();
                if ($group !== null) {
                    $roleName = '';
                    $roleList = ListOptionQuery::create()
                        ->filterById($group->getRoleListId())
                        ->filterByOptionId($membership->getRoleId())
                        ->findOne();
                    if ($roleList !== null) {
                        $roleName = $roleList->getOptionName();
                    }
                    $personData[$group->getName()] = $roleName;
                }
            }

            $result[] = $personData;
        }

        return $result;
    }

    /**
     * Returns mailing email lists grouped by classification role.
     *
     * Includes people in active (non-deactivated) families AND people with no family
     * assignment (per_fam_ID = 0), because the LEFT JOIN means a NULL family row passes
     * the DateDeactivated IS NULL predicate. People in *deactivated* families are excluded.
     *
     * Respects the iDoNotEmailPropertyId exclusion setting and appends sToEmailAddress when configured.
     *
     * TODO: Decide whether the intended scope is strictly "active-family members" (INNER JOIN or
     * explicit ->filterByFamId(0, Criteria::NOT_EQUAL)) or "everyone not in a deactivated family"
     * (current LEFT JOIN behaviour). Align the query and the docstring in a follow-up.
     *
     * @return array{all: string[], byRole: array<string, string[]>}
     */
    public function getMailingEmails(): array
    {
        $doNotEmailSet = [];
        $doNotEmailPropId = (int) SystemConfig::getValue('iDoNotEmailPropertyId');
        if ($doNotEmailPropId > 0) {
            foreach (RecordPropertyQuery::create()->filterByPropertyId($doNotEmailPropId)->find() as $r) {
                $doNotEmailSet[(int) $r->getRecordId()] = true;
            }
        }

        $roleNameMap = [];
        foreach (ListOptionQuery::create()->filterById(1)->find() as $opt) {
            $roleNameMap[(int) $opt->getOptionId()] = $opt->getOptionName();
        }

        $persons = PersonQuery::create()
            ->leftJoinWithFamily()
            ->useQuery('Family')
                ->filterByDateDeactivated(null)
            ->endUse()
            ->filterByEmail('', Criteria::NOT_EQUAL)
            ->find();

        $all = [];
        $byRole = [];
        $emailsSeen = [];

        foreach ($persons as $person) {
            if (isset($doNotEmailSet[(int) $person->getId()])) {
                continue;
            }
            $email = (string) $person->getEmail();
            if ($email === '' || isset($emailsSeen[strtolower($email)])) {
                continue;
            }
            $emailsSeen[strtolower($email)] = true;
            $all[] = $email;

            $roleName = $roleNameMap[(int) $person->getClsId()] ?? gettext('Member');
            $byRole[$roleName][] = $email;
        }

        $defaultTo = SystemConfig::getValue('sToEmailAddress');
        if ($defaultTo !== '') {
            // Use the same case-insensitive dedup strategy as the person loop above.
            $defaultToLower = strtolower($defaultTo);
            if (!isset($emailsSeen[$defaultToLower])) {
                $emailsSeen[$defaultToLower] = true;
                $all[] = $defaultTo;
            }
            // TODO: Optimization opportunity — array_map('strtolower', $roleEmails) allocates a
            // new array on each iteration. For large datasets, maintain a per-role $seenByRole
            // set (keyed by lowercase email) to make the dedup check O(1) instead of O(n).
            foreach ($byRole as &$roleEmails) {
                if (!in_array($defaultToLower, array_map('strtolower', $roleEmails), true)) {
                    $roleEmails[] = $defaultTo;
                }
            }
            unset($roleEmails);
        }

        return ['all' => $all, 'byRole' => $byRole];
    }

    /**
     * Assign a volunteer opportunity to a person.
     */
    public function addVolunteerOpportunity(int $personId, int $opportunityId): bool
    {
        $assignment = new PersonVolunteerOpportunity();
        $assignment->setPersonId($personId);
        $assignment->setVolunteerOpportunityId($opportunityId);

        return (bool)$assignment->save();
    }

    /**
     * Remove a volunteer opportunity assignment from a person.
     */
    public function removeVolunteerOpportunity(int $personId, int $opportunityId): void
    {
        PersonVolunteerOpportunityQuery::create()
            ->filterByPersonId($personId)
            ->filterByVolunteerOpportunityId($opportunityId)
            ->delete();
    }

    /**
     * Get a list of families with head of household information.
     *
     * @param bool $allowAll When true, allows loading all families without search/classification filter
     *
     * @return array<int, string> Family list keyed by family ID, with formatted name and household head info
     */
    public function getFamilyList(string $dirRoleHead, string $dirRoleSpouse, int $classification = 0, ?string $searchTerm = null, bool $allowAll = false): array
    {
        // Require minimum 2 characters for search, or classification filter, to prevent loading entire database
        $hasValidSearch = $searchTerm !== null && mb_strlen(trim($searchTerm)) >= 2;
        $hasClassification = $classification > 0;

        if (!$hasValidSearch && !$hasClassification && !$allowAll) {
            return [];
        }

        // Build family query using Propel ORM
        $familyQuery = FamilyQuery::create();

        if ($hasClassification) {
            // Get family IDs that have persons with this classification
            $familyIds = PersonQuery::create()
                ->filterByClsId((int) $classification)
                ->filterByFamId(null, Criteria::ISNOTNULL)
                ->select(['FamId'])
                ->distinct()
                ->find()
                ->toArray();

            if (empty($familyIds)) {
                return [];
            }
            $familyQuery->filterById($familyIds);
        }

        if ($hasValidSearch) {
            $familyQuery->filterByName('%' . trim($searchTerm) . '%', Criteria::LIKE);
        }

        $families = $familyQuery->orderByName()->find();

        // Build head of household roles array
        $headRoles = array_filter(array_map('intval', explode(',', $dirRoleHead ?: '1')));
        $spouseRole = max(0, (int) $dirRoleSpouse);
        if ($spouseRole > 0) {
            $headRoles[] = $spouseRole;
        }

        // Get all heads of household and spouses
        $headPersons = PersonQuery::create()
            ->filterByFmrId($headRoles)
            ->filterByFamId(null, Criteria::ISNOTNULL)
            ->filterByFamId(0, Criteria::NOT_EQUAL)
            ->orderByFamId()
            ->find();

        $aHead = [];
        foreach ($headPersons as $person) {
            $famId = $person->getFamId();
            $firstName = $person->getFirstName();
            if ($firstName) {
                if (isset($aHead[$famId])) {
                    $aHead[$famId] .= ' & ' . $firstName;
                } else {
                    $aHead[$famId] = $firstName;
                }
            }
        }

        // Build display array
        $familyArray = [];
        foreach ($families as $family) {
            $name = $family->getName();
            $famId = $family->getId();

            if (isset($aHead[$famId])) {
                $name .= ', ' . $aHead[$famId];
            }
            $name .= ' ' . $family->getAddress();

            $familyArray[$famId] = $name;
        }

        return $familyArray;
    }

    /**
     * Get count of people missing gender data.
     *
     * @return int Count of people with Gender = 0 (excluding system record)
     */
    public function getMissingGenderDataCount(): int
    {
        return PersonQuery::create()
            ->filterByGender(0)
            ->filterById(1, Criteria::NOT_EQUAL)
            ->count();
    }

    /**
     * Get count of people missing family role data.
     *
     * @return int Count of people with FmrId = 0 and assigned to a family
     */
    public function getMissingRoleDataCount(): int
    {
        return PersonQuery::create()
            ->filterByFmrId(0)
            ->filterById(1, Criteria::NOT_EQUAL)
            ->filterByFamId(null, Criteria::ISNOTNULL)
            ->filterByFamId(0, Criteria::NOT_EQUAL)
            ->count();
    }

    /**
     * Get count of people missing classification data.
     *
     * @return int Count of people with ClsId = 0 (excluding system record)
     */
    public function getMissingClassificationDataCount(): int
    {
        return PersonQuery::create()
            ->filterByClsId(0)
            ->filterById(1, Criteria::NOT_EQUAL)
            ->count();
    }
}

