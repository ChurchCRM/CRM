<?php

namespace ChurchCRM\Portal;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\LocaleInfo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Service\ImpersonationService;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Throwable;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\Markup;
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
     * The per-request Content-Security-Policy nonce. Every inline `<script>` in
     * a portal template — core or theme — must carry it.
     */
    public function nonce(): string
    {
        return (string) SystemURLs::getCSPNonce();
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
                // Masquerade ("Login as User", #9843/#9844). True when an
                // administrator is acting as this member; the layout then hides the
                // staff bar — "viewing as yourself" would be a lie — and renders
                // `portal.impersonationBanner` in its place.
                'impersonating' => ImpersonationService::isActive(),
                // Developer mode is the Admin → Member Portal switch
                // `bPortalDeveloperMode` (#9864): the template cache is off and
                // PortalTwig prints the template name in an HTML comment.
                'developerMode' => PortalTwig::isDeveloperMode(),
                // The Admin → Member Portal section switches (#9864). A theme
                // that draws its own home page reads these to decide whether a
                // section belongs on the page at all.
                'showCalendar' => SystemConfig::getBooleanValue('bPortalShowCalendar'),
                'showVolunteer' => SystemConfig::getBooleanValue('bPortalShowVolunteer'),
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
                // The masquerade banner, rendered by the SAME include both admin
                // header layouts use (Include/ImpersonationBanner.php) so its markup,
                // its ids and its wording can never drift between the admin shell and
                // the portal. Empty when nobody is impersonating.
                'impersonationBanner' => new Markup($this->renderImpersonationBanner(), 'UTF-8'),
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
     * The masquerade banner's HTML, or '' when nobody is impersonating.
     *
     * `Include/ImpersonationBanner.php` echoes its markup and returns early when the
     * session carries no masquerade, so it is captured rather than called. Rendering
     * the include instead of re-writing the bar in Twig is the whole point: the exit
     * form, its CSRF token, its ids and the sentence an administrator reads are
     * defined once (#9843) and the portal shows exactly what the admin shell shows.
     *
     * The portal bundle styles `.impersonation-bar` itself (`_portal-bar.scss`) —
     * the core stylesheet this markup was written against is not loaded here.
     */
    private function renderImpersonationBanner(): string
    {
        if (!ImpersonationService::isActive()) {
            return '';
        }

        ob_start();

        try {
            require SystemURLs::getDocumentRoot() . '/Include/ImpersonationBanner.php';
        } catch (Throwable $e) {
            ob_end_clean();
            LoggerUtils::getAppLogger()->error('Could not render the portal masquerade banner', [
                'error' => $e->getMessage(),
            ]);

            return '';
        }

        return (string) ob_get_clean();
    }

    /**
     * The per-user colour mode, normalised for the template: the user setting
     * stores 'default' for light.
     */
    private function getColorMode(): string
    {
        $user = AuthenticationManager::getCurrentUser();
        if (!$user instanceof User) {
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
     * @return array<string, string|float>
     */
    private function getChurch(): array
    {
        return [
            'name' => ChurchMetaData::getChurchName(),
            'address' => ChurchMetaData::getChurchAddress(),
            'city' => ChurchMetaData::getChurchCity(),
            'state' => ChurchMetaData::getChurchState(),
            'zip' => ChurchMetaData::getChurchZip(),
            'phone' => ChurchMetaData::getChurchPhone(),
            'email' => ChurchMetaData::getChurchEmail(),
            'website' => ChurchMetaData::getChurchWebSite(),
            'logoUrl' => ChurchMetaData::getChurchLogoURL(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getMember(): array
    {
        $user = AuthenticationManager::getCurrentUser();
        if (!$user instanceof User) {
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

        $person = $user->getPerson();
        $personId = (int) $user->getPersonId();

        return [
            'id' => $personId,
            'firstName' => $person ? (string) $person->getFirstName() : '',
            'lastName' => $person ? (string) $person->getLastName() : '',
            'fullName' => (string) $user->getFullName(),
            // Not in design §3.4's table, but the home page greets a member with
            // their family's name; adding a field is a compatible change (§3.6).
            'familyName' => $person && $person->getFamily() ? (string) $person->getFamily()->getName() : '',
            'email' => (string) ($user->getEmail() ?? ''),
            'avatarUrl' => SystemURLs::getRootPath() . '/api/person/' . $personId . '/photo',
            'familyId' => $person ? (int) $person->getFamId() : 0,
            // True when this person holds a volunteer `team` scope — including on
            // a self-service login, which is the whole of the D14 revision (P17,
            // #9867). MP7 turns it into the "My Teams" nav entry; until then it is
            // a fact a theme may already render.
            'isTeamLeader' => $user->isVolunteerTeamLeaderEnabled(),
            'isStaff' => !$user->isEditSelfExclusive(),
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
