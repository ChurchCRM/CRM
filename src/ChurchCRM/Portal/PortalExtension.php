<?php

namespace ChurchCRM\Portal;

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\LocaleInfo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Portal\Auth\PortalContext;
use ChurchCRM\Portal\Auth\PortalPrincipal;
use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\PortalAuthorizationService;
use ChurchCRM\Portal\Permissions\PortalCapabilityRegistry;
use ChurchCRM\Portal\Permissions\PortalScope;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\Markup;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Everything a Member Portal template — core or church-supplied — is allowed to
 * reach. The surface is exactly the functions and globals listed in design §3.4
 * and nothing else: no PHP includes, no filesystem or network functions, no
 * `$_SESSION`. That is what makes a church-uploaded template safe to render
 * without Twig's sandbox extension.
 */
class PortalExtension extends AbstractExtension implements GlobalsInterface
{
    /** Session key holding the flash messages the next portal page renders. */
    private const FLASH_SESSION_KEY = 'aPortalFlash';

    /** The stock logo ChurchMetaData::getChurchLogoURL() falls back to, drawn for a light background. */
    private const STOCK_LOGO = '/Images/churchcrm-logo-ink-blue.svg';

    /** ChurchCRM's paper + blue mark, drawn for a dark background. */
    private const STOCK_LOGO_ON_DARK = '/Images/churchcrm-symbol-paper-blue.svg';

    public function __construct(
        private readonly string $themeName,
        private readonly ?string $activeNavId = null
    ) {
    }

    /**
     * Queue a flash message for the next portal page render.
     */
    public static function addFlash(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION[self::FLASH_SESSION_KEY][] = ['type' => $type, 'message' => $message];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('url', [$this, 'url']),
            new TwigFunction('asset', [$this, 'asset']),
            new TwigFunction('theme_asset', [$this, 'themeAsset']),
            new TwigFunction('csrf_field', [$this, 'csrfField'], ['is_safe' => ['html']]),
            new TwigFunction('nonce', [$this, 'nonce']),
            new TwigFunction('portal_can', [$this, 'portalCan']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('email_wrap', [$this, 'emailWrap'], ['pre_escape' => 'html', 'is_safe' => ['html']]),
        ];
    }

    /**
     * A path inside this installation, root-path aware so a subdirectory
     * install works unchanged.
     */
    public function url(string $path = ''): string
    {
        return SystemURLs::getRootPath() . $path;
    }

    /**
     * A core asset (the shared CSS/JS bundles), cache-busted by its own
     * modification time exactly as the admin pages do it.
     */
    public function asset(string $path): string
    {
        return SystemURLs::assetVersioned($path);
    }

    /**
     * A file from the active theme, served through `/portal/theme/…`. When the
     * active theme does not carry the file but the default theme does, the
     * default theme's URL is returned.
     */
    public function themeAsset(string $path): string
    {
        return ThemeAssetStreamer::getAssetUrl($this->themeName, $path);
    }

    /**
     * The hidden input every portal form must carry.
     */
    public function csrfField(): Markup
    {
        return new Markup(CSRFUtils::getTokenInputField(), 'UTF-8');
    }

    /**
     * An escaped email address with a line-break opportunity after the "@" and
     * before every ".", so a long address wraps at a readable point instead of
     * mid-word.
     */
    public function emailWrap(string $escapedEmail): string
    {
        return str_replace(['@', '.'], ['@<wbr>', '<wbr>.'], $escapedEmail);
    }

    /**
     * The per-request Content-Security-Policy nonce. Every inline `<script>` in
     * a portal template — core or theme — must carry it.
     */
    public function nonce(): string
    {
        return (string) SystemURLs::getCSPNonce();
    }

