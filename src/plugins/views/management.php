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
?>
    <div class="card <?= $hasError ? 'card-danger' : ($isActive ? 'card-success' : 'card-secondary') ?> card-outline collapsed-card" 
         data-plugin-id="<?= $pluginId ?>"
         <?php if ($hasHelp): ?>data-plugin-help="<?= $helpData ?>"<?php endif; ?>>
        <div class="card-header">
            <h3 class="card-title">
                <?php if ($hasError): ?>
                    <i class="fas fa-exclamation-triangle text-danger mr-2"></i>
                <?php elseif ($isActive): ?>
                    <i class="fas fa-check-circle text-success mr-2"></i>
                <?php else: ?>
                    <i class="fas fa-times-circle text-secondary mr-2"></i>
                <?php endif; ?>
                <strong><?= htmlspecialchars($plugin['name']) ?></strong>
                <span class="badge badge-info ml-2">v<?= htmlspecialchars($plugin['version']) ?></span>
                <?php if ($hasError): ?>
                    <span class="badge badge-danger ml-2"><?= gettext('Error') ?></span>
                <?php elseif ($isActive): ?>
                    <span class="badge badge-success ml-2"><?= gettext('Enabled') ?></span>
                    <?php if (!$plugin['isConfigured']): ?>
                        <span class="badge badge-warning ml-2"><?= gettext('Needs Configuration') ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge badge-secondary ml-2"><?= gettext('Disabled') ?></span>
                <?php endif; ?>
            </h3>
            <div class="card-tools">
                <?php if ($hasHelp): ?>
                    <button type="button" class="btn btn-tool btn-plugin-help text-info" 
                            data-plugin-id="<?= $pluginId ?>"
                            data-plugin-name="<?= htmlspecialchars($plugin['name']) ?>"
                            title="<?= gettext('Help') ?>">
                        <i class="fas fa-question-circle"></i>
                    </button>
                <?php endif; ?>
                <?php if (!$hasError): ?>
                    <?php if ($isActive): ?>
                        <button type="button" class="btn btn-tool btn-plugin-toggle text-danger" 
                                data-action="disable" data-plugin-id="<?= $pluginId ?>"
                                title="<?= gettext('Disable Plugin') ?>">
                            <i class="fas fa-power-off"></i> <?= gettext('Disable') ?>
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-tool btn-plugin-toggle text-success" 
                                data-action="enable" data-plugin-id="<?= $pluginId ?>"
                                title="<?= gettext('Enable Plugin') ?>">
                            <i class="fas fa-play"></i> <?= gettext('Enable') ?>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
        <div class="card-body" style="display: none;">
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
                    <i class="fas fa-exclamation-triangle mr-2"></i>
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
                                    <div class="input-group-append">
                                        <span class="input-group-text text-success" title="<?= gettext('Value is configured') ?>">
                                            <i class="fas fa-check"></i>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($settingType === 'select' && !empty($setting['options'])): ?>
                                <select class="form-control plugin-setting"
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
                            <i class="fas fa-save mr-1"></i><?= gettext('Save Settings') ?>
                        </button>
                        <?php if (!empty($plugin['hasTest'])): ?>
                        <button type="button" class="btn btn-outline-info btn-sm btn-test-settings"
                                data-plugin-id="<?= $pluginId ?>">
                            <i class="fas fa-plug mr-1"></i><?= gettext('Test Connection') ?>
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-danger btn-sm btn-reset-settings" data-plugin-id="<?= $pluginId ?>">
                            <i class="fas fa-undo mr-1"></i><?= gettext('Reset') ?>
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
                            <code>plugins/community/</code>
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

<!-- Plugin Help Modal -->
<div class="modal fade" id="pluginHelpModal" tabindex="-1" role="dialog" aria-labelledby="pluginHelpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title" id="pluginHelpModalLabel">
                    <i class="fas fa-question-circle mr-2"></i>
                    <span id="pluginHelpTitle"><?= gettext('Plugin Help') ?></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="<?= gettext('Close') ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="pluginHelpContent">
                <!-- Help content will be injected here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <?= gettext('Close') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
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
                contentHtml += '<div class="card card-outline card-secondary mb-3">';
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
                contentHtml += escapeHtml(i18next.t(link.label)) + ' <i class="fas fa-external-link-alt fa-xs"></i></a></li>';
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

    // Collect current form settings for a plugin
    function collectSettings(form) {
        const settings = {};
        form.find('.plugin-setting').each(function() {
            const input = $(this);
            const key   = input.data('setting-key');
            if (input.attr('type') === 'checkbox') {
                settings[key] = input.is(':checked') ? '1' : '0';
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
        const icon = success ? 'fa-check-circle' : 'fa-exclamation-circle';
        resultDiv
            .removeClass('alert-success alert-danger')
            .addClass('alert ' + alertClass)
            .html('<i class="fas ' + icon + ' mr-1"></i>' + escapeHtml(message))
            .show();
    }

    // Test Connection button
    $('.btn-test-settings').on('click', function(e) {
        e.preventDefault();
        const btn      = $(this);
        const pluginId = btn.data('plugin-id');
        const form     = btn.closest('form');
        const settings = collectSettings(form);

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>' + i18next.t('Testing...'));

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
            btn.prop('disabled', false).html('<i class="fas fa-plug mr-1"></i>' + i18next.t('Test Connection'));
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
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>' + i18next.t('Resetting...'));
        
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
            btn.prop('disabled', false).html('<i class="fas fa-undo mr-1"></i>' + i18next.t('Reset'));
        });
    });
    
    // Auto-expand plugin card if URL has hash (e.g., #plugin-mailchimp)
    if (window.location.hash && window.location.hash.startsWith('#plugin-')) {
        const pluginId = window.location.hash.replace('#plugin-', '');
        const card = $('[data-plugin-id="' + pluginId + '"]');
        if (card.length) {
            card.removeClass('collapsed-card');
            card.find('.card-body').show();
            card.find('.card-tools .btn-tool[data-card-widget="collapse"] i')
                .removeClass('fa-plus').addClass('fa-minus');
            // Scroll to the card
            $('html, body').animate({
                scrollTop: card.offset().top - 100
            }, 500);
        }
    }
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
