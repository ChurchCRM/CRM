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
     * name or salutation, then its mailing address. The individual method instead gives
     * every living member of the family their own label with their own name.
     *
     * @param bool $newsletterOnly only the families flagged to receive the newsletter by mail
     * @param string $namingMethod salutationutility, familyname, individual or formal; anything else reads as the salutation
     * @return array<int, array{text: string}>
     */
    public static function familyCells(bool $newsletterOnly, string $namingMethod = 'salutationutility'): array
    {
        $query = FamilyQuery::create()->filterByActive();
        if ($newsletterOnly) {
            $query->filterBySendNewsletter('TRUE');
        }

        $cells = [];
        // The SQL sort is on the primary ZIP; sortByMailingZip() re-sorts on the ZIP actually
        // printed, which only differs for families whose second address is the mailing address.
        foreach (Family::sortByMailingZip($query->orderByZip()->find()) as $family) {
            $address = $family->getMailingAddressLines();
            if ($namingMethod === 'individual') {
                foreach ($family->getLivingPeople() as $person) {
                    $text = $person->getFullName();
                    if ($address !== '') {
                        $text .= "\n" . $address;
                    }
                    $cells[] = ['text' => $text];
                }
                continue;
            }
            $text = match ($namingMethod) {
                'familyname' => (string) $family->getName(),
                'formal' => $family->getFormalName(),
                default => (string) $family->getSalutation(),
            };
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
        $people = PersonQuery::create()
            ->filterByActive()
            ->filterById($personIds)
            ->orderByLastName()
            ->orderByFirstName()
            ->find();
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
