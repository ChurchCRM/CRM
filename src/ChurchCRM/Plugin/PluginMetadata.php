<?php

namespace ChurchCRM\Plugin;

/**
 * Data class representing plugin metadata from plugin.json.
 */
class PluginMetadata
{
    private string $id;
    private string $name;
    private string $description;
    private string $version;
    private string $author;
    private ?string $authorUrl;
    private string $type;
    private string $minimumCRMVersion;
    private array $dependencies;
    private string $mainClass;
    private string $path;
    private array $settings;
    private array $menuItems;
    private array $hooks;
    private ?string $settingsUrl;

    public function __construct(array $data, string $path)
    {
        $this->id = $data['id'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->version = $data['version'] ?? '1.0.0';
        $this->author = $data['author'] ?? '';
        $this->authorUrl = $data['authorUrl'] ?? null;
        $this->type = $data['type'] ?? 'community';
        $this->minimumCRMVersion = $data['minimumCRMVersion'] ?? '5.0.0';
        $this->dependencies = $data['dependencies'] ?? [];
        $this->mainClass = $data['mainClass'] ?? '';
        $this->path = $path;
        $this->settings = $data['settings'] ?? [];
        $this->menuItems = $data['menuItems'] ?? [];
        $this->hooks = $data['hooks'] ?? [];
        $this->settingsUrl = $data['settingsUrl'] ?? null;
    }

    /**
     * Create metadata from a plugin.json file.
     */
    public static function fromJsonFile(string $jsonPath): ?self
    {
        if (!file_exists($jsonPath)) {
            return null;
        }

        $content = file_get_contents($jsonPath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return null;
        }

        return new self($data, dirname($jsonPath));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getAuthorUrl(): ?string
    {
        return $this->authorUrl;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMinimumCRMVersion(): string
    {
        return $this->minimumCRMVersion;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function getMainClass(): string
    {
        return $this->mainClass;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getMenuItems(): array
    {
        return $this->menuItems;
    }

    public function getHooks(): array
    {
        return $this->hooks;
    }

    public function getSettingsUrl(): ?string
    {
        return $this->settingsUrl;
    }

    /**
     * Validate the metadata has required fields.
     */
    public function isValid(): bool
    {
        return !empty($this->id)
            && !empty($this->name)
            && !empty($this->version)
            && !empty($this->mainClass);
    }

    /**
     * Convert to array for serialization.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'author' => $this->author,
            'authorUrl' => $this->authorUrl,
            'type' => $this->type,
            'minimumCRMVersion' => $this->minimumCRMVersion,
            'dependencies' => $this->dependencies,
            'mainClass' => $this->mainClass,
            'path' => $this->path,
            'settings' => $this->settings,
            'menuItems' => $this->menuItems,
            'hooks' => $this->hooks,
            'settingsUrl' => $this->settingsUrl,
        ];
    }
}
