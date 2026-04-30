<?php

namespace ChurchCRM\view;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

class PageHeader
{
    /**
     * Build a structured breadcrumb array for Header.php.
     *
     * Each item is [label] or [label, relativeUrl]. The last item is automatically
     * marked active. URLs are relative to the application root — getRootPath() is
     * prepended automatically, so pass e.g. '/groups/dashboard' not the full path.
     *
     * @param array<array{0: string, 1?: string}> $items
     * @return array<array{label: string, url?: string, active?: bool}>
     */
    public static function breadcrumbs(array $items): array
    {
        $rootPath = SystemURLs::getRootPath();
        $result = [];
        $last = count($items) - 1;

        foreach ($items as $i => $item) {
            $crumb = ['label' => $item[0]];
            if ($i === $last) {
                $crumb['active'] = true;
            } elseif (isset($item[1])) {
                $url = $item[1];
                if ($url !== '' && !str_starts_with($url, 'http')) {
                    $url = $rootPath . $url;
                }
                $crumb['url'] = $url;
            }
            $result[] = $crumb;
        }

        return $result;
    }

    /**
     * Build HTML for page header action buttons.
     *
     * Each button: ['label' => ..., 'url' => ..., 'icon' => ...] for link buttons,
     * or ['label' => ..., 'icon' => ..., 'collapse' => '#targetId'] for settings toggles.
     * Buttons are only rendered for admin users unless 'adminOnly' => false is set.
     *
     * @param array<array{label: string, url?: string, icon?: string, collapse?: string, adminOnly?: bool}> $buttons
     * @return string HTML
     */
    public static function buttons(array $buttons): string
    {
        if (empty($buttons)) {
            return '';
        }

        $isAdmin = AuthenticationManager::getCurrentUser()->isAdmin();

        $html = '<div class="btn-list">';

        foreach ($buttons as $btn) {
            $adminOnly = $btn['adminOnly'] ?? true;
            if ($adminOnly && !$isAdmin) {
                continue;
            }

            $label = $btn['label'];
            $icon = isset($btn['icon']) ? '<i class="fa-solid ' . $btn['icon'] . ' me-1"></i>' : '';

            if (isset($btn['collapse'])) {
                // Settings toggle button (Bootstrap collapse)
                $target = $btn['collapse'];
                $html .= '<button class="btn btn-sm btn-outline-secondary" type="button"'
                    . ' data-bs-toggle="collapse" data-bs-target="' . $target . '"'
                    . ' aria-expanded="false" aria-controls="' . ltrim($target, '#') . '">'
                    . $icon . $label . '</button>';
            } elseif (isset($btn['offcanvas'])) {
                // Offcanvas trigger button
                $target = $btn['offcanvas'];
                $html .= '<button class="btn btn-sm btn-outline-secondary" type="button"'
                    . ' data-bs-toggle="offcanvas" data-bs-target="' . $target . '"'
                    . ' aria-controls="' . ltrim($target, '#') . '">'
                    . $icon . $label . '</button>';
            } else {
                // Link button
                $url = $btn['url'] ?? '#';
                $isExternal = $url !== '#' && str_starts_with($url, 'http');
                if (!$isExternal && $url !== '#') {
                    $url = SystemURLs::getRootPath() . $url;
                }
                $targetAttr = $isExternal ? ' target="_blank" rel="noopener noreferrer"' : '';
                $html .= '<a href="' . $url . '" class="btn btn-sm btn-outline-secondary"' . $targetAttr . '>'
                    . $icon . $label . '</a>';
            }
        }

        $html .= '</div>';

        return $html;
    }
}
