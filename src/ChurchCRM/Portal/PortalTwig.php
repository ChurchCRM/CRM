<?php

namespace ChurchCRM\Portal;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Twig\GettextExtension;
use ChurchCRM\Utils\LoggerUtils;
use Laminas\Diactoros\Response as Psr7Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Throwable;
use Twig\Environment;
use Twig\Error\Error as TwigError;
use Twig\Loader\FilesystemLoader;

/**
 * The Twig environment the Member Portal renders with, and the one place a
 * portal page is turned into a response.
 *
 * Loader order is *active theme → default theme*, so a theme file with the same
 * relative path wins; the `@default` namespace lets an override extend the core
 * template it replaces. Templates compile to a cache under `Include/cache/` with
 * `auto_reload`, which is what makes a file edited over FTP live on the next
 * request (decision P7).
 *
 * When the active theme fails at render time the failure is loud (P8): the
 * administrator gets the error page with theme, file, line and message, members
 * get a portal-styled "temporarily unavailable" page, and both the application
 * log and PHP's `error_log` record it. There is no silent fallback to `default`.
 */
class PortalTwig
{
    /** Compile cache, under the update-safe and deny-all `Include/` directory. */
    public const CACHE_DIRECTORY = '/Include/cache/twig-portal';

    public const THEME_ERROR_TEMPLATE = 'errors/theme-error.html.twig';
    public const UNAVAILABLE_TEMPLATE = 'errors/unavailable.html.twig';

    /**
     * The two side effects every portal page needs before it renders: the
     * security headers that mint the CSP nonce each inline script carries, and
     * the plugin system whose head/footer HTML the layout prints.
     *
     * `PortalAccessMiddleware` calls this for the portal's own routes. A route
     * outside `/portal` that renders a portal page — the password and
     * two-factor pages a self-service session opens (#9865) — calls it itself.
     * Both operations are idempotent, exactly as `PageInit.php` relies on.
     */
    public static function preparePage(): void
    {
        $documentRoot = rtrim(SystemURLs::getDocumentRoot(), '/\\');

        require_once $documentRoot . '/Include/Header-Security.php';

        PluginManager::init($documentRoot . '/plugins');
    }

    /**
     * Build the per-request environment for the active theme.
     *
     * @throws ThemeException when the configured theme folder is gone
     */
    public static function create(?string $activeNavId = null): Environment
    {
        $themeName = ThemeManager::getActiveThemeName();
        if (!ThemeManager::themeExists($themeName)) {
            throw ThemeException::themeFolderNotFound($themeName);
        }

        $paths = [];
        $themeTemplates = ThemeManager::getTemplatesPath($themeName);
        if ($themeTemplates !== null) {
            $paths[] = $themeTemplates;
        }
        $defaultTemplates = ThemeManager::getDefaultTemplatesPath();
        if (!in_array($defaultTemplates, $paths, true)) {
            $paths[] = $defaultTemplates;
        }

        return self::buildEnvironment($paths, $themeName, $activeNavId);
    }

    /**
     * An environment restricted to the default theme. The theme-error and
     * unavailable pages render through it so a broken theme cannot break the
     * page that reports it.
     */
    public static function createDefaultOnly(?string $activeNavId = null): Environment
    {
        return self::buildEnvironment(
            [ThemeManager::getDefaultTemplatesPath()],
            ThemeManager::DEFAULT_THEME,
            $activeNavId
        );
    }

    /**
     * An environment used only to compile a theme's templates during validation.
     * It never renders, so it runs without a compile cache.
     */
    public static function createValidationEnvironment(string $templatesPath): Environment
    {
        $twig = new Environment(ThemeValidator::createLoader($templatesPath), [
            'autoescape' => 'html',
            'strict_variables' => false,
            'cache' => false,
            'auto_reload' => true,
        ]);
        // The same extensions the portal renders with: without them every
        // `gettext()` and `url()` in a template would compile as an unknown
        // function and the validator would call a working theme broken.
        $twig->addExtension(new GettextExtension());
        $twig->addExtension(new PortalExtension(ThemeManager::DEFAULT_THEME));

        return $twig;
    }

