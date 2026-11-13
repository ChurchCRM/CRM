<?php

namespace ChurchCRM\Plugins\Confession;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Utils\CustomFieldUtils;
use ChurchCRM\Utils\InputUtils;

class ConfessionPlugin extends AbstractPlugin
{
    public function getId(): string
    {
        return 'confession';
    }

    public function getName(): string
    {
        return gettext('Confession');
    }

    public function getDescription(): string
    {
        return 'Show a person\'s confessor and most recent confession date on their profile.';
    }

    public function getType(): string
    {
        return 'core';
    }

    public function isConfigured(): bool
    {
        return (int) $this->getConfigValue('fatherFieldId') > 0
            && (int) $this->getConfigValue('dateFieldId') > 0;
    }

    public function boot(): void
    {
        $this->migrateLegacySettings();
        HookManager::addFilter(Hooks::PERSON_VIEW_CONTENT, [$this, 'addPersonViewContent']);
        HookManager::addFilter(Hooks::PERSON_VIEW_CUSTOM_FIELDS, [$this, 'filterAdditionalInformationFields']);
    }

    public function addPersonViewContent(string $content, Person $person, array $visibleFields, array $visibleData): string
    {
        $configuredFields = [
            [(int) $this->getConfigValue('fatherFieldId'), 9],
            [(int) $this->getConfigValue('dateFieldId'), 2],
        ];
        $items = '';

        foreach ($visibleFields as $field) {
            foreach ($configuredFields as [$fieldId, $typeId]) {
                if ($fieldId !== (int) $field->getId() || $typeId !== (int) $field->getTypeId()) {
                    continue;
                }

                $value = CustomFieldUtils::display(
                    $typeId,
                    (string) ($visibleData[$fieldId] ?? ''),
                    $field->getSpecial()
                );
                if ($value === null || $value === '') {
                    continue;
                }

                $items .= '<li class="mb-2"><strong>' . InputUtils::escapeHTML($field->getName())
                    . ':</strong> ' . InputUtils::escapeHTML((string) $value) . '</li>';
            }
        }

        if ($items === '') {
            return $content;
        }

        return $content
            . '<div class="mb-3" id="person-confession">'
            . '<h6 class="text-body-secondary mb-2"><i class="fa-solid fa-cross me-1"></i>'
            . gettext('Confession')
            . '</h6><ul class="list-unstyled ms-3">'
            . $items
            . '</ul></div>';
    }

    public function filterAdditionalInformationFields(array $fields): array
    {
        $fieldTypes = [
            (int) $this->getConfigValue('fatherFieldId') => 9,
            (int) $this->getConfigValue('dateFieldId') => 2,
        ];

        return array_values(array_filter(
            $fields,
            static fn ($field): bool => ($fieldTypes[(int) $field->getId()] ?? null) !== (int) $field->getTypeId()
        ));
    }

    private function migrateLegacySettings(): void
    {
        if ($this->getConfigValue('legacySettingsMigrated') === '1') {
            return;
        }

        $migrations = [
            'iPersonConfessionFatherCustomField' => 'fatherFieldId',
            'iPersonConfessionDateCustomField' => 'dateFieldId',
        ];
        $migrationSucceeded = true;

        foreach ($migrations as $legacyKey => $pluginKey) {
            $legacyValue = (string) SystemConfig::getValue($legacyKey);
            $pluginValue = $this->getConfigValue($pluginKey);
            if ($pluginValue === '' && $legacyValue !== '') {
                $this->setConfigValue($pluginKey, $legacyValue);
                $pluginValue = $this->getConfigValue($pluginKey);
            }
            if ($legacyValue !== '' && $pluginValue === '') {
                $migrationSucceeded = false;
            }
        }

        if ($migrationSucceeded) {
            $this->setConfigValue('legacySettingsMigrated', '1');
        }
    }
}
