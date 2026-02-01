<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

/**
 * Plugin Management Admin Page.
 *
 * Displays all installed plugins with options to enable/disable them.
 *
 * Variables available:
 * - $corePlugins: array of core plugin data
 * - $communityPlugins: array of community plugin data
 */
?>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header border-0">
                <h3 class="card-title">
                    <i class="fas fa-plug mr-2"></i><?= gettext('Core Plugins') ?>
                </h3>
            </div>
            <div class="card-body p-0">
                <?php if (empty($corePlugins)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                        <p><?= gettext('No core plugins found') ?></p>
                    </div>
                <?php else: ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th style="width: 50px"><?= gettext('Status') ?></th>
                                <th><?= gettext('Plugin') ?></th>
                                <th><?= gettext('Version') ?></th>
                                <th><?= gettext('Configuration') ?></th>
                                <th style="width: 150px"><?= gettext('Actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($corePlugins as $plugin): ?>
                                <tr data-plugin-id="<?= htmlspecialchars($plugin['id']) ?>" class="<?= !empty($plugin['hasError']) ? 'table-danger' : '' ?>">
                                    <td class="text-center">
                                        <?php if (!empty($plugin['hasError'])): ?>
                                            <span class="badge badge-danger" title="<?= gettext('Error') ?>">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </span>
                                        <?php elseif ($plugin['isActive']): ?>
                                            <span class="badge badge-success" title="<?= gettext('Active') ?>">
                                                <i class="fas fa-check"></i>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary" title="<?= gettext('Inactive') ?>">
                                                <i class="fas fa-times"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($plugin['name']) ?></strong>
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
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($plugin['description']) ?></small>
                                        <?php if (!empty($plugin['hasError'])): ?>
                                            <br><small class="text-danger"><i class="fas fa-bug"></i> <?= htmlspecialchars($plugin['errorMessage'] ?? gettext('Plugin failed to load')) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?= htmlspecialchars($plugin['version']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($plugin['isConfigured']): ?>
                                            <span class="text-success">
                                                <i class="fas fa-check-circle"></i> <?= gettext('Configured') ?>
                                            </span>
                                        <?php elseif ($plugin['isActive']): ?>
                                            <span class="text-warning">
                                                <i class="fas fa-exclamation-circle"></i> <?= gettext('Needs Configuration') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($plugin['isActive']): ?>
                                                <button type="button" 
                                                        class="btn btn-outline-danger btn-plugin-toggle"
                                                        data-action="disable"
                                                        data-plugin-id="<?= htmlspecialchars($plugin['id']) ?>"
                                                        title="<?= gettext('Disable') ?>">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button"
                                                        class="btn btn-outline-success btn-plugin-toggle"
                                                        data-action="enable"
                                                        data-plugin-id="<?= htmlspecialchars($plugin['id']) ?>"
                                                        title="<?= gettext('Enable') ?>">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($plugin['settingsUrl']): ?>
                                                <a href="<?= SystemURLs::getRootPath() . htmlspecialchars($plugin['settingsUrl']) ?>"
                                                   class="btn btn-outline-primary"
                                                   title="<?= gettext('Settings') ?>">
                                                    <i class="fas fa-cog"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header border-0">
                <h3 class="card-title">
                    <i class="fas fa-puzzle-piece mr-2"></i><?= gettext('Community Plugins') ?>
                </h3>
            </div>
            <div class="card-body p-0">
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
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th style="width: 50px"><?= gettext('Status') ?></th>
                                <th><?= gettext('Plugin') ?></th>
                                <th><?= gettext('Version') ?></th>
                                <th><?= gettext('Configuration') ?></th>
                                <th style="width: 150px"><?= gettext('Actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($communityPlugins as $plugin): ?>
                                <tr data-plugin-id="<?= htmlspecialchars($plugin['id']) ?>" class="<?= !empty($plugin['hasError']) ? 'table-danger' : '' ?>">
                                    <td class="text-center">
                                        <?php if (!empty($plugin['hasError'])): ?>
                                            <span class="badge badge-danger" title="<?= gettext('Error') ?>">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </span>
                                        <?php elseif ($plugin['isActive']): ?>
                                            <span class="badge badge-success" title="<?= gettext('Active') ?>">
                                                <i class="fas fa-check"></i>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary" title="<?= gettext('Inactive') ?>">
                                                <i class="fas fa-times"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($plugin['name']) ?></strong>
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
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($plugin['description']) ?></small>
                                        <?php if (!empty($plugin['hasError'])): ?>
                                            <br><small class="text-danger"><i class="fas fa-bug"></i> <?= htmlspecialchars($plugin['errorMessage'] ?? gettext('Plugin failed to load')) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?= htmlspecialchars($plugin['version']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($plugin['isConfigured']): ?>
                                            <span class="text-success">
                                                <i class="fas fa-check-circle"></i> <?= gettext('Configured') ?>
                                            </span>
                                        <?php elseif ($plugin['isActive']): ?>
                                            <span class="text-warning">
                                                <i class="fas fa-exclamation-circle"></i> <?= gettext('Needs Configuration') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($plugin['isActive']): ?>
                                                <button type="button"
                                                        class="btn btn-outline-danger btn-plugin-toggle"
                                                        data-action="disable"
                                                        data-plugin-id="<?= htmlspecialchars($plugin['id']) ?>"
                                                        title="<?= gettext('Disable') ?>">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button"
                                                        class="btn btn-outline-success btn-plugin-toggle"
                                                        data-action="enable"
                                                        data-plugin-id="<?= htmlspecialchars($plugin['id']) ?>"
                                                        title="<?= gettext('Enable') ?>">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($plugin['settingsUrl']): ?>
                                                <a href="<?= SystemURLs::getRootPath() . htmlspecialchars($plugin['settingsUrl']) ?>"
                                                   class="btn btn-outline-primary"
                                                   title="<?= gettext('Settings') ?>">
                                                    <i class="fas fa-cog"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle mr-2"></i><?= gettext('Plugin Development') ?>
                </h3>
            </div>
            <div class="card-body">
                <p><?= gettext('ChurchCRM supports a plugin architecture for extending functionality.') ?></p>
                <ul>
                    <li><strong><?= gettext('Core plugins') ?>:</strong> <?= gettext('Shipped with ChurchCRM and maintained by the core team.') ?></li>
                    <li><strong><?= gettext('Community plugins') ?>:</strong> <?= gettext('Third-party extensions. Install by copying to') ?> <code>src/plugins/community/</code></li>
                </ul>
                <p><?= gettext('Each plugin requires a') ?> <code>plugin.json</code> <?= gettext('manifest file and a main PHP class implementing') ?> <code>PluginInterface</code>.</p>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    $('.btn-plugin-toggle').on('click', function() {
        const btn = $(this);
        const pluginId = btn.data('plugin-id');
        const action = btn.data('action');
        const row = btn.closest('tr');
        
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
                // Reload page to show updated state
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
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
