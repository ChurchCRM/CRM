<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\model\ChurchCRM\Map\VolunteerMinistryTableMap;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerQualificationQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeam;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Volunteer\Service\VolunteerEventService;
use ChurchCRM\Volunteer\Service\VolunteerMinistryService;
use ChurchCRM\Volunteer\Service\VolunteerQualificationService;
use Propel\Runtime\Propel;

// Volunteer Opportunities (V1) leave ChurchCRM (#10606). Every opportunity not converted yet
// becomes a position in the ministry the last conversion filled (or a new "Volunteer
// Opportunities" ministry), and every person linked to it a qualification for that position;
// people linked to an already converted opportunity since its conversion are qualified too.
// A link to a person who no longer exists or is deceased is not carried over. The V1 tables
// are dropped only once all of that is committed: when the conversion fails, they stay, so no
// volunteer data is lost.

SystemConfig::init(ConfigQuery::create()->find());
$logger = LoggerUtils::getAppLogger();
$connection = Propel::getWriteConnection(VolunteerMinistryTableMap::DATABASE_NAME);

$storedSetting = static fn (string $name): ?string => ConfigQuery::create()->filterByName($name)->findOne($connection)?->getValue();
$tableExists = static fn (string $table): bool => $connection->query("SHOW TABLES LIKE '" . $table . "'")->fetchColumn() !== false;

/** `$name`, or `$name (2)`, `$name (3)`… — the first not in `$taken`, which it joins. */
$freeName = static function (string $name, array &$taken): string {
    $candidate = $name;
    for ($n = 2; isset($taken[mb_strtolower($candidate)]); ++$n) {
        $candidate = sprintf('%s (%d)', $name, $n);
    }
    $taken[mb_strtolower($candidate)] = true;

    return $candidate;
};

$firstTeam = static fn (VolunteerMinistry $ministry): ?VolunteerTeam => VolunteerTeamQuery::create()
    ->filterByMinistryId($ministry->getId())
    ->orderById()
    ->findOne($connection);

$v1Tables = $tableExists('volunteeropportunity_vol') && $tableExists('person2volunteeropp_p2vo');
$wasOnV1 = $storedSetting('sVolunteerVersion') === 'v1';
$storedConversion = json_decode((string) $storedSetting('aVolunteerV1Conversion'), true);
$conversionMap = [];
foreach (is_array($storedConversion['opportunities'] ?? null) ? $storedConversion['opportunities'] : [] as $opportunityId => $positionId) {
    $conversionMap[(int) $opportunityId] = (int) $positionId;
}

