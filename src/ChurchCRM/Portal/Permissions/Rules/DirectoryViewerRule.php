<?php

namespace ChurchCRM\Portal\Permissions\Rules;

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Portal\Permissions\BulkPortalRule;
use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\PortalRuleGrant;
use ChurchCRM\Portal\PortalDirectoryService;
use Propel\Runtime\ActiveQuery\Criteria;

/** People whose classification the Directory tab lets see the member directory. */
final class DirectoryViewerRule implements BulkPortalRule
{
    public const KEY = 'core.directory-viewers';

    public function getKey(): string
    {
        return self::KEY;
    }

    public function grantsFor(Person $person): iterable
    {
        return PortalDirectoryService::isViewerClassification((int) $person->getClsId()) ? [self::grant()] : [];
    }

    public function getSourceName(): string
    {
        return gettext('Directory classifications');
    }

    public function getCapabilityKeys(): array
    {
        return [CorePortalCapabilities::DIRECTORY_VIEW];
    }

    public function grantsForPeople(array $personIds): iterable
    {
        $viewers = PortalDirectoryService::settings()['viewers'];
        if ($viewers === []) {
            return;
        }

        foreach (array_chunk($personIds, 1000) as $chunk) {
            $ids = PersonQuery::create()
                ->filterById($chunk, Criteria::IN)
                ->filterByClsId($viewers, Criteria::IN)
                ->select(['Id'])
                ->find();
            foreach ($ids as $id) {
                yield (int) $id => self::grant();
            }
        }
    }

    private static function grant(): PortalRuleGrant
    {
        return new PortalRuleGrant(CorePortalCapabilities::DIRECTORY_VIEW, null, gettext('Directory classifications'));
    }
}
