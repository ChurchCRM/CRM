<?php

namespace ChurchCRM\Service;

use ChurchCRM\data\GradePresets;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Map\ListOptionTableMap;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Propel;

/**
 * The church's grade list (#10189). A grade's option ID is its level (see
 * GradePresets); grades the church adds itself are numbered from
 * FIRST_CUSTOM_OPTION_ID so they never take a level.
 */
class GradeService
{
    public const DEFAULT_LIST_ID = 6;
    public const FIRST_CUSTOM_OPTION_ID = 100;
    public const NOTE_TYPE = 'grade';
    public const PRESET_CONFIG = 'sGradePreset';

    /** @var array<int, string>|null */
    private static ?array $names = null;

    public static function listId(): int
    {
        $listId = (int) SystemConfig::getValue('iGradeListId');

        return $listId > 0 ? $listId : self::DEFAULT_LIST_ID;
    }

    /**
     * @return ObjectCollection<ListOption>
     */
    public static function options(): ObjectCollection
    {
        return ListOptionQuery::create()->filterById(self::listId())->orderByOptionSequence()->find();
    }

    /**
     * @return array<int, string> option ID => name, in list order
     */
    public static function names(): array
    {
        if (self::$names === null) {
            self::$names = [];
            foreach (self::options() as $option) {
                self::$names[(int) $option->getOptionId()] = $option->getOptionName();
            }
        }

        return self::$names;
    }

    public static function nameOf(?int $gradeId): ?string
    {
        return $gradeId === null ? null : (self::names()[$gradeId] ?? null);
    }

    public static function isValid(?int $gradeId): bool
    {
        return $gradeId !== null && array_key_exists($gradeId, self::names());
    }

    /**
     * Normalise a submitted grade: a known option ID, or null for anything else.
     */
    public static function resolve(mixed $raw): ?int
    {
        if (!is_numeric($raw)) {
            return null;
        }
        $gradeId = (int) $raw;

        return self::isValid($gradeId) ? $gradeId : null;
    }

    /**
     * @return array<int, int|null> each option ID => the next one in list order; the last => null
     */
    public static function nextGradeMap(): array
    {
        $ids = array_keys(self::names());
        $next = [];
        foreach ($ids as $index => $id) {
            $next[$id] = $ids[$index + 1] ?? null;
        }

        return $next;
    }

    public static function forgetCache(): void
    {
        self::$names = null;
    }

    public static function currentPresetKey(): string
    {
        $key = SystemConfig::getValue(self::PRESET_CONFIG);

        return GradePresets::get($key) !== null ? $key : GradePresets::DEFAULT_KEY;
    }

