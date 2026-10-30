<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;

/**
 * Family address labels and personal name badges.
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
     * A name badge for each person, with their photo when one was uploaded.
     *
     * @param int[] $personIds
     * @return array<int, array<string, mixed>>
     */
    public static function nameTagCells(array $personIds, float $labelWidth, float $labelHeight, int $fontSize): array
    {
        if ($personIds === []) {
            return [];
        }

        $cells = [];
        $people = PersonQuery::create()->filterById($personIds)->orderByLastName()->orderByFirstName()->find();
        foreach ($people as $person) {
            $photo = $person->getPhoto();
            $cells[] = LabelSheetService::nameTag(
                (string) $person->getFirstName(),
                (string) $person->getLastName(),
                $photo->hasUploadedPhoto() ? (string) $photo->getPhotoURI() : null,
                $labelWidth,
                $labelHeight,
                $fontSize
            );
        }

        return $cells;
    }
}
