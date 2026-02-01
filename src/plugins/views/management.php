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
?>
    <div class="card <?= $hasError ? 'card-danger' : ($plugin['isActive'] ? 'card-success' : 'card-secondary') ?> card-outline collapsed-card" 
         data-plugin-id="<?= $pluginId ?>">
        <div class="card-header">
            <h3 class="card-title">
                <?php if ($hasError): ?>
                    <i class="fas fa-exclamation-triangle text-danger mr-2"></i>
                <?php elseif ($plugin['isActive']): ?>
                    <i class="fas fa-check-circle text-success mr-2"></i>
                <?php else: ?>
                    <i class="fas fa-circle text-secondary mr-2"></i>
                <?php endif; ?>
                <strong><?= htmlspecialchars($plugin['name']) ?></strong>
                <span class="badge badge-info ml-2"><?= htmlspecialchars($plugin['version']) ?></span>
                <?php if ($plugin['isActive'] && !$plugin['isConfigured']): ?>
                    <span class="badge badge-warning ml-2"><?= gettext('Needs Configuration') ?></span>
                <?php endif; ?>
            </h3>
            <div class="card-tools">
                <?php if ($plugin['isActive']): ?>
                    <button type="button" class="btn btn-tool btn-plugin-toggle text-danger" 
                            data-action="disable" data-plugin-id="<?= $pluginId ?>"
                            title="<?= gettext('Disable') ?>">
                        <i class="fas fa-power-off"></i>
                    </button>
                <?php else: ?>
                    <button type="button" class="btn btn-tool btn-plugin-toggle text-success" 
                            data-action="enable" data-plugin-id="<?= $pluginId ?>"
                            title="<?= gettext('Enable') ?>">
                        <i class="fas fa-play"></i>
                    </button>
                <?php endif; ?>
                <?php if ($hasSettings): ?>
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-plus"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body" <?= $hasSettings ? 'style="display: none;"' : '' ?>>
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
                <div class="alert alert-danger mt-2 mb-0">
                    <i class="fas fa-bug mr-2"></i>
                    <?= htmlspecialchars($plugin['errorMessage'] ?? gettext('Plugin failed to load')) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($hasSettings && !$hasError): ?>
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
                        <div class="form-group">
                            <label for="<?= $pluginId ?>-<?= $settingKey ?>">
                                <?= $settingLabel ?>
                                <?php if ($isRequired): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            <?php if ($settingType === 'boolean'): ?>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" 
                                           class="custom-control-input plugin-setting" 
                                           id="<?= $pluginId ?>-<?= $settingKey ?>"
                                           data-setting-key="<?= $settingKey ?>"
                                           data-config-key="<?= $configKey ?>"
                                           <?= ($settingValue === '1' || $settingValue === 'true') ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="<?= $pluginId ?>-<?= $settingKey ?>">
                                        <?= gettext('Enabled') ?>
                                    </label>
                                </div>
                            <?php elseif ($settingType === 'password'): ?>
                                <input type="password" 
                                       class="form-control plugin-setting" 
                                       id="<?= $pluginId ?>-<?= $settingKey ?>"
                                       data-setting-key="<?= $settingKey ?>"
                                       data-config-key="<?= $configKey ?>"
                                       value="<?= $settingValue ?>"
                                       <?= $isRequired ? 'required' : '' ?>>
                            <?php elseif ($settingType === 'select' && !empty($setting['options'])): ?>
                                <select class="form-control plugin-setting"
                                        id="<?= $pluginId ?>-<?= $settingKey ?>"
                                        data-setting-key="<?= $settingKey ?>"
                                        data-config-key="<?= $configKey ?>"
                                        <?= $isRequired ? 'required' : '' ?>>
                                    <?php foreach ($setting['options'] as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" 
                                                <?= $settingValue === $option ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($option) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" 
                                       class="form-control plugin-setting" 
                                       id="<?= $pluginId ?>-<?= $settingKey ?>"
                                       data-setting-key="<?= $settingKey ?>"
                                       data-config-key="<?= $configKey ?>"
                                       value="<?= $settingValue ?>"
                                       <?= $isRequired ? 'required' : '' ?>>
                            <?php endif; ?>
                            <?php if ($settingHelp): ?>
                                <small class="form-text text-muted"><?= htmlspecialchars($settingHelp) ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save mr-1"></i><?= gettext('Save Settings') ?>
                    </button>
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
                    <i class="fas fa-plug mr-2"></i><?= gettext('Core Plugins') ?>
                </h3>
            </div>
            <div class="card-body">
                <?php if (empty($corePlugins)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-info-circle fa-2x mb-2"></i>
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
            <div class="card-header border-0">
                <h3 class="card-title">
                    <i class="fas fa-puzzle-piece mr-2"></i><?= gettext('Community Plugins') ?>
                </h3>
            </div>
            <div class="card-body">
                <?php if (empty($communityPlugins)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-folder-open fa-2x mb-2"></i>
                        <p><?= gettext('No community plugins installed') ?></p>
                        <p class="small">
                            <?= gettext('Install plugins by placing them in') ?>
                            <code>src/plugins/community/</code>
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
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle mr-2"></i><?= gettext('Plugin Development') ?>
                </h3>
            </div>
            <div class="card-body">
                <p><?= gettext('ChurchCRM supports a plugin architecture for extending functionality.') ?></p>
                <ul class="pl-3">
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

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
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
            } else {
                value = input.val();
            }
            
            settings[key] = value;
        });
        
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>' + i18next.t('Saving...'));
        
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
            submitBtn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>' + i18next.t('Save Settings'));
        });
    });
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