    /**
     * Switch the grade list to a country's names. Each level keeps its option ID,
     * so people keep their grade; only the name changes. Preset names replace any
     * name the church typed. Levels the preset lacks are deleted and the people in
     * them lose their grade. A church-added grade named like a preset level is
     * folded into that level; other church-added grades keep their place.
     *
     * @return array{preset: string, renamed: array<int, array{from: string, to: string}>, added: string[], removed: array<int, array{name: string, people: int}>, merged: array<int, array{name: string, people: int}>, cleared: int}
     */
    public static function applyPreset(string $key, int $authorId, bool $dryRun = false): array
    {
        $preset = GradePresets::get($key);
        if ($preset === null) {
            throw new \InvalidArgumentException('Unknown grade preset: ' . $key);
        }

        $listId = self::listId();
        $options = self::options();
        $levelByName = [];
        foreach ($preset['grades'] as $level => $name) {
            $levelByName[mb_strtolower($name)] = $level;
        }
        $peopleByGrade = self::peopleCountByGrade();

        $summary = ['preset' => $key, 'renamed' => [], 'added' => [], 'removed' => [], 'merged' => [], 'cleared' => 0];
        $existing = [];
        $mergeInto = [];
        $removedIds = [];
        $anchors = [];
        $lastLevel = 0;
        foreach ($options as $option) {
            $id = (int) $option->getOptionId();
            $name = $option->getOptionName();
            $existing[$id] = $option;
            if ($id < self::FIRST_CUSTOM_OPTION_ID) {
                $lastLevel = $id;
                if (!isset($preset['grades'][$id])) {
                    $removedIds[] = $id;
                    $summary['removed'][] = ['name' => $name, 'people' => $peopleByGrade[$id] ?? 0];
                    $summary['cleared'] += $peopleByGrade[$id] ?? 0;
                } elseif ($name !== $preset['grades'][$id]) {
                    $summary['renamed'][] = ['from' => $name, 'to' => $preset['grades'][$id]];
                }
            } elseif (isset($levelByName[mb_strtolower($name)])) {
                $mergeInto[$id] = $levelByName[mb_strtolower($name)];
                $summary['merged'][] = ['name' => $name, 'people' => $peopleByGrade[$id] ?? 0];
            } else {
                $anchors[$lastLevel][] = $id;
            }
        }
        foreach ($preset['grades'] as $level => $name) {
            if (!isset($existing[$level])) {
                $summary['added'][] = $name;
            }
        }

        if ($dryRun) {
            return $summary;
        }

        $con = Propel::getWriteConnection(ListOptionTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            foreach ($mergeInto as $customId => $level) {
                PersonQuery::create()->filterByGradeId($customId)->update(['GradeId' => $level], $con);
                $existing[$customId]->delete($con);
            }
            foreach ($removedIds as $level) {
                $oldName = $existing[$level]->getOptionName();
                foreach (self::personIdsInGrade($level) as $personId) {
                    self::addNote(
                        $personId,
                        sprintf(gettext('Grade %s cleared when the grade names changed to %s'), $oldName, $preset['name']),
                        $authorId,
                        $con
                    );
                }
                PersonQuery::create()->filterByGradeId($level)->update(['GradeId' => null], $con);
                $existing[$level]->delete($con);
            }

            $order = [];
            $placed = [];
            $levels = array_keys($preset['grades']);
            foreach ($anchors as $anchor => $customIds) {
                $target = 0;
                foreach ($levels as $level) {
                    if ($level <= $anchor) {
                        $target = $level;
                    }
                }
                $placed[$target] = array_merge($placed[$target] ?? [], $customIds);
            }
            array_push($order, ...($placed[0] ?? []));
            foreach ($levels as $level) {
                $order[] = $level;
                array_push($order, ...($placed[$level] ?? []));
            }

            foreach ($order as $index => $optionId) {
                $option = $existing[$optionId] ?? null;
                if ($option === null) {
                    $option = new ListOption();
                    $option->setId($listId);
                    $option->setOptionId($optionId);
                }
                if (isset($preset['grades'][$optionId])) {
                    $option->setOptionName($preset['grades'][$optionId]);
                }
                $option->setOptionSequence($index + 1);
                $option->save($con);
            }

            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();
            throw $e;
        }

        SystemConfig::setValue(self::PRESET_CONFIG, $key);
        self::forgetCache();
        LoggerUtils::getAppLogger()->info('Grade names changed', ['preset' => $key, 'renamed' => count($summary['renamed']), 'added' => count($summary['added']), 'removed' => count($summary['removed']), 'cleared' => $summary['cleared']]);

        return $summary;
    }

    public static function addNote(int $personId, string $text, int $authorId, $con = null): void
    {
        $note = new Note();
        $note->setPerId($personId);
        $note->setType(self::NOTE_TYPE);
        $note->setText($text);
        $note->setEntered($authorId);
        $note->save($con);
    }

    /**
     * @return array<int, int> option ID => number of people with that grade
     */
    private static function peopleCountByGrade(): array
    {
        $rows = PersonQuery::create()
            ->filterByGradeId(null, Criteria::ISNOTNULL)
            ->withColumn('COUNT(*)', 'People')
            ->groupByGradeId()
            ->select(['GradeId', 'People'])
            ->find();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['GradeId']] = (int) $row['People'];
        }

        return $counts;
    }

    /**
     * @return int[]
     */
    private static function personIdsInGrade(int $gradeId): array
    {
        return array_map('intval', PersonQuery::create()->filterByGradeId($gradeId)->select(['Id'])->find()->toArray());
    }
}
