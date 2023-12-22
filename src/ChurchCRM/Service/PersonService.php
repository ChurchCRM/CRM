<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class PersonService
{
    /**
     * @return array<mixed, array<'address'|'displayName'|'familyID'|'familyRole'|'firstName'|'id'|'lastName'|'role'|'thumbnailURI'|'title'|'uri', mixed>>
     */
    public function search(string $searchTerm, $includeFamilyRole = true): array
    {
        $searchLikeString = '%' . $searchTerm . '%';
        $people = PersonQuery::create()->
        filterByFirstName($searchLikeString, Criteria::LIKE)->
        _or()->filterByLastName($searchLikeString, Criteria::LIKE)->
        _or()->filterByEmail($searchLikeString, Criteria::LIKE)->
        limit(15)->find();
        $return = [];
        foreach ($people as $person) {
            $values['id'] = $person->getId();
            $values['familyID'] = $person->getFamId();
            $values['firstName'] = $person->getFirstName();
            $values['lastName'] = $person->getLastName();
            $values['displayName'] = $person->getFullName();
            $values['uri'] = $person->getViewURI();
            $values['thumbnailURI'] = $person->getThumbnailURI();
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
        $rsPeopleWithEmails = RunQuery($sSQL);
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
}