    /**
     * Whether the member holds a portal capability (member sign-in design §8.2),
     * to show or hide a section or a button. `scope` is the record the template
     * has — a team, a family — or its id; without one, holding the capability on
     * any scope is enough. The routes and the API check again for themselves.
     */
    public function portalCan(string $capability, mixed $scope = null): bool
    {
        $principal = PortalContext::principal();
        if (!$principal instanceof PortalPrincipal) {
            return false;
        }

        $authz = new PortalAuthorizationService();
        if ($scope === null) {
            return $authz->scopesFor($principal, $capability) !== [];
        }

        if (!$scope instanceof PortalScope) {
            $id = is_array($scope) ? ($scope['id'] ?? null) : $scope;
            if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
                return false;
            }
            $type = PortalCapabilityRegistry::get($capability)?->scopeType ?? PortalScope::NONE;
            $scope = new PortalScope($type, (int) $id);
        }

        return $authz->decide($principal, $capability, $scope)->allowed;
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        $localeInfo = Bootstrapper::getCurrentLocale();

        return [
            'church' => $this->getChurch(),
            'member' => $this->getMember(),
            'nav' => PortalNav::build($this->activeNavId),
            'flash' => $this->takeFlash(),
            'portal' => [
                'rootPath' => SystemURLs::getRootPath(),
                'themeName' => $this->themeName,
                'locale' => $localeInfo->getLocale(),
                'isRTL' => $localeInfo->isRTL(),
                // Developer mode is the Admin → Member Portal switch
                // `bPortalDeveloperMode` (#9864): the template cache is off and
                // PortalTwig prints the template name in an HTML comment.
                'developerMode' => PortalTwig::isDeveloperMode(),
                // Whether the calendar and volunteering sections are there for
                // this member: their switches (#9864) through the capabilities.
                // A theme drawing its own home page reads these.
                'showCalendar' => $this->portalCan(CorePortalCapabilities::CALENDAR_VIEW),
                'showVolunteer' => $this->portalCan(CorePortalCapabilities::VOLUNTEER_USE),
                'allowBirthdayEdit' => SystemConfig::getBooleanValue('bPortalAllowBirthdayEdit'),
                // Whether the active theme (or the default it falls back to)
                // carries these optional files, so the layout can skip a <link>
                // or <script> that would only 404. Extra fields on an existing
                // global are a compatible addition (design §3.6).
                'hasThemeCss' => ThemeAssetStreamer::hasAsset($this->themeName, 'theme.css'),
                'hasThemeJs' => ThemeAssetStreamer::hasAsset($this->themeName, 'theme.js'),
                // The per-user colour mode, so the layout can stamp
                // `data-bs-theme` before first paint: 'auto', 'light' or 'dark'.
                'colorMode' => $this->getColorMode(),
                // Pre-rendered fragments the layout has to emit verbatim: the
                // head/footer HTML active plugins inject, and the two JSON
                // blobs the bootstrap and locale-loader scripts need. They are
                // Markup so autoescape leaves them alone, and the JSON is
                // encoded with InputUtils::jsonEncodeForScript().
                'pluginHead' => new Markup(PluginManager::getPluginHeadContent(), 'UTF-8'),
                'pluginFooter' => new Markup(PluginManager::getPluginFooterContent(), 'UTF-8'),
                'bootstrapJson' => new Markup($this->getBootstrapJson($localeInfo), 'UTF-8'),
                'localeConfigJson' => new Markup(
                    InputUtils::jsonEncodeForScript($localeInfo->getLocaleConfigArray()),
                    'UTF-8'
                ),
            ],
        ];
    }

    /**
     * The staff account's colour mode, normalised for the template: the user
     * setting stores 'default' for light. A member session has no setting.
     */
    private function getColorMode(): string
    {
        $user = PortalContext::staffUser();
        if ($user === null) {
            return 'auto';
        }

        return $user->getThemeMode() === 'default' ? 'light' : (string) $user->getThemeMode();
    }

    /**
     * The `window.CRM` seed every core bundle expects, in the same shape
     * Include/HeaderNotLoggedIn.php writes it.
     */
    private function getBootstrapJson(LocaleInfo $localeInfo): string
    {
        return InputUtils::jsonEncodeForScript([
            'root' => SystemURLs::getRootPath(),
            'churchWebSite' => ChurchMetaData::getChurchWebSite(),
            'lang' => $localeInfo->getLanguageCode(),
            'isRTL' => $localeInfo->isRTL(),
            'systemLocale' => $localeInfo->getSystemLocale(),
            'locale' => $localeInfo->getLocale(),
            'shortLocale' => $localeInfo->getShortLocale(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getChurch(): array
    {
        $logoUrl = ChurchMetaData::getChurchLogoURL();

        return [
            'name' => ChurchMetaData::getChurchName(),
            'address' => ChurchMetaData::getChurchAddress(),
            'city' => ChurchMetaData::getChurchCity(),
            'state' => ChurchMetaData::getChurchState(),
            'zip' => ChurchMetaData::getChurchZip(),
            'phone' => ChurchMetaData::getChurchPhone(),
            'email' => ChurchMetaData::getChurchEmail(),
            'website' => self::webAddressOrEmpty(ChurchMetaData::getChurchWebSite()),
            'logoUrl' => $logoUrl,
            'logoOnDarkUrl' => $logoUrl === SystemURLs::getURL() . self::STOCK_LOGO
                ? SystemURLs::getRootPath() . self::STOCK_LOGO_ON_DARK
                : $logoUrl,
            // The church's own social accounts (#9907), already filtered to
            // the configured ones and ordered X, YouTube, Facebook, Instagram.
            // Each entry is {id, label, url, icon}; empty when none is set.
            'socialLinks' => array_values(array_filter(
                ChurchMetaData::getChurchSocialLinks(),
                static fn (array $link): bool => ChurchMetaData::isValidSocialUrl((string) $link['url'])
            )),
        ];
    }

    /**
     * These addresses are typed by an administrator and land in an `href`, where
     * escaping cannot stop a `javascript:` URL: only http(s) is handed to a theme.
     */
    private static function webAddressOrEmpty(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return filter_var($url, FILTER_VALIDATE_URL) !== false && in_array($scheme, ['http', 'https'], true) ? $url : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function getMember(): array
    {
        $person = PortalContext::person();
        if (!$person instanceof Person) {
            return [
                'id' => 0,
                'firstName' => '',
                'lastName' => '',
                'fullName' => '',
                'familyName' => '',
                'email' => '',
                'avatarUrl' => '',
                'familyId' => 0,
                'isTeamLeader' => false,
                'isStaff' => false,
            ];
        }

        $staffUser = PortalContext::staffUser();

        return [
            'id' => (int) $person->getId(),
            'firstName' => (string) $person->getFirstName(),
            'lastName' => (string) $person->getLastName(),
            'fullName' => (string) $person->getFullName(),
            // Not in design §3.4's table, but the home page greets a member with
            // their family's name; adding a field is a compatible change (§3.6).
            'familyName' => $person->getFamily() ? (string) $person->getFamily()->getName() : '',
            'email' => (string) ($person->getEmail() ?? ''),
            // The portal's own endpoint, not /api/person/{id}/photo: a
            // self-service session is refused that path, so an avatar pointed
            // there renders broken for exactly the people the portal is for.
            // Empty when nobody has uploaded a photo, like profile.photoUrl.
            'avatarUrl' => PortalSelfService::getPhotoUrl($person, $person),
            'familyId' => (int) $person->getFamId(),
            // Kept for themes written before portal_can(); new templates ask
            // portal_can('volunteer.team.manage') instead.
            'isTeamLeader' => $this->portalCan(CorePortalCapabilities::TEAM_MANAGE),
            'isStaff' => $staffUser !== null && !$staffUser->isEditSelfExclusive(),
        ];
    }

    /**
     * Read and clear the queued flash messages.
     *
     * @return array<int, array{type: string, message: string}>
     */
    private function takeFlash(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return [];
        }
        $flash = $_SESSION[self::FLASH_SESSION_KEY] ?? [];
        unset($_SESSION[self::FLASH_SESSION_KEY]);

        return is_array($flash) ? $flash : [];
    }
}
