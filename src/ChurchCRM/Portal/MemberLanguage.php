<?php

namespace ChurchCRM\Portal;

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\PersonSignin;
use ChurchCRM\model\ChurchCRM\PersonSigninQuery;
use ChurchCRM\Portal\Auth\PortalPrincipal;
use ChurchCRM\Portal\Auth\PortalSessionStore;
use ChurchCRM\Portal\Auth\SignInMethod;
use ChurchCRM\Service\LocaleService;
use ChurchCRM\Utils\LoggerUtils;
use Collator;
use Throwable;

/**
 * The language a person chose for the Member Portal and the emails it sends
 * them (`psi_Language`). Unset, or set to a language this installation no
 * longer ships, it is the church's `sLanguage`.
 *
 * A member session (an email, device or passkey sign-in) and Login as Person use
 * the person's language. A staff session opening the portal keeps the staff
 * account's own `ui.locale` setting, so it has no choice here.
 */
final class MemberLanguage
{
    /** @var array<int, string|null> the stored value per person, for this request */
    private static array $stored = [];

    /** @var array<string, string>|null locale => native name */
    private static ?array $names = null;

    /**
     * The locale of this browser's member or Login as Person session, or null for
     * none and for a staff session, which keeps the staff account's setting.
     */
    public static function forSession(): ?string
    {
        $principal = PortalSessionStore::load();
        if (!self::appliesTo($principal)) {
            return null;
        }

        return self::localeFor($principal->personId);
    }

    /** Whether a session uses, and may choose, the person's portal language. */
    public static function appliesTo(?PortalPrincipal $principal): bool
    {
        return $principal instanceof PortalPrincipal && $principal->method !== SignInMethod::STAFF;
    }

    /** The person's language, or the church's when they chose none. */
    public static function localeFor(int $personId): string
    {
        return self::chosenBy($personId) ?? self::churchLocale();
    }

    /** The person's own choice, while this installation still ships it. */
    public static function chosenBy(int $personId): ?string
    {
        $stored = self::storedFor($personId);

        return $stored !== null && self::isAvailable($stored) ? $stored : null;
    }

    public static function churchLocale(): string
    {
        return (string) SystemConfig::getValue('sLanguage') ?: 'en_US';
    }

    public static function isAvailable(string $locale): bool
    {
        return array_key_exists($locale, self::names());
    }

    /** Null, or an empty string, is the church's language. */
    public static function choose(int $personId, ?string $locale): void
    {
        $locale = $locale === '' ? null : $locale;
        $state = PersonSigninQuery::create()->findPk($personId) ?? (new PersonSignin())->setPersonId($personId);
        $state->setLanguage($locale)->save();
        self::$stored[$personId] = $locale;
    }

    /**
     * What the profile's language picker offers: the church's language first,
     * then every language by its own name.
     *
     * @return array{selected: string, churchName: string, choices: list<array{locale: string, name: string}>}
     */
    public static function picker(int $personId): array
    {
        $names = self::names();
        $choices = [];
        foreach ($names as $locale => $name) {
            $choices[] = ['locale' => $locale, 'name' => $name];
        }
        if (class_exists(Collator::class)) {
            $collator = new Collator('root');
            usort($choices, static fn (array $a, array $b): int => (int) $collator->compare($a['name'], $b['name']));
        }

        return [
            'selected' => self::chosenBy($personId) ?? '',
            'churchName' => $names[self::churchLocale()] ?? self::churchLocale(),
            'choices' => $choices,
        ];
    }

    /** Builds text for this person, such as an email, in their language. */
    public static function inLanguageOf(int $personId, callable $work): mixed
    {
        return Bootstrapper::inLocale(self::localeFor($personId), $work);
    }

    /** Builds text for the church office in the church's language. */
    public static function inChurchLanguage(callable $work): mixed
    {
        return Bootstrapper::inLocale(self::churchLocale(), $work);
    }

    /** What the person chose, whether or not this installation still ships it. */
    public static function storedFor(int $personId): ?string
    {
        if (!array_key_exists($personId, self::$stored)) {
            try {
                $value = PersonSigninQuery::create()->findPk($personId)?->getLanguage();
            } catch (Throwable $e) {
                // A database the 7.9.0 upgrade has not reached yet has no column.
                LoggerUtils::getAppLogger()->debug('Could not read the member language', ['message' => $e->getMessage()]);
                $value = null;
            }
            self::$stored[$personId] = is_string($value) && $value !== '' ? $value : null;
        }

        return self::$stored[$personId];
    }

    /**
     * @return array<string, string>
     */
    private static function names(): array
    {
        if (self::$names === null) {
            self::$names = [];
            foreach (LocaleService::getSupportedLocales() as $englishName => $config) {
                $native = $config['nativeName'] ?? '';
                self::$names[(string) $config['locale']] = is_string($native) && $native !== '' ? $native : (string) $englishName;
            }
        }

        return self::$names;
    }
}
