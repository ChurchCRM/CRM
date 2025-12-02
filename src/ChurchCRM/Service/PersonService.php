<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonVolunteerOpportunity;
use ChurchCRM\model\ChurchCRM\PersonVolunteerOpportunityQuery;
use ChurchCRM\Utils\Functions;
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
                    $familyRole .= gettext(' of the') . ' <a href="v2/family/' . $values['familyID'] . '">' . $person->getFamily()->getName() . '</a> ' . gettext('family') . ' )';
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
        $sSQL = "SELECT per_FirstName, per_LastName, per_Email, per_ID, group_grp.grp_Name, lst_OptionName
                from person_per
                    left JOIN person2group2role_p2g2r on
                  person2group2role_p2g2r.p2g2r_per_ID = person_per.per_id

                left JOIN group_grp ON
                  person2group2role_p2g2r.p2g2r_grp_ID = group_grp.grp_ID

                left JOIN list_lst ON
                  group_grp.grp_RoleListID = list_lst.lst_ID AND
                  person2group2role_p2g2r.p2g2r_rle_ID =  list_lst.lst_OptionID

              where per_email != ''

              order by per_id;";
        $rsPeopleWithEmails = Functions::runQuery($sSQL);
        $people = [];
        $lastPersonId = 0;
        $person = [];
        while ($row = mysqli_fetch_array($rsPeopleWithEmails)) {
            if ($lastPersonId != $row['per_ID']) {
                if ($lastPersonId != 0) {
                    $people[] = $person;
                }
                $person = [];
                $person['id'] = $row['per_ID'];
                $person['email'] = $row['per_Email'];
                $person['firstName'] = $row['per_FirstName'];
                $person['lastName'] = $row['per_LastName'];
            }

            $person[$row['grp_Name']] = $row['lst_OptionName'];

            if ($lastPersonId != $row['per_ID']) {
                $lastPersonId = $row['per_ID'];
            }
        }
        $people[] = $person;

        return $people;
    }

    /**
     * Assign a volunteer opportunity to a person.
     */
    public function addVolunteerOpportunity(int $personId, int $opportunityId): bool
    {
        $assignment = new PersonVolunteerOpportunity();
        $assignment->setPerID($personId);
        $assignment->setVolID($opportunityId);

        return (bool)$assignment->save();
    }

    /**
     * Remove a volunteer opportunity assignment from a person.
     */
    public function removeVolunteerOpportunity(int $personId, int $opportunityId): void
    {
        PersonVolunteerOpportunityQuery::create()
            ->filterByPerID($personId)
            ->filterByVolID($opportunityId)
            ->delete();
    }

    /**
     * Get a list of families with head of household information.
     *
     * @return array<int, string> Family list keyed by family ID, with formatted name and household head info
     */
    public function getFamilyList(string $dirRoleHead, string $dirRoleSpouse, int $classification = 0, ?string $searchTerm = null): array
    {
        if ($classification) {
            if ($searchTerm) {
                $whereClause = " WHERE per_cls_ID='" . $classification . "' AND fam_Name LIKE '%" . $searchTerm . "%' ";
            } else {
                $whereClause = " WHERE per_cls_ID='" . $classification . "' ";
            }
            $sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam LEFT JOIN person_per ON fam_ID = per_fam_ID $whereClause ORDER BY fam_Name";
        } else {
            if ($searchTerm) {
                $whereClause = " WHERE fam_Name LIKE '%" . $searchTerm . "%' ";
            } else {
                $whereClause = '';
            }
            $sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam $whereClause ORDER BY fam_Name";
        }

        $rsFamilies = Functions::runQuery($sSQL);

        // Build Criteria for Head of Household
        if (!$dirRoleHead) {
            $dirRoleHead = '1';
        }
        $head_criteria = ' per_fmr_ID = ' . $dirRoleHead;
        // If more than one role assigned to Head of Household, add OR
        $head_criteria = str_replace(',', ' OR per_fmr_ID = ', $head_criteria);
        // Add Spouse to criteria
        if (intval($dirRoleSpouse) > 0) {
            $head_criteria .= " OR per_fmr_ID = $dirRoleSpouse";
        }
        // Build array of Head of Households and Spouses with fam_ID as the key
        $sSQL = 'SELECT per_FirstName, per_fam_ID FROM person_per WHERE per_fam_ID > 0 AND (' . $head_criteria . ') ORDER BY per_fam_ID';
        $rs_head = Functions::runQuery($sSQL);
        $aHead = [];
        while ([$head_firstname, $head_famid] = mysqli_fetch_row($rs_head)) {
            if ($head_firstname && isset($aHead[$head_famid])) {
                $aHead[$head_famid] .= ' & ' . $head_firstname;
            } elseif ($head_firstname) {
                $aHead[$head_famid] = $head_firstname;
            }
        }
        $familyArray = [];
        while ($aRow = mysqli_fetch_array($rsFamilies)) {
            extract($aRow);
            $name = $fam_Name;
            if (isset($aHead[$fam_ID])) {
                $name .= ', ' . $aHead[$fam_ID];
            }
            $name .= ' ' . \FormatAddressLine($fam_Address1, $fam_City, $fam_State);

            $familyArray[$fam_ID] = $name;
        }

        return $familyArray;
    }

    /**
     * Build a family select dropdown HTML.
     *
     * @return string HTML option tags for family select dropdown
     */
    public function buildFamilySelect(int $familyId, string $dirRoleHead, string $dirRoleSpouse): string
    {
        $familyArray = $this->getFamilyList($dirRoleHead, $dirRoleSpouse);
        $html = '';
        foreach ($familyArray as $fam_ID => $fam_Data) {
            $html .= '<option value="' . $fam_ID . '"';
            if ($familyId == $fam_ID) {
                $html .= ' selected';
            }
            $html .= '>' . $fam_Data;
        }

        return $html;
    }
