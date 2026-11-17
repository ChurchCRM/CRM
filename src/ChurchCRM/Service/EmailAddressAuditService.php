<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\EmailAddress;

final class EmailAddressAuditService
{
    /** @return array{invalid: array, collisions: array} */
    public function report(): array
    {
        $invalid = [];
        $groups = [];
        $rows = PersonQuery::create()
            ->withColumn('MIN(per_ID) OVER (PARTITION BY LOWER(TRIM(per_Email)))', 'EmailGroup')
            ->select(['Id', 'Email', 'WorkEmail', 'EmailGroup'])
            ->orderById()
            ->find();
        foreach ($rows as $row) {
            foreach (['Email', 'WorkEmail'] as $field) {
                $email = trim((string) $row[$field]);
                if ($email !== '' && !EmailAddress::isValid($email)) {
                    $invalid[] = ['personId' => (int) $row['Id'], 'field' => $field, 'email' => $row[$field]];
                }
            }
            if (trim((string) $row['Email']) !== '') {
                $groups[$row['EmailGroup']][] = ['personId' => (int) $row['Id'], 'email' => $row['Email']];
            }
        }

        return [
            'invalid' => $invalid,
            'collisions' => array_values(array_filter($groups, static fn (array $group): bool => count($group) > 1)),
        ];
    }
}