$converted = true;
if ($v1Tables) {
    $opportunities = $connection->query(
        'SELECT vol_ID, vol_Order, vol_Active, vol_Name, vol_Description FROM volunteeropportunity_vol ORDER BY vol_Order, vol_ID'
    )->fetchAll(\PDO::FETCH_ASSOC);

    $links = [];
    foreach ($connection->query(
        'SELECT DISTINCT p2vo_vol_ID, p2vo_per_ID FROM person2volunteeropp_p2vo WHERE p2vo_vol_ID IS NOT NULL AND p2vo_per_ID IS NOT NULL ORDER BY p2vo_per_ID'
    )->fetchAll(\PDO::FETCH_ASSOC) as $link) {
        $links[(int) $link['p2vo_vol_ID']][(int) $link['p2vo_per_ID']] = true;
    }

    $people = [];
    $linkedIds = array_keys(array_replace([], ...array_values($links)));
    if ($linkedIds !== []) {
        foreach (PersonQuery::create()->filterById($linkedIds)->find($connection) as $person) {
            $people[(int) $person->getId()] = $person;
        }
    }

    $skipped = [];
    $peopleOf = static function (int $opportunityId) use ($links, $people, &$skipped): array {
        $ids = [];
        foreach (array_keys($links[$opportunityId] ?? []) as $personId) {
            if (!isset($people[$personId])) {
                $skipped[] = ['opportunityId' => $opportunityId, 'personId' => $personId, 'reason' => 'missing'];
            } elseif ($people[$personId]->isDeceased()) {
                $skipped[] = ['opportunityId' => $opportunityId, 'personId' => $personId, 'reason' => 'deceased'];
            } else {
                $ids[] = $personId;
            }
        }

        return $ids;
    };

    $toConvert = array_values(array_filter($opportunities, static fn (array $row): bool => !isset($conversionMap[(int) $row['vol_ID']])));
    $pending = [];
    foreach ($conversionMap as $opportunityId => $positionId) {
        $position = VolunteerPositionQuery::create()->filterById($positionId)->filterByActive(true)->findOne($connection);
        if ($position === null) {
            continue;
        }
        $qualified = array_flip(array_map('intval', VolunteerQualificationQuery::create()
            ->filterByPositionId($positionId)
            ->select(['PersonId'])
            ->find($connection)
            ->toArray()));
        $waiting = array_values(array_filter($peopleOf($opportunityId), static fn (int $id): bool => !isset($qualified[$id])));
        if ($waiting !== []) {
            $pending[] = [$position, $waiting];
        }
    }

    if ($toConvert !== [] || $pending !== []) {
        $connection->beginTransaction();
        try {
            /** @var User|null $actor */
            $actor = UserQuery::create()->filterByAdmin(true)->orderByPersonId()->findOne($connection);
            if ($actor === null) {
                throw new \RuntimeException('No administrator to record the conversion against');
            }
            $ministries = new VolunteerMinistryService();
            $qualifications = new VolunteerQualificationService($ministries->getAuthorizationService(), $ministries);

            foreach ($pending as [$position, $waiting]) {
                $qualifications->importQualifications($waiting, $position, $actor);
            }

            if ($toConvert !== []) {
                $target = (int) ($storedConversion['ministryId'] ?? 0) > 0
                    ? VolunteerMinistryQuery::create()->findPk((int) $storedConversion['ministryId'], $connection)
                    : null;
                $team = $target === null ? null : $firstTeam($target);
                if ($team === null) {
                    $ministryNames = [];
                    foreach (VolunteerMinistryQuery::create()->select(['Name'])->find($connection) as $name) {
                        $ministryNames[mb_strtolower((string) $name)] = true;
                    }
                    $target = $ministries->createMinistry($freeName(gettext('Volunteer Opportunities'), $ministryNames), null, $actor);
                    $team = $firstTeam($target);
                }

                $positionNames = [];
                foreach (VolunteerPositionQuery::create()->filterByTeamId($team->getId())->select(['Name'])->find($connection) as $name) {
                    $positionNames[mb_strtolower((string) $name)] = true;
                }

                foreach ($toConvert as $opportunity) {
                    $opportunityId = (int) $opportunity['vol_ID'];
                    $name = trim((string) $opportunity['vol_Name']);
                    $position = $ministries->createPosition(
                        $target,
                        $team,
                        $freeName($name !== '' ? $name : sprintf(gettext('Opportunity #%d'), $opportunityId), $positionNames),
                        trim((string) $opportunity['vol_Description']) ?: null,
                        (int) $opportunity['vol_Order'],
                        $actor,
                        false,
                        false,
                        $opportunity['vol_Active'] !== 'false'
                    );
                    $qualifications->importQualifications($peopleOf($opportunityId), $position, $actor);
                    $conversionMap[$opportunityId] = (int) $position->getId();
                }
            }

            if ($wasOnV1) {
                VolunteerEventService::addOtherEventType();
            }
            $connection->commit();
            $logger->info('Volunteer Opportunities converted to positions at upgrade', [
                'opportunities' => $conversionMap,
                'pendingQualified' => count($pending),
                'skippedLinks' => $skipped,
            ]);
        } catch (\Throwable $e) {
            $connection->rollBack();
            $converted = false;
            $logger->error('Volunteer Opportunities could not be converted at upgrade; their tables are kept', [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'opportunities' => count($toConvert),
            ]);
        }
    } elseif ($wasOnV1) {
        VolunteerEventService::addOtherEventType();
    }
}

if ($converted) {
    $connection->exec('DROP TABLE IF EXISTS `person2volunteeropp_p2vo`');
    $connection->exec('DROP TABLE IF EXISTS `volunteeropportunity_vol`');
    $connection->exec("DELETE FROM `config_cfg` WHERE `cfg_name` IN ('sVolunteerVersion', 'aVolunteerV1Conversion', 'sVolunteerV1NoticeDismissed')");
}
