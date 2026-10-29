<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;

/**
 * Labels with no recipient list of their own: one per family for the newsletter and the data
 * confirmation mailing, one per cart member for name tags.
 */
class FamilyLabelService
{
    /**
     * One label per family, in the order of the ZIP the mail is addressed to: the family's
     * name or salutation, then its mailing address.
     *
     * @param bool $newsletterOnly only the families flagged to receive the newsletter by mail
     * @param bool $familyNames the family name rather than the salutation ("John & Mary Smith")
     * @return array<int, array{text: string}>
     */
    public static function familyCells(bool $newsletterOnly, bool $familyNames): array
    {
        $query = FamilyQuery::create();
        if ($newsletterOnly) {
            $query->filterBySendNewsletter('TRUE');
        }

        $cells = [];
        // The SQL sort is on the primary ZIP; sortByMailingZip() re-sorts on the ZIP actually
        // printed, which only differs for families whose second address is the mailing address.
        foreach (Family::sortByMailingZip($query->orderByZip()->find()) as $family) {
            $text = $familyNames ? (string) $family->getName() : (string) $family->getSalutation();
            $address = $family->getMailingAddressLines();
            if ($address !== '') {
                $text .= "\n" . $address;
            }
            $cells[] = ['text' => $text];
        }

        return $cells;
    }

    /**
     * A name tag for each person: their photo when they have one, the name and the person's ID.
     *
     * @param int[] $personIds
     * @return array<int, array<string, mixed>>
     */
    public static function nameTagCells(array $personIds, float $labelWidth, float $labelHeight): array
    {
        if ($personIds === []) {
            return [];
        }

        $cells = [];
        $people = PersonQuery::create()->filterById($personIds)->orderByLastName()->orderByFirstName()->find();
        foreach ($people as $person) {
            $photo = LabelSheetService::fitPhoto((string) $person->getPhoto()->getPhotoURI(), $labelWidth / 2 - 2, $labelHeight - 2);
            $name = $photo !== null
                ? $person->getFirstName() . "\n" . $person->getLastName()
                : $person->getFirstName() . ' ' . $person->getLastName();
            $cells[] = ['text' => $name . "\n\n" . $person->getId(), 'photo' => $photo];
        }

        return $cells;
    }
}