    /**
     * Render a portal page. `$model` is the page's own view-model; the globals
     * of design §3.4 are supplied by PortalExtension.
     *
     * @param array<string, mixed> $model
     */
    public static function render(
        ResponseInterface $response,
        string $template,
        array $model = [],
        ?string $activeNavId = null
    ): ResponseInterface {
        try {
            $html = self::create($activeNavId)->render($template, $model);
        } catch (ThemeException | TwigError $e) {
            return self::renderThemeFailure($response, $e, $activeNavId);
        }

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * The portal's error handler, handed to MvcAppFactory.
     *
     * The shared Tabler error page renders the admin shell, which a member must
     * never see, so a portal error is drawn with the portal's own error
     * templates instead — and a theme may override them like any other page.
     */
    public static function createErrorHandler(): callable
    {
        return function (
            ServerRequestInterface $request,
            Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails
        ): ResponseInterface {
            $status = match (true) {
                $exception instanceof HttpNotFoundException => 404,
                $exception instanceof HttpForbiddenException => 403,
                $exception instanceof HttpMethodNotAllowedException => 405,
                default => 500,
            };

            $logContext = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'path' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
            ];
            if ($status >= 500) {
                $logContext['trace'] = $exception->getTraceAsString();
                LoggerUtils::getAppLogger()->error('Member Portal error', $logContext);
            } else {
                LoggerUtils::getAppLogger()->info('Member Portal ' . $status, $logContext);
            }

            // 405 has no page of its own; it reads to a member as "not found".
            $template = 'errors/' . ($status === 405 ? 404 : $status) . '.html.twig';

            return self::render(new Psr7Response(), $template)->withStatus($status);
        };
    }

    /**
     * The failure path of design §3.3: log to the application log *and* to
     * `error_log()`, then show the administrator the detail and the member the
     * "temporarily unavailable" page — both from the default theme.
     */
    private static function renderThemeFailure(
        ResponseInterface $response,
        Throwable $error,
        ?string $activeNavId
    ): ResponseInterface {
        $themeName = $error instanceof ThemeException && $error->getThemeName() !== ''
            ? $error->getThemeName()
            : ThemeManager::getActiveThemeName();
        $file = $error instanceof TwigError ? (string) $error->getSourceContext()?->getName() : '';
        $line = $error instanceof TwigError ? max(0, $error->getTemplateLine()) : 0;
        $message = $error instanceof TwigError ? $error->getRawMessage() : $error->getMessage();

        $logContext = [
            'theme' => $themeName,
            'file' => $file,
            'line' => $line,
            'message' => $message,
        ];
        LoggerUtils::getAppLogger()->error('Member Portal theme render failed', $logContext);
        error_log(sprintf(
            'ChurchCRM Member Portal: theme "%s" failed to render — %s (%s line %d)',
            $themeName,
            $message,
            $file !== '' ? $file : 'unknown file',
            $line
        ));

        $twig = self::createDefaultOnly($activeNavId);
        if (self::currentUserIsAdministrator()) {
            $html = $twig->render(self::THEME_ERROR_TEMPLATE, [
                'themeName' => $themeName,
                'file' => $file,
                'line' => $line,
                'message' => $message,
            ]);
            $status = 500;
        } else {
            $html = $twig->render(self::UNAVAILABLE_TEMPLATE);
            $status = 503;
        }

        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withStatus($status);
    }

    /**
     * @param array<int, string> $templatePaths
     */
    private static function buildEnvironment(array $templatePaths, string $themeName, ?string $activeNavId): Environment
    {
        $loader = new FilesystemLoader($templatePaths);
        $defaultTemplates = ThemeManager::getDefaultTemplatesPath();
        if (is_dir($defaultTemplates)) {
            // `@default/...` always addresses the core template, so an override
            // can extend the very template it replaces.
            $loader->addPath($defaultTemplates, 'default');
        }

        $twig = new Environment($loader, [
            'autoescape' => 'html',
            'strict_variables' => false,
            'cache' => self::getCacheDirectory(),
            'auto_reload' => true,
        ]);
        $twig->addExtension(new GettextExtension());
        $twig->addExtension(new PortalExtension($themeName, $activeNavId));

        return $twig;
    }

    /**
     * The compile cache directory, created on first use. When it cannot be
     * written (a read-only deployment, say), templates are compiled in memory
     * instead of failing the request.
     *
     * @return string|false
     */
    private static function getCacheDirectory(): string|false
    {
        $cacheDirectory = rtrim(SystemURLs::getDocumentRoot(), '/\\') . self::CACHE_DIRECTORY;
        if (!is_dir($cacheDirectory) && !@mkdir($cacheDirectory, 0o775, true) && !is_dir($cacheDirectory)) {
            return false;
        }

        return is_writable($cacheDirectory) ? $cacheDirectory : false;
    }

    private static function currentUserIsAdministrator(): bool
    {
        $user = AuthenticationManager::getCurrentUser();

        return $user instanceof User && $user->isAdmin();
    }
}
