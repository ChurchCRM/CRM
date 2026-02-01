<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

/**
 * Individual Plugin Settings Page.
 *
 * Displays settings form for a specific plugin.
 *
 * Variables available:
 * - $plugin: array plugin metadata
 * - $isActive: bool whether plugin is active
 * - $isConfigured: bool whether plugin is configured
 * - $settingsSchema: array settings fields schema
 */
?>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header border-0">
                <h3 class="card-title">
                    <i class="fas fa-plug mr-2"></i><?= htmlspecialchars($plugin['name']) ?>
                </h3>
                <div class="card-tools">
                    <a href="<?= SystemURLs::getRootPath() ?>/plugins" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left mr-1"></i><?= gettext('Back to Plugins') ?>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <p class="text-muted"><?= htmlspecialchars($plugin['description']) ?></p>
                    </div>
                    <div class="col-md-4 text-right">
                        <span class="badge badge-info mr-2">v<?= htmlspecialchars($plugin['version']) ?></span>
                        <?php if ($isActive): ?>
                            <span class="badge badge-success"><?= gettext('Active') ?></span>
                        <?php else: ?>
                            <span class="badge badge-secondary"><?= gettext('Inactive') ?></span>
                        <?php endif; ?>
                        <?php if ($isConfigured): ?>
                            <span class="badge badge-success ml-2">
                                <i class="fas fa-check"></i> <?= gettext('Configured') ?>
                            </span>
                        <?php else: ?>
                            <span class="badge badge-warning ml-2">
                                <i class="fas fa-exclamation"></i> <?= gettext('Needs Configuration') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($settingsSchema)): ?>
                    <div class="callout callout-info">
                        <h5><i class="fas fa-info-circle mr-2"></i><?= gettext('Configuration') ?></h5>
                        <p><?= gettext('Plugin settings are managed through System Settings. The settings below show the current configuration status.') ?></p>
                        <a href="<?= SystemURLs::getRootPath() ?>/SystemSettings.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-cog mr-1"></i><?= gettext('Open System Settings') ?>
                        </a>
                    </div>

                    <h5 class="mt-4 mb-3"><?= gettext('Plugin Settings') ?></h5>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th style="width: 30%"><?= gettext('Setting') ?></th>
                                <th style="width: 50%"><?= gettext('Description') ?></th>
                                <th style="width: 20%"><?= gettext('Status') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($settingsSchema as $setting): ?>
                                <?php 
                                    $configValue = SystemConfig::getValue($setting['key']);
                                    $hasValue = !empty($configValue) && $configValue !== '0';
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($setting['label']) ?></strong>
                                        <br>
                                        <small class="text-muted"><code><?= htmlspecialchars($setting['key']) ?></code></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($setting['help'])): ?>
                                            <?= htmlspecialchars($setting['help']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($hasValue): ?>
                                            <span class="text-success">
                                                <i class="fas fa-check-circle fa-lg"></i>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-danger">
                                                <i class="fas fa-times-circle fa-lg"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="callout callout-success">
                        <h5><i class="fas fa-check-circle mr-2"></i><?= gettext('No Configuration Required') ?></h5>
                        <p><?= gettext('This plugin does not require any configuration.') ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($plugin['author'])): ?>
<div class="row">
    <div class="col-lg-12">
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle mr-2"></i><?= gettext('Plugin Information') ?>
                </h3>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3"><?= gettext('Author') ?></dt>
                    <dd class="col-sm-9">
                        <?php if (!empty($plugin['authorUrl'])): ?>
                            <a href="<?= htmlspecialchars($plugin['authorUrl']) ?>" target="_blank">
                                <?= htmlspecialchars($plugin['author']) ?>
                                <i class="fas fa-external-link-alt fa-xs ml-1"></i>
                            </a>
                        <?php else: ?>
                            <?= htmlspecialchars($plugin['author']) ?>
                        <?php endif; ?>
                    </dd>
                    
                    <dt class="col-sm-3"><?= gettext('Type') ?></dt>
                    <dd class="col-sm-9">
                        <?php if ($plugin['type'] === 'core'): ?>
                            <span class="badge badge-primary"><?= gettext('Core Plugin') ?></span>
                        <?php else: ?>
                            <span class="badge badge-secondary"><?= gettext('Community Plugin') ?></span>
                        <?php endif; ?>
                    </dd>
                    
                    <dt class="col-sm-3"><?= gettext('Minimum CRM Version') ?></dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($plugin['minimumCRMVersion'] ?? '5.0.0') ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
