<?php

namespace ChurchCRM\view;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Service\GradeService;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\CustomFieldUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\MiscUtils;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class RecordEditorView
{
    public function render(string $template, array $values): string
    {
        $twig = new Environment(new FilesystemLoader(SystemURLs::getDocumentRoot() . '/people/views'), [
            'autoescape' => 'html',
            'cache' => SystemURLs::getDocumentRoot() . '/Include/cache/twig-record-editors',
            'auto_reload' => true,
        ]);
        $functions = [
            'gettext' => 'gettext',
            'root_path' => [SystemURLs::class, 'getRootPath'],
            'csp_nonce' => [SystemURLs::class, 'getCSPNonce'],
            'asset_url' => [SystemURLs::class, 'assetVersioned'],
            'config' => [SystemConfig::class, 'getValue'],
            'config_bool' => [SystemConfig::class, 'getBooleanValue'],
            'date_picker_format' => [DateTimeUtils::class, 'datePickerFormat'],
            'date_picker_js_format' => [DateTimeUtils::class, 'datePickerJsFormat'],
            'date_picker' => [DateTimeUtils::class, 'formatForDatePicker'],
            'current_user' => [AuthenticationManager::class, 'getCurrentUser'],
            'address_line' => [MiscUtils::class, 'formatAddressLine'],
            'grade_names' => [GradeService::class, 'names'],
            'grade_options' => static function (): array {
                $grades = [];
                foreach (GradeService::names() as $id => $name) {
                    $grades[] = ['id' => $id, 'name' => $name];
                }
                return $grades;
            },
            'person_uri' => [Person::class, 'getViewURIForId'],
        ];
        foreach ($functions as $name => $callback) {
            $twig->addFunction(new TwigFunction($name, $callback));
        }
        $htmlFunctions = [
            'escape_html' => [InputUtils::class, 'escapeHTML'],
            'escape_attr' => [InputUtils::class, 'escapeAttribute'],
            'config_attr' => [SystemConfig::class, 'getValueForAttr'],
            'json_script' => [InputUtils::class, 'jsonEncodeForScript'],
            'csrf_input' => [CSRFUtils::class, 'getTokenInputField'],
            'custom_form' => static function (int $type, string $name, ?string $value, mixed $special, bool $firstPass): string {
                ob_start();
                try {
                    CustomFieldUtils::renderForm($type, $name, $value, $special, $firstPass);
                    return (string) ob_get_clean();
                } catch (\Throwable $e) {
                    ob_end_clean();
                    throw $e;
                }
            },
        ];
        foreach ($htmlFunctions as $name => $callback) {
            $twig->addFunction(new TwigFunction($name, $callback, ['is_safe' => ['html']]));
        }
        return $twig->render($template, $values);
    }
}
