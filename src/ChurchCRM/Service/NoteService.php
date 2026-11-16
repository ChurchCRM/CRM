<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Map\NoteTableMap;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Utils\DateTimeUtils;
use Propel\Runtime\ActiveQuery\Criteria;

class NoteService
{
    private const EXCERPT_LENGTH = 140;

    /**
     * The newest person and family notes the user may read, newest first.
     * Private notes follow Note::isVisibleTo(): the author and users who may read
     * private notes see them; nobody else learns they exist.
     *
     * @return array<int, array{subject: string, url: string, excerpt: string, author: string, date: string, private: bool}>
     */
    public function latestNotesFor(User $viewer, int $limit): array
    {
        if (!$viewer->canReadNotes()) {
            return [];
        }

        $query = NoteQuery::create()
            ->filterByType('note')
            ->leftJoinWithPerson()
            ->leftJoinWithFamily()
            ->orderByDateEntered(Criteria::DESC)
            ->orderById(Criteria::DESC)
            ->limit($limit);
        if (!$viewer->canReadPrivateNotes()) {
            $query
                ->condition('public', NoteTableMap::COL_NTE_PRIVATE . ' = ?', 0)
                ->condition('own', NoteTableMap::COL_NTE_ENTEREDBY . ' = ?', (int) $viewer->getId())
                ->where(['public', 'own'], Criteria::LOGICAL_OR);
        }

        $notes = [];
        foreach ($query->find() as $note) {
            $row = $this->toRow($note, $viewer);
            if ($row !== null) {
                $notes[] = $row;
            }
        }

        $authors = $this->authorNames(array_column($notes, 'author'));

        return array_map(
            static fn (array $row): array => ['author' => $authors[$row['author']] ?? gettext('Unknown')] + $row,
            $notes
        );
    }

    /**
     * @return array{subject: string, url: string, excerpt: string, author: int, date: string, private: bool}|null
     */
    private function toRow(Note $note, User $viewer): ?array
    {
        if (!$note->isVisibleTo($viewer)) {
            return null;
        }

        $person = (int) $note->getPerId() > 0 ? $note->getPerson() : null;
        $family = $person === null && (int) $note->getFamId() > 0 ? $note->getFamily() : null;
        if ($person !== null) {
            $subject = $person->getFullName();
            $url = $person->getViewURI();
            $familyId = (int) $person->getFamId();
        } elseif ($family !== null) {
            $subject = (string) $family->getName();
            $url = $family->getViewURI();
            $familyId = (int) $family->getId();
        } else {
            return null;
        }
        if (!$viewer->canReadNotes($person?->getId(), $familyId > 0 ? $familyId : null)) {
            return null;
        }
        if ($familyId > 0 && !$viewer->canViewFamily($familyId)) {
            return null;
        }

        return [
            'subject' => $subject,
            'url' => $url,
            'excerpt' => $this->excerpt((string) $note->getText()),
            'author' => (int) $note->getEnteredBy(),
            'date' => DateTimeUtils::formatDate($note->getDateEntered('Y-m-d H:i:s'), true),
            'private' => $note->isPrivate(),
        ];
    }

    /**
     * Notes are stored as editor HTML; a one-line preview needs plain text.
     */
    private function excerpt(string $html): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div|\/li)\b[^>]*>/i', ' ', $html) ?? $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return mb_strimwidth($text, 0, self::EXCERPT_LENGTH, '…', 'UTF-8');
    }

    /**
     * @param int[] $personIds
     * @return array<int, string>
     */
    private function authorNames(array $personIds): array
    {
        $personIds = array_values(array_unique(array_filter($personIds, static fn (int $id): bool => $id > 0)));
        if ($personIds === []) {
            return [];
        }

        $names = [];
        foreach (PersonQuery::create()->filterById($personIds, Criteria::IN)->find() as $person) {
            $names[(int) $person->getId()] = $person->getFullName();
        }

        return $names;
    }
}
