<?php

namespace ChurchCRM\Plugins\Holidays;

use ChurchCRM\data\Countries;
use ChurchCRM\data\Country;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;

/**
 * Holiday Calendars Plugin.
 *
 * Contributes one or more system calendars populated from the Yasumi holiday
 * provider. Admins choose which countries to display via plugin settings;
 * each enabled country becomes its own toggleable calendar in the sidebar.
 */
class HolidaysPlugin extends AbstractPlugin
{
    public function getId(): string
    {
        return 'holidays';
    }

    public function getName(): string
    {
        return 'Holiday Calendars';
    }

    public function getDescription(): string
    {
        return 'Adds public-holiday calendars (powered by Yasumi) to the main calendar.';
    }

    public function boot(): void
    {
        HookManager::addFilter(
            Hooks::SYSTEM_CALENDARS_REGISTER,
            [$this, 'registerSystemCalendars']
        );
    }

    /**
     * On first activation, pre-populate the `countries` setting from the
     * configured church country (if Yasumi supports it).
     */
    public function activate(): void
    {
        parent::activate();

        $existing = $this->getConfigValue('countries');
        if (!empty($existing)) {
            return;
        }

        $yasumiName = $this->getChurchCountryYasumiName();
        if ($yasumiName !== null) {
            $this->setConfigValue('countries', $yasumiName);
            $this->log('Pre-selected holiday country from church country', 'info', [
                'country' => $yasumiName,
            ]);
        }
    }

    /**
     * Filter callback: append one HolidayCalendarProvider per configured country.
     *
     * @param array $calendars Existing system calendars
     * @return array
     */
    public function registerSystemCalendars(array $calendars): array
    {
        $countries = $this->getConfiguredCountries();
        if (empty($countries)) {
            return $calendars;
        }

        $categories = $this->getConfiguredCategories();

        foreach ($countries as $yasumiCountry) {
            $calendars[] = new HolidayCalendarProvider($yasumiCountry, $categories);
        }

        return $calendars;
    }

    /**
     * Resolve the list of Yasumi country names to display.
     *
     * Order of precedence:
     *   1. Comma-separated `countries` plugin setting
     *   2. The church country from System Settings (if Yasumi supports it)
     *
     * @return string[]
     */
    public function getConfiguredCountries(): array
    {
        $raw = $this->getConfigValue('countries');
        if (!empty($raw)) {
            $list = array_filter(array_map('trim', explode(',', $raw)));
            return array_values(array_unique($list));
        }

        $churchCountry = $this->getChurchCountryYasumiName();
        return $churchCountry !== null ? [$churchCountry] : [];
    }

    /**
     * @return string[] Lowercase Yasumi category names; empty array means "all".
     */
    public function getConfiguredCategories(): array
    {
        $raw = $this->getConfigValue('categories');
        if (empty($raw)) {
            return [];
        }

        $list = array_filter(array_map(
            fn (string $c): string => strtolower(trim($c)),
            explode(',', $raw)
        ));

        return array_values(array_unique($list));
    }

    public function isConfigured(): bool
    {
        // Plugin is "configured" as long as we can resolve at least one country
        // (either from settings or the church country fallback).
        return !empty($this->getConfiguredCountries());
    }

    public function getConfigurationError(): ?string
    {
        if ($this->isConfigured()) {
            return null;
        }

        return gettext(
            'No holiday country is configured. Set the church country in System Settings ' .
            'or list countries explicitly in the plugin settings.'
        );
    }

    private function getChurchCountryYasumiName(): ?string
    {
        try {
            $countryName = SystemConfig::getValue('sChurchCountry');
            if (empty($countryName)) {
                return null;
            }

            $country = Countries::getCountryByName($countryName);
            if ($country instanceof Country) {
                return $country->getCountryNameYasumi();
            }
        } catch (\Throwable $e) {
            // Swallow — treat as "no country"
        }

        return null;
    }
}
