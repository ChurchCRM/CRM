<?php

namespace ChurchCRM\Plugins\CustomLinks;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Config\Menu\MenuItem;
use ChurchCRM\model\ChurchCRM\MenuLinkQuery;
use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;

class CustomLinksPlugin extends AbstractPlugin
{
    private static ?CustomLinksPlugin $instance = null;

    public function __construct(string $basePath = '')
    {
        parent::__construct($basePath);
        self::$instance = $this;
    }

    public static function getInstance(): ?CustomLinksPlugin
    {
        return self::$instance;
    }

    public function getId(): string
    {
        return 'custom-links';
    }

    public function getName(): string
    {
        return gettext('Custom Menu Links');
    }

    public function getDescription(): string
    {
        return gettext('Add custom external links to the navigation menu.');
    }

    public function boot(): void
    {
        // Register menu hook to add the Links menu when this plugin is active
        HookManager::addFilter(Hooks::MENU_BUILDING, [$this, 'addLinksMenu'], 10);
    }

    /**
     * Hook callback to add the Links menu to the navigation.
     *
     * @param array $menus Current menu items
     * @return array Modified menu items with Links menu added
     */
    public function addLinksMenu(array $menus): array
    {
        // Only add menu if plugin is enabled
        if (!$this->isEnabled()) {
            return $menus;
        }

        $links = $this->getNavigationMenuItems();
        
        // Only show menu if there are links configured OR user is admin (so they can manage)
        $isAdmin = AuthenticationManager::getCurrentUser()->isAdmin();
        if (empty($links) && !$isAdmin) {
            return $menus;
        }

        $menu = new MenuItem(gettext('Links'), '', true, 'fa-link');
        
        // Add configured links first
        foreach ($links as $link) {
            $menu->addSubMenu(new MenuItem($link['label'], $link['url'], true, $link['icon']));
        }
        
        // Add admin link for managing links (admin only)
        if ($isAdmin) {
            $menu->addSubMenu(new MenuItem(gettext('Manage Links'), 'plugins/custom-links/manage', true, 'fa-cog'));
        }

        // Add the Links menu to the menus array
        $menus['Links'] = $menu;

        return $menus;
    }

    public function activate(): void
    {
        // No activation tasks needed - uses existing menu_links table
    }

    public function deactivate(): void
    {
        // Keep links in database when deactivated
    }

    public function uninstall(): void
    {
        // Optionally clean up all links when uninstalled
        // For now, preserve links in case plugin is re-enabled
    }

    public function isConfigured(): bool
    {
        // Always configured - no external API keys needed
        return true;
    }

    public function getConfigurationError(): ?string
    {
        return null;
    }

    /**
     * Get all menu links ordered by display order.
     *
     * @return array Array of menu links
     */
    public function getMenuLinks(): array
    {
        return MenuLinkQuery::create()
            ->orderByOrder()
            ->find()
            ->toArray();
    }

    /**
     * Get menu items for the Links menu in navigation.
     * This provides the custom links as a navigation menu.
     *
     * @return array Menu items for navigation
     */
    public function getNavigationMenuItems(): array
    {
        $links = MenuLinkQuery::create()
            ->orderByOrder()
            ->select(['Name', 'Uri'])
            ->find()
            ->toArray();

        $items = [];
        foreach ($links as $link) {
            $items[] = [
                'label' => $link['Name'],
                'url' => $link['Uri'],
                'icon' => 'fa-solid fa-external-link-alt',
                'external' => true,
            ];
        }

        return $items;
    }

    /**
     * Get count of configured links.
     *
     * @return int Number of links
     */
    public function getLinkCount(): int
    {
        return MenuLinkQuery::create()->count();
    }
}
