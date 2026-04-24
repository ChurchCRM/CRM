<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

/**
 * Plugin Management Admin Page.
 *
 * Displays all installed plugins with inline settings.
 *
 * Variables available:
 * - $corePlugins: array of core plugin data with settings
 * - $communityPlugins: array of community plugin data with settings
 */

/**
 * Render a plugin card with inline settings.
 */
function renderPluginCard(array $plugin, string $rootPath, string $nonce): void {
    $pluginId = htmlspecialchars($plugin['id']);
    $hasError = !empty($plugin['hasError']);
    $hasSettings = !empty($plugin['settings']);
    $hasHelp = !empty($plugin['help']) && (!empty($plugin['help']['summary']) || !empty($plugin['help']['sections']));
    $isActive = $plugin['isActive'] ?? false;
    $helpData = $hasHelp ? htmlspecialchars(json_encode($plugin['help']), ENT_QUOTES, 'UTF-8') : '';

    // Verification, risk, quarantine state surfaced by PluginManager.
    $isCommunity = ($plugin['type'] ?? 'community') === 'community';
    $isVerified = !empty($plugin['verified']);
    $verificationSource = $plugin['verificationSource'] ?? 'unknown';
    $verificationReason = $plugin['verificationReason'] ?? null;
    $isQuarantined = !empty($plugin['quarantined']);
    $quarantineReason = $plugin['quarantineReason'] ?? null;
    $risk = $plugin['risk'] ?? null;
    $riskSummary = $plugin['riskSummary'] ?? null;
    $permissions = $plugin['permissions'] ?? null;
    $canUninstall = !empty($plugin['canUninstall']);

    // Top border colour escalation: quarantine > error > enabled > default.
    $borderClass = 'card-secondary';
    if ($isQuarantined) {
        $borderClass = 'border-top border-warning border-3';
    } elseif ($hasError) {
        $borderClass = 'border-top border-danger border-3';
    } elseif ($isActive) {
        $borderClass = 'border-top border-success border-3';
    }
?>
    <div class="card <?= $borderClass ?>"
         data-plugin-id="<?= $pluginId ?>"
         data-plugin-type="<?= htmlspecialchars($plugin['type'] ?? 'community') ?>"
         data-plugin-verified="<?= $isVerified ? '1' : '0' ?>"
         data-plugin-quarantined="<?= $isQuarantined ? '1' : '0' ?>"
         <?php if ($hasHelp): ?>data-plugin-help="<?= $helpData ?>"<?php endif; ?>>
        <div class="card-header d-flex align-items-center" style="cursor: pointer;" class="plugin-card-header">
            <h3 class="card-title">
                <?php if ($isQuarantined): ?>
                    <i class="fa-solid fa-shield-halved text-warning me-3" title="<?= gettext('Quarantined') ?>"></i>
                <?php elseif ($hasError): ?>
                    <i class="fa-solid fa-triangle-exclamation text-danger me-3"></i>
                <?php elseif ($isActive): ?>
                    <i class="fa-solid fa-circle-check text-success me-3"></i>
                <?php else: ?>
                    <i class="fa-solid fa-circle-xmark text-secondary me-3"></i>
                <?php endif; ?>
                <strong><?= htmlspecialchars($plugin['name']) ?></strong>
                <span class="badge bg-info ms-2">v<?= htmlspecialchars($plugin['version']) ?></span>
                <?php if ($isQuarantined): ?>
                    <span class="badge bg-warning text-dark ms-2" title="<?= htmlspecialchars((string) $quarantineReason) ?>">
                        <i class="fa-solid fa-shield-halved me-1"></i><?= gettext('Quarantined') ?>
                    </span>
                <?php elseif ($hasError): ?>
                    <span class="badge bg-danger ms-2"><?= gettext('Error') ?></span>
                <?php elseif ($isActive): ?>
                    <span class="badge bg-green-lt text-green ms-2"><?= gettext('Enabled') ?></span>
                    <?php if (!$plugin['isConfigured']): ?>
                        <span class="badge bg-warning text-dark ms-2"><?= gettext('Needs Configuration') ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge bg-light text-dark ms-2"><?= gettext('Disabled') ?></span>
                <?php endif; ?>
                <?php if ($isCommunity && $isVerified): ?>
                    <span class="badge bg-green-lt text-green ms-2" title="<?= gettext('Matches an entry on the approved plugin list') ?>">
                        <i class="fa-solid fa-shield-check me-1"></i><?= gettext('Verified') ?>
                    </span>
                <?php elseif ($isCommunity && !$isVerified): ?>
                    <span class="badge bg-orange-lt text-orange ms-2"
                          title="<?= htmlspecialchars((string) ($verificationReason ?? gettext('Unverified plugin'))) ?>">
                        <i class="fa-solid fa-shield-halved me-1"></i><?= gettext('Unverified') ?>
                    </span>
                <?php endif; ?>
                <?php if ($risk !== null): ?>
                    <?php
                        $riskBadge = match ($risk) {
                            'low'    => 'bg-green-lt text-green',
                            'medium' => 'bg-yellow-lt text-yellow',
                            'high'   => 'bg-red-lt text-red',
                            default  => 'bg-secondary',
                        };
                    ?>
                    <span class="badge <?= $riskBadge ?> ms-2"
                          title="<?= htmlspecialchars((string) $riskSummary) ?>">
                        <i class="fa-solid fa-gauge-high me-1"></i><?= htmlspecialchars(ucfirst((string) $risk)) ?> <?= gettext('risk') ?>
                    </span>
                <?php endif; ?>
            </h3>
            <div class="card-tools ms-auto">
                <?php if ($hasHelp): ?>
                    <button type="button" class="btn btn-tool btn-plugin-help text-info"
                            data-plugin-id="<?= $pluginId ?>"
                            data-plugin-name="<?= htmlspecialchars($plugin['name']) ?>"
                            title="<?= gettext('Help') ?>"
                            onclick="event.stopPropagation();">
                        <i class="fa-solid fa-circle-question"></i>
                    </button>
                <?php endif; ?>
                <?php if (!$hasError): ?>
                    <?php if ($isActive): ?>
                        <button type="button" class="btn btn-tool btn-plugin-toggle text-danger"
                                data-action="disable" data-plugin-id="<?= $pluginId ?>"
                                title="<?= gettext('Disable Plugin') ?>"
                                onclick="event.stopPropagation();">
                            <i class="fa-solid fa-power-off"></i><?= gettext('Disable') ?>
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-tool btn-plugin-toggle text-success"
                                data-action="enable" data-plugin-id="<?= $pluginId ?>"
                                title="<?= gettext('Enable Plugin') ?>"
                                onclick="event.stopPropagation();">
                            <i class="fa-solid fa-play"></i><?= gettext('Enable') ?>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($isQuarantined): ?>
                    <button type="button" class="btn btn-tool btn-plugin-clear-quarantine text-warning"
                            data-plugin-id="<?= $pluginId ?>"
                            title="<?= gettext('Clear Quarantine') ?>"
                            onclick="event.stopPropagation();">
                        <i class="fa-solid fa-shield-halved"></i>
                    </button>
                <?php endif; ?>
                <?php if ($canUninstall): ?>
                    <button type="button" class="btn btn-tool btn-plugin-uninstall text-danger"
                            data-plugin-id="<?= $pluginId ?>"
                            data-plugin-name="<?= htmlspecialchars($plugin['name']) ?>"
                            title="<?= gettext('Uninstall (delete from disk)') ?>"
                            onclick="event.stopPropagation();">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn-tool btn-expand-plugin" title="<?= gettext('Expand') ?>" onclick="event.stopPropagation();">
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
            </div>
        </div>
        <div class="card-body" style="display: none;">
            <?php if ($isQuarantined): ?>
                <div class="alert alert-warning mb-3" role="alert">
                    <h4 class="alert-heading mb-1">
                        <i class="fa-solid fa-shield-halved me-2"></i><?= gettext('Plugin quarantined') ?>
                    </h4>
                    <p class="mb-1"><?= htmlspecialchars((string) ($quarantineReason ?? gettext('The plugin was automatically disabled after a runtime failure.'))) ?></p>
                    <p class="mb-0 small"><?= gettext('Clear the quarantine only after the underlying issue has been fixed. Enabling a quarantined plugin is refused by the server.') ?></p>
                </div>
            <?php endif; ?>
            <?php if ($isCommunity && !$isVerified && !$isQuarantined): ?>
                <div class="alert alert-warning mb-3" role="alert">
                    <h4 class="alert-heading mb-1">
                        <i class="fa-solid fa-shield-halved me-2"></i><?= gettext('Unverified plugin') ?>
                    </h4>
                    <p class="mb-1"><?= htmlspecialchars((string) ($verificationReason ?? gettext('This plugin is not on the ChurchCRM approved plugin list.'))) ?></p>
                    <p class="mb-0 small"><?= gettext('Review the plugin files on disk before enabling. Unverified plugins run with the same permissions as approved plugins.') ?></p>
                </div>
            <?php endif; ?>
            <?php if ($isCommunity && $isVerified && $riskSummary !== null): ?>
                <div class="alert alert-info mb-3" role="alert">
                    <strong><?= gettext('What this plugin does:') ?></strong>
                    <?= htmlspecialchars((string) $riskSummary) ?>
                    <?php if (!empty($permissions) && is_array($permissions)): ?>
                        <div class="mt-2">
                            <?php foreach ($permissions as $perm): ?>
                                <span class="badge bg-secondary me-1"><?= htmlspecialchars((string) $perm) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <p class="text-muted mb-2"><?= htmlspecialchars($plugin['description']) ?></p>
            <?php if (!empty($plugin['author'])): ?>
                <small class="text-muted">
                    <?= gettext('by') ?>
                    <?php if (!empty($plugin['authorUrl'])): ?>
                        <a href="<?= htmlspecialchars($plugin['authorUrl']) ?>" target="_blank">
                            <?= htmlspecialchars($plugin['author']) ?>
                        </a>
                    <?php else: ?>
                        <?= htmlspecialchars($plugin['author']) ?>
                    <?php endif; ?>
                </small>
            <?php endif; ?>
            <?php if ($hasError): ?>
                <div class="alert alert-danger mt-2 mb-2">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <?= htmlspecialchars($plugin['errorMessage'] ?? gettext('Plugin failed to load')) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($hasSettings): ?>
                <hr>
                <form class="plugin-settings-form" data-plugin-id="<?= $pluginId ?>">
                    <?php foreach ($plugin['settings'] as $setting): 
                        $settingKey = htmlspecialchars($setting['key'] ?? '');
                        $settingLabel = htmlspecialchars($setting['label'] ?? $settingKey);
                        $settingType = $setting['type'] ?? 'text';
                        $settingValue = htmlspecialchars($setting['value'] ?? '');
                        $settingHelp = $setting['help'] ?? '';
                        $configKey = htmlspecialchars($setting['configKey'] ?? '');
                        $isRequired = !empty($setting['required']);
                    ?>
                        <div class="mb-3">
                            <label for="<?= $pluginId ?>-<?= $settingKey ?>">
                                <?= $settingLabel ?>
                                <?php if ($isRequired): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            <?php if ($settingType === 'boolean'): ?>
                                <div class="form-check form-switch">
                                    <input type="checkbox" 
                                           class="form-check-input plugin-setting" 
                                           id="<?= $pluginId ?>-<?= $settingKey ?>"
                                           data-setting-key="<?= $settingKey ?>"
                                           data-config-key="<?= $configKey ?>"
                                           <?= ($settingValue === '1' || $settingValue === 'true') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= $pluginId ?>-<?= $settingKey ?>">
                                        <?= gettext('Enabled') ?>
                                    </label>
                                </div>
                            <?php elseif ($settingType === 'password'): 
                                $hasExistingValue = !empty($setting['hasValue']);
                                $placeholder = $hasExistingValue 
                                    ? gettext('Value is set - leave blank to keep current') 
                                    : ($isRequired ? gettext('Required') : '');
                            ?>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-control plugin-setting" 
                                           id="<?= $pluginId ?>-<?= $settingKey ?>"
                                           data-setting-key="<?= $settingKey ?>"
                                           data-config-key="<?= $configKey ?>"
                                           data-has-value="<?= $hasExistingValue ? '1' : '0' ?>"
                                           value=""
                                           placeholder="<?= $placeholder ?>">
                                    <?php if ($hasExistingValue): ?>
                                        <span class="input-group-text text-success" title="<?= gettext('Value is configured') ?>">
                                            <i class="fa-solid fa-check"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($settingType === 'select' && !empty($setting['options'])): ?>
                                <select class="form-select plugin-setting"
                                        id="<?= $pluginId ?>-<?= $settingKey ?>"
                                        data-setting-key="<?= $settingKey ?>"
                                        data-config-key="<?= $configKey ?>">
                                    <?php foreach ($setting['options'] as $index => $option): 
                                        $optionLabel = $setting['optionLabels'][$index] ?? $option;
                                    ?>
                                        <option value="<?= htmlspecialchars($option) ?>" 
                                                <?= $settingValue === $option ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($optionLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($settingType === 'multiselect' && !empty($setting['options'])): ?>
                                <?php $selectedValues = !empty($settingValue) ? array_map('trim', explode(',', $settingValue)) : []; ?>
                                <select class="form-select plugin-setting"
                                        id="<?= $pluginId ?>-<?= $settingKey ?>"
                                        data-setting-key="<?= $settingKey ?>"
                                        data-config-key="<?= $configKey ?>"
                                        multiple
                                        size="<?= min(8, count($setting['options'])) ?>">
                                    <?php foreach ($setting['options'] as $idx => $opt):
                                        $optLabel = $setting['optionLabels'][$idx] ?? $opt;
                                    ?>
                                        <option value="<?= htmlspecialchars($opt) ?>"
                                                <?= in_array($opt, $selectedValues, true) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($optLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($settingType === 'checkboxes' && !empty($setting['options'])): ?>
                                <?php
                                    $defaultValues = (!empty($setting['default']) && is_array($setting['default'])) ? $setting['default'] : [];
                                    $selectedValues = !empty($settingValue) ? array_map('trim', explode(',', $settingValue)) : $defaultValues;
                                ?>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <?php foreach ($setting['options'] as $idx => $opt):
                                        $optLabel = $setting['optionLabels'][$idx] ?? $opt;
                                        $cbId = $pluginId . '-' . $settingKey . '-cb-' . $idx;
                                    ?>
                                        <div class="form-check">
                                            <input class="form-check-input plugin-checkbox-item"
                                                   type="checkbox"
                                                   id="<?= $cbId ?>"
                                                   value="<?= htmlspecialchars($opt) ?>"
                                                   data-group-key="<?= htmlspecialchars($settingKey) ?>"
                                                   <?= in_array($opt, $selectedValues, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="<?= $cbId ?>">
                                                <?= htmlspecialchars($optLabel) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden"
                                       class="plugin-setting plugin-checkboxes"
                                       id="<?= $pluginId ?>-<?= $settingKey ?>"
                                       data-setting-key="<?= $settingKey ?>"
                                       data-config-key="<?= $configKey ?>"
                                       value="<?= htmlspecialchars($settingValue) ?>">
                            <?php else: ?>
                                <input type="text"
                                       class="form-control plugin-setting"
                                       id="<?= $pluginId ?>-<?= $settingKey ?>"
                                       data-setting-key="<?= $settingKey ?>"
                                       data-config-key="<?= $configKey ?>"
                                       value="<?= $settingValue ?>"
                                       placeholder="<?= $isRequired ? gettext('Required') : '' ?>">
                            <?php endif; ?>
                            <?php if ($settingHelp): ?>
                                <small class="form-text text-muted"><?= htmlspecialchars($settingHelp) ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="btn-group" role="group">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-floppy-disk me-2"></i><?= gettext('Save Settings') ?>
                        </button>
                        <?php if (!empty($plugin['hasTest'])): ?>
                        <button type="button" class="btn btn-outline-info btn-sm btn-test-settings"
                                data-plugin-id="<?= $pluginId ?>">
                            <i class="fa-solid fa-plug me-2"></i><?= gettext('Test Connection') ?>
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-danger btn-sm btn-reset-settings" data-plugin-id="<?= $pluginId ?>">
                            <i class="fa-solid fa-undo me-2"></i><?= gettext('Reset') ?>
                        </button>
                    </div>
                    <?php if (!empty($plugin['hasTest'])): ?>
                    <div class="mt-2 plugin-test-result" id="test-result-<?= $pluginId ?>" style="display:none;"></div>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php
}
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Core Plugins -->
        <div class="card">
            <div class="card-header border-0">
                <h3 class="card-title">
                    <i class="fa-solid fa-plug me-3"></i><?= gettext('Core Plugins') ?>
                </h3>
            </div>
            <div class="card-body">
                <?php if (empty($corePlugins)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-circle-info fa-2x mb-2"></i>
                        <p><?= gettext('No core plugins found') ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($corePlugins as $plugin): ?>
                        <?php renderPluginCard($plugin, $sRootPath, SystemURLs::getCSPNonce()); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Community Plugins -->
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <h3 class="card-title">
                    <i class="fa-solid fa-puzzle-piece me-3"></i><?= gettext('Community Plugins') ?>
                </h3>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-browse-approved">
                        <i class="fa-solid fa-list me-1"></i><?= gettext('Browse Approved') ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning" id="btn-install-from-url">
                        <i class="fa-solid fa-link me-1"></i><?= gettext('Install from URL') ?>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($communityPlugins)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-folder-open fa-2x mb-2"></i>
                        <p><?= gettext('No community plugins installed') ?></p>
                        <p class="small">
                            <?= gettext('Click "Browse Approved" above to install a vetted plugin, or "Install from URL" for an unverified build during development.') ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($communityPlugins as $plugin): ?>
                        <?php renderPluginCard($plugin, $sRootPath, SystemURLs::getCSPNonce()); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Plugin Info -->
        <div class="card border border-info">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title">
                    <i class="fa-solid fa-circle-info me-3"></i><?= gettext('Plugin Development') ?>
                </h3>
            </div>
            <div class="card-body">
                <p><?= gettext('ChurchCRM supports a plugin architecture for extending functionality.') ?></p>
                <ul class="ps-3">
                    <li><strong><?= gettext('Core plugins') ?>:</strong> <?= gettext('Shipped with ChurchCRM.') ?></li>
                    <li><strong><?= gettext('Community plugins') ?>:</strong> <?= gettext('Third-party extensions.') ?></li>
                </ul>
                <p class="small text-muted">
                    <?= gettext('Each plugin requires a') ?> <code>plugin.json</code> <?= gettext('manifest.') ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Browse Approved Plugins Modal -->
<div class="modal fade" id="approvedPluginsModal" tabindex="-1" role="dialog" aria-labelledby="approvedPluginsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approvedPluginsModalLabel">
                    <i class="fa-solid fa-list me-2"></i><?= gettext('Approved Community Plugins') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">
                    <?= gettext('These plugins have been reviewed and approved by the ChurchCRM maintainers. Click Install to download and install one — the plugin will not be enabled automatically.') ?>
                </p>
                <div id="approvedPluginsList" class="d-flex flex-column gap-2">
                    <div class="text-center text-muted py-4">
                        <i class="fa-solid fa-spinner fa-spin me-2"></i><?= gettext('Loading approved plugin list…') ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Close') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Install from URL Modal -->
<div class="modal fade" id="installFromUrlModal" tabindex="-1" role="dialog" aria-labelledby="installFromUrlModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning-lt">
                <h5 class="modal-title" id="installFromUrlModalLabel">
                    <i class="fa-solid fa-shield-halved me-2"></i><?= gettext('Install Plugin from URL (Unverified)') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <strong><?= gettext('This plugin will be installed as UNVERIFIED.') ?></strong><br>
                    <?= gettext('The ChurchCRM maintainers have not reviewed it. You are responsible for the security and behaviour of anything you install here. Prefer the Browse Approved flow whenever possible.') ?>
                </div>
                <form id="installFromUrlForm">
                    <div class="mb-3">
                        <label for="install-url-input" class="form-label"><?= gettext('Plugin zip URL (HTTPS only)') ?></label>
                        <input type="url" id="install-url-input" class="form-control" placeholder="https://example.org/releases/my-plugin-1.0.0.zip" required>
                        <small class="text-muted"><?= gettext('Must be an immutable release URL served over HTTPS.') ?></small>
                    </div>
                    <div class="mb-3">
                        <label for="install-sha256-input" class="form-label"><?= gettext('SHA-256 checksum') ?></label>
                        <input type="text" id="install-sha256-input" class="form-control font-monospace" pattern="[a-fA-F0-9]{64}" placeholder="0123456789abcdef… (64 hex characters)" required>
                        <small class="text-muted"><?= gettext('Get this from the plugin author. The installer will refuse the download if it does not match byte-for-byte.') ?></small>
                    </div>
                    <div class="mb-3">
                        <label for="install-plugin-id-input" class="form-label"><?= gettext('Plugin id') ?></label>
                        <input type="text" id="install-plugin-id-input" class="form-control" pattern="[a-z0-9][a-z0-9-]*" placeholder="my-plugin" required>
                        <small class="text-muted"><?= gettext('Kebab-case. Must match the top-level directory name inside the zip and the id in plugin.json.') ?></small>
                    </div>
                </form>
                <div id="installFromUrlResult"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button type="button" class="btn btn-warning" id="btn-submit-install-url">
                    <i class="fa-solid fa-download me-1"></i><?= gettext('Install Unverified') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Plugin Help Modal -->
<div class="modal fade" id="pluginHelpModal" tabindex="-1" role="dialog" aria-labelledby="pluginHelpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title" id="pluginHelpModalLabel">
                    <i class="fa-solid fa-circle-question me-3"></i>
                    <span id="pluginHelpTitle"><?= gettext('Plugin Help') ?></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
            </div>
            <div class="modal-body" id="pluginHelpContent">
                <!-- Help content will be injected here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?= gettext('Close') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    // Expand/collapse plugin card
    function togglePluginCard(card) {
        const body = card.find('.card-body');
        const expandBtn = card.find('.btn-expand-plugin i');
        const isHidden = body.is(':hidden');

        if (isHidden) {
            body.slideDown(200);
            expandBtn.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        } else {
            body.slideUp(200);
            expandBtn.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        }
    }

    // Click on card header to expand/collapse
    $('.plugin-card-header').on('click', function() {
        const card = $(this).closest('.card');
        togglePluginCard(card);
    });

    // Click on expand button to expand/collapse
    $('.btn-expand-plugin').on('click', function(e) {
        e.stopPropagation();
        const card = $(this).closest('.card');
        togglePluginCard(card);
    });

    // Initialize TomSelect on multiselect plugin settings
    if (typeof TomSelect !== 'undefined') {
        document.querySelectorAll('select[multiple].plugin-setting').forEach(function(el) {
            new TomSelect(el, {
                plugins: ['remove_button'],
                maxItems: null,
                create: false,
                placeholder: i18next.t('Select…'),
            });
        });
    }

    // Show plugin help modal
    $('.btn-plugin-help').on('click', function(e) {
        e.stopPropagation();
        const btn = $(this);
        const pluginId = btn.data('plugin-id');
        const pluginName = btn.data('plugin-name');
        const card = $('[data-plugin-id="' + pluginId + '"]');
        const helpData = card.data('plugin-help');
        
        if (!helpData) {
            return;
        }
        
        // Build help content HTML with i18next translation
        let contentHtml = '';
        
        // Summary - translate via i18next
        if (helpData.summary) {
            contentHtml += '<p class="lead">' + escapeHtml(i18next.t(helpData.summary)) + '</p>';
        }
        
        // Sections - translate titles and content
        if (helpData.sections && helpData.sections.length > 0) {
            helpData.sections.forEach(function(section) {
                contentHtml += '<div class="card-secondary mb-3">';
                contentHtml += '<div class="card-header"><h6 class="mb-0">' + escapeHtml(i18next.t(section.title)) + '</h6></div>';
                contentHtml += '<div class="card-body"><p class="mb-0" style="white-space: pre-line;">' + escapeHtml(i18next.t(section.content)) + '</p></div>';
                contentHtml += '</div>';
            });
        }
        
        // Links - translate labels (URLs are not translated)
        if (helpData.links && helpData.links.length > 0) {
            contentHtml += '<div class="mt-3"><strong>' + i18next.t('Helpful Links') + ':</strong><ul class="mb-0">';
            helpData.links.forEach(function(link) {
                contentHtml += '<li><a href="' + escapeHtml(link.url) + '" target="_blank" rel="noopener noreferrer">';
                contentHtml += escapeHtml(i18next.t(link.label)) + ' <i class="fa-solid fa-arrow-up-right-from-square fa-xs"></i></a></li>';
            });
            contentHtml += '</ul></div>';
        }
        
        if (!contentHtml) {
            contentHtml = '<p class="text-muted">' + i18next.t('No help available for this plugin.') + '</p>';
        }
        
        $('#pluginHelpTitle').text(pluginName + ' - ' + i18next.t('Help'));
        $('#pluginHelpContent').html(contentHtml);
        $('#pluginHelpModal').modal('show');
    });
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Enable/Disable plugin
    $('.btn-plugin-toggle').on('click', function(e) {
        e.stopPropagation();
        const btn = $(this);
        const pluginId = btn.data('plugin-id');
        const action = btn.data('action');
        
        btn.prop('disabled', true);
        
        $.ajax({
            url: window.CRM.root + '/plugins/api/plugins/' + pluginId + '/' + action,
            method: 'POST',
            dataType: 'json',
            contentType: 'application/json'
        })
        .done(function(response) {
            if (response.success) {
                window.CRM.notify(i18next.t('Plugin status updated'), { type: 'success' });
                location.reload();
            } else {
                window.CRM.notify(response.message || i18next.t('Failed to update plugin'), { type: 'error' });
                btn.prop('disabled', false);
            }
        })
        .fail(function(xhr) {
            const error = xhr.responseJSON?.message || i18next.t('Failed to update plugin');
            window.CRM.notify(error, { type: 'error' });
            btn.prop('disabled', false);
        });
    });

    // Save plugin settings
    $('.plugin-settings-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const pluginId = form.data('plugin-id');
        const submitBtn = form.find('button[type="submit"]');
        
        // Collect settings
        const settings = {};
        form.find('.plugin-setting').each(function() {
            const input = $(this);
            const key = input.data('setting-key');
            let value;
            
            if (input.attr('type') === 'checkbox') {
                value = input.is(':checked') ? '1' : '0';
                settings[key] = value;
            } else if (input.is('select[multiple]')) {
                const ts = input[0].tomselect;
                const vals = ts ? Object.keys(ts.items) : input.val();
                settings[key] = Array.isArray(vals) ? vals.join(',') : (vals || '');
            } else if (input.hasClass('plugin-checkboxes')) {
                const groupKey = input.data('setting-key');
                const checked = form.find('.plugin-checkbox-item[data-group-key="' + groupKey + '"]:checked')
                    .map(function() { return this.value; }).get();
                settings[groupKey] = checked.join(',');
            } else if (input.attr('type') === 'password') {
                // For password fields, only include if user entered a new value
                // Skip empty passwords when there's an existing value (preserve current)
                value = input.val();
                const hasExistingValue = input.data('has-value') === 1 || input.data('has-value') === '1';
                if (value !== '' || !hasExistingValue) {
                    settings[key] = value;
                }
                // If empty and hasExistingValue, don't include in settings (keep existing)
            } else {
                value = input.val();
                settings[key] = value;
            }
        });
        
        submitBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i>' + i18next.t('Saving...'));
        
        $.ajax({
            url: window.CRM.root + '/plugins/api/plugins/' + pluginId + '/settings',
            method: 'POST',
            dataType: 'json',
            contentType: 'application/json',
            data: JSON.stringify({ settings: settings })
        })
        .done(function(response) {
            if (response.success) {
                window.CRM.notify(i18next.t('Settings saved'), { type: 'success' });
            } else {
                window.CRM.notify(response.message || i18next.t('Failed to save settings'), { type: 'error' });
            }
        })
        .fail(function(xhr) {
            const error = xhr.responseJSON?.message || i18next.t('Failed to save settings');
            window.CRM.notify(error, { type: 'error' });
        })
        .always(function() {
            submitBtn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk me-1"></i>' + i18next.t('Save Settings'));
        });
    });

    // Collect current form settings for a plugin
    function collectSettings(form) {
        const settings = {};
        form.find('.plugin-setting').each(function() {
            const input = $(this);
            const key   = input.data('setting-key');
            if (input.attr('type') === 'checkbox') {
                settings[key] = input.is(':checked') ? '1' : '0';
            } else if (input.is('select[multiple]')) {
                const ts = input[0].tomselect;
                const vals = ts ? Object.keys(ts.items) : input.val();
                settings[key] = Array.isArray(vals) ? vals.join(',') : (vals || '');
            } else if (input.hasClass('plugin-checkboxes')) {
                const groupKey = input.data('setting-key');
                const checked = form.find('.plugin-checkbox-item[data-group-key="' + groupKey + '"]:checked')
                    .map(function() { return this.value; }).get();
                settings[groupKey] = checked.join(',');
            } else if (input.attr('type') === 'password') {
                const val = input.val();
                // Only include if the admin actually typed a new value
                if (val !== '') {
                    settings[key] = val;
                }
                // If empty, omit — the plugin will fall back to the saved secret
            } else {
                settings[key] = input.val();
            }
        });
        return settings;
    }

    // Show inline test result below the button group
    function showTestResult(pluginId, success, message) {
        const resultDiv = $('#test-result-' + pluginId);
        const alertClass = success ? 'alert-success' : 'alert-danger';
        const icon = success ? 'fa-circle-check' : 'fa-circle-exclamation';
        resultDiv
            .removeClass('alert-success alert-danger')
            .addClass('alert ' + alertClass)
            .html('<i class="fa-solid ' + icon + ' me-1"></i>' + escapeHtml(message))
            .show();
    }

    // Test Connection button
    $('.btn-test-settings').on('click', function(e) {
        e.preventDefault();
        const btn      = $(this);
        const pluginId = btn.data('plugin-id');
        const form     = btn.closest('form');
        const settings = collectSettings(form);

        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i>' + i18next.t('Testing...'));

        $.ajax({
            url:         window.CRM.root + '/plugins/api/plugins/' + pluginId + '/test',
            method:      'POST',
            dataType:    'json',
            contentType: 'application/json',
            data:        JSON.stringify({ settings: settings }),
        })
        .done(function(response) {
            showTestResult(pluginId, response.success, response.message);
        })
        .fail(function(xhr) {
            const msg = xhr.responseJSON?.message || i18next.t('Connection test failed');
            showTestResult(pluginId, false, msg);
        })
        .always(function() {
            btn.prop('disabled', false).html('<i class="fa-solid fa-plug me-1"></i>' + i18next.t('Test Connection'));
        });
    });

    // Reset plugin settings
    $('.btn-reset-settings').on('click', function(e) {
        e.preventDefault();
        const btn = $(this);
        const pluginId = btn.data('plugin-id');
        const form = btn.closest('form');
        
        // Confirm reset
        if (!confirm(i18next.t('Are you sure you want to reset all settings for this plugin? This will clear all configured values.'))) {
            return;
        }
        
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i>' + i18next.t('Resetting...'));
        
        $.ajax({
            url: window.CRM.root + '/plugins/api/plugins/' + pluginId + '/reset',
            method: 'POST',
            dataType: 'json',
            contentType: 'application/json'
        })
        .done(function(response) {
            if (response.success) {
                window.CRM.notify(i18next.t('Settings reset'), { type: 'success' });
                // Clear form fields
                form.find('.plugin-setting').each(function() {
                    const input = $(this);
                    if (input.attr('type') === 'checkbox') {
                        input.prop('checked', false);
                    } else {
                        input.val('');
                    }
                });
            } else {
                window.CRM.notify(response.message || i18next.t('Failed to reset settings'), { type: 'error' });
            }
        })
        .fail(function(xhr) {
            const error = xhr.responseJSON?.message || i18next.t('Failed to reset settings');
            window.CRM.notify(error, { type: 'error' });
        })
        .always(function() {
            btn.prop('disabled', false).html('<i class="fa-solid fa-undo me-1"></i>' + i18next.t('Reset'));
        });
    });
    
    // Auto-expand plugin card if URL has hash (e.g., #plugin-mailchimp)
    if (window.location.hash && window.location.hash.startsWith('#plugin-')) {
        const pluginId = window.location.hash.replace('#plugin-', '');
        const card = $('[data-plugin-id="' + pluginId + '"]');
        if (card.length) {
            const body = card.find('.card-body');
            // Expand the card if not already expanded
            if (body.is(':hidden')) {
                body.show();
                card.find('.btn-expand-plugin i')
                    .removeClass('fa-chevron-down').addClass('fa-chevron-up');
            }
            // Scroll to the card
            setTimeout(function() {
                $('html, body').animate({
                    scrollTop: card.offset().top - 100
                }, 500);
            }, 100);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Uninstall (delete community plugin from disk)
    // ─────────────────────────────────────────────────────────────
    $(document).on('click', '.btn-plugin-uninstall', function(e) {
        e.stopPropagation();
        const pluginId = $(this).data('plugin-id');
        const pluginName = $(this).data('plugin-name');
        if (!pluginId) return;

        const confirmed = window.confirm(
            "<?= addslashes(gettext('This will permanently delete the plugin files from disk and clear its settings. This cannot be undone. Continue?')) ?>\n\n" + pluginName
        );
        if (!confirmed) return;

        $.ajax({
            url: '<?= $sRootPath ?>/plugins/api/plugins/' + encodeURIComponent(pluginId),
            method: 'DELETE',
            success: function() {
                window.CRM && window.CRM.notify
                    ? window.CRM.notify(<?= json_encode(gettext('Plugin uninstalled')) ?>, 'success')
                    : alert(<?= json_encode(gettext('Plugin uninstalled')) ?>);
                setTimeout(function() { window.location.reload(); }, 400);
            },
            error: function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : <?= json_encode(gettext('Failed to uninstall plugin')) ?>;
                alert(msg);
            }
        });
    });

    // ─────────────────────────────────────────────────────────────
    //  Clear Quarantine
    // ─────────────────────────────────────────────────────────────
    $(document).on('click', '.btn-plugin-clear-quarantine', function(e) {
        e.stopPropagation();
        const pluginId = $(this).data('plugin-id');
        if (!pluginId) return;

        const confirmed = window.confirm(
            <?= json_encode(gettext('Clear quarantine for this plugin? Only do this after the underlying issue has been fixed. You will still need to click Enable before the plugin runs again.')) ?>
        );
        if (!confirmed) return;

        $.ajax({
            url: '<?= $sRootPath ?>/plugins/api/plugins/' + encodeURIComponent(pluginId) + '/quarantine',
            method: 'DELETE',
            success: function() { setTimeout(function() { window.location.reload(); }, 200); },
            error: function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : <?= json_encode(gettext('Failed to clear quarantine')) ?>;
                alert(msg);
            }
        });
    });

    // ─────────────────────────────────────────────────────────────
    //  Browse Approved Plugins
    // ─────────────────────────────────────────────────────────────
    $('#btn-browse-approved').on('click', function() {
        const $list = $('#approvedPluginsList');
        $list.html('<div class="text-center text-muted py-4"><i class="fa-solid fa-spinner fa-spin me-2"></i><?= addslashes(gettext('Loading…')) ?></div>');
        $('#approvedPluginsModal').modal('show');

        $.ajax({
            url: '<?= $sRootPath ?>/plugins/api/approved',
            method: 'GET',
            dataType: 'json',
            success: function(resp) {
                if (!resp.success || !Array.isArray(resp.data) || resp.data.length === 0) {
                    $list.html('<div class="alert alert-info"><?= addslashes(gettext('The approved plugin list is empty. There are no third-party plugins available through the verified installer yet.')) ?></div>');
                    return;
                }
                const html = resp.data.map(function(entry) {
                    const risk = (entry.risk || 'low').toLowerCase();
                    const riskClass = risk === 'high' ? 'bg-red-lt text-red'
                                   : risk === 'medium' ? 'bg-yellow-lt text-yellow'
                                   : 'bg-green-lt text-green';
                    const perms = entry.permissions || [];
                    const permsHtml = perms.length
                        ? '<div class="mt-2">' + perms.map(function(p) {
                              return '<span class="badge bg-secondary-lt text-secondary me-1">' + $('<div>').text(p).html() + '</span>';
                          }).join('') + '</div>'
                        : '';
                    const author = (entry.author || '').trim();
                    const homepageUrl = (entry.homepage || '').trim();
                    const homepageLink = homepageUrl
                        ? '<a href="' + $('<div>').text(homepageUrl).html() + '" target="_blank" rel="noopener"><?= addslashes(gettext('Homepage')) ?></a>'
                        : '';
                    const metaParts = [author ? $('<div>').text(author).html() : '', homepageLink].filter(Boolean);
                    const metaHtml = metaParts.length
                        ? '<div class="mt-1 small text-muted">' + metaParts.join(' · ') + '</div>'
                        : '';
                    const notesHtml = entry.notes
                        ? '<div class="mt-2 small text-muted fst-italic">' + $('<div>').text(entry.notes).html() + '</div>'
                        : '';
                    return '' +
                        '<div class="card">' +
                        '  <div class="card-body">' +
                        '    <div class="d-flex align-items-start gap-3">' +
                        '      <div class="flex-grow-1">' +
                        '        <div class="d-flex align-items-center flex-wrap gap-1 mb-1">' +
                        '          <strong>' + $('<div>').text(entry.name || entry.id).html() + '</strong>' +
                        '          <span class="badge bg-azure-lt text-azure">v' + $('<div>').text(entry.version || '').html() + '</span>' +
                        '          <span class="badge ' + riskClass + '">' + $('<div>').text(risk).html() + ' <?= addslashes(gettext('risk')) ?></span>' +
                        '        </div>' +
                        '        <div>' + $('<div>').text(entry.riskSummary || '').html() + '</div>' +
                        permsHtml +
                        metaHtml +
                        notesHtml +
                        '      </div>' +
                        '      <div class="flex-shrink-0">' +
                        '        <button type="button" class="btn btn-primary btn-sm btn-install-approved"' +
                        '                data-download-url="' + $('<div>').text(entry.downloadUrl || '').html() + '">' +
                        '          <i class="fa-solid fa-download me-1"></i><?= addslashes(gettext('Install')) ?>' +
                        '        </button>' +
                        '      </div>' +
                        '    </div>' +
                        '  </div>' +
                        '</div>';
                }).join('');
                $list.html(html);
            },
            error: function() {
                $list.html('<div class="alert alert-danger"><?= addslashes(gettext('Failed to load approved plugin list.')) ?></div>');
            }
        });
    });

    $(document).on('click', '.btn-install-approved', function() {
        const $btn = $(this);
        const downloadUrl = $btn.data('download-url');
        if (!downloadUrl) return;
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');

        $.ajax({
            url: '<?= $sRootPath ?>/plugins/api/plugins/install',
            method: 'POST',
            data: { downloadUrl: downloadUrl },
            success: function() {
                $('#approvedPluginsModal').modal('hide');
                setTimeout(function() { window.location.reload(); }, 300);
            },
            error: function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : "<?= addslashes(gettext('Install failed')) ?>";
                alert(msg);
                $btn.prop('disabled', false).html('<i class="fa-solid fa-download me-1"></i><?= addslashes(gettext('Install')) ?>');
            }
        });
    });

    // ─────────────────────────────────────────────────────────────
    //  Install from URL (unverified)
    // ─────────────────────────────────────────────────────────────
    $('#btn-install-from-url').on('click', function() {
        $('#installFromUrlForm')[0].reset();
        $('#installFromUrlResult').empty();
        $('#installFromUrlModal').modal('show');
    });

    $('#btn-submit-install-url').on('click', function() {
        const url = $('#install-url-input').val().trim();
        const sha = $('#install-sha256-input').val().trim();
        const pid = $('#install-plugin-id-input').val().trim();
        if (!url || !sha || !pid) {
            $('#installFromUrlResult').html('<div class="alert alert-danger"><?= addslashes(gettext('All three fields are required.')) ?></div>');
            return;
        }
        if (!/^https:\/\//i.test(url)) {
            $('#installFromUrlResult').html('<div class="alert alert-danger"><?= addslashes(gettext('URL must use HTTPS.')) ?></div>');
            return;
        }
        if (!/^[a-fA-F0-9]{64}$/.test(sha)) {
            $('#installFromUrlResult').html('<div class="alert alert-danger"><?= addslashes(gettext('SHA-256 must be a 64-character hex string.')) ?></div>');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i><?= addslashes(gettext('Installing…')) ?>');

        $.ajax({
            url: '<?= $sRootPath ?>/plugins/api/plugins/install-url',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ downloadUrl: url, sha256: sha, pluginId: pid }),
            success: function() {
                $('#installFromUrlModal').modal('hide');
                setTimeout(function() { window.location.reload(); }, 300);
            },
            error: function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : "<?= addslashes(gettext('Install failed')) ?>";
                $('#installFromUrlResult').html('<div class="alert alert-danger">' + $('<div>').text(msg).html() + '</div>');
                $btn.prop('disabled', false).html('<i class="fa-solid fa-download me-1"></i><?= addslashes(gettext('Install Unverified')) ?>');
            }
        });
    });
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
