<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>
<!-- Breadcrumb Navigation with Actions -->
<div class="row mb-3">
    <div class="col-12 d-flex align-items-center">
        <nav aria-label="breadcrumb" class="flex-grow-1">
            <ol class="breadcrumb mb-0 bg-light">
                <li class="breadcrumb-item"><a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard"><i class="fa-solid fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="<?= SystemURLs::getRootPath() ?>/plugins"><?= gettext('Plugins') ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= gettext('MailChimp') ?></li>
            </ol>
        </nav>
        <div class="btn-group btn-group-sm ml-2">
            <a href="https://login.mailchimp.com/" target="_blank" class="btn btn-outline-warning" title="<?= gettext('Open MailChimp') ?>">
                <i class="fa-brands fa-mailchimp fa-fw"></i> <?= gettext('Open MailChimp') ?>
            </a>
            <a href="<?= SystemURLs::getRootPath() ?>/plugins/management/mailchimp" class="btn btn-outline-secondary" title="<?= gettext('Plugin Settings') ?>">
                <i class="fa-solid fa-cog fa-fw"></i>
            </a>
        </div>
    </div>
</div>

<?php if ($isMailChimpActive && !empty($mailChimpLists)) : ?>
    <?php
    // Calculate totals
    $totalMembers = 0;
    $totalCampaigns = 0;
    $latestCampaignDate = null;
    foreach ($mailChimpLists as $list) {
        $totalMembers += $list['stats']['member_count'] ?? 0;
        $totalCampaigns += $list['stats']['campaign_count'] ?? 0;
        $lastSent = $list['stats']['campaign_last_sent'] ?? null;
        if ($lastSent && ($latestCampaignDate === null || $lastSent > $latestCampaignDate)) {
            $latestCampaignDate = $lastSent;
        }
    }
    ?>

    <!-- Account Info Banner -->
    <?php if (!empty($accountInfo['account_name'])) : ?>
    <div class="alert alert-light border mb-3">
        <div class="d-flex align-items-center">
            <i class="fa-brands fa-mailchimp fa-2x text-warning mr-3"></i>
            <div class="flex-grow-1">
                <strong><?= htmlspecialchars($accountInfo['account_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                <?php if (!empty($accountInfo['email'])) : ?>
                    <span class="text-muted ml-2">(<?= htmlspecialchars($accountInfo['email'], ENT_QUOTES, 'UTF-8') ?>)</span>
                <?php endif; ?>
            </div>
            <?php if ($latestCampaignDate) : ?>
            <div class="text-right text-muted small">
                <i class="fa-solid fa-paper-plane mr-1"></i><?= gettext('Last campaign') ?>: 
                <strong><?= date('M j, Y', strtotime($latestCampaignDate)) ?></strong>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Overview -->
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3><?= count($mailChimpLists) ?></h3>
                    <p><?= gettext('Audiences') ?></p>
                </div>
                <div class="icon">
                    <i class="fa-solid fa-list"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3><?= number_format($totalMembers) ?></h3>
                    <p><?= gettext('Total Subscribers') ?></p>
                </div>
                <div class="icon">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3><?= number_format($totalCampaigns) ?></h3>
                    <p><?= gettext('Total Campaigns') ?></p>
                </div>
                <div class="icon">
                    <i class="fa-solid fa-paper-plane"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3><i class="fa-solid fa-check"></i></h3>
                    <p><?= gettext('Connected') ?></p>
                </div>
                <div class="icon">
                    <i class="fa-solid fa-plug"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Audiences / Lists -->
    <div class="row">
        <?php foreach ($mailChimpLists as $list) : ?>
            <div class="col-lg-6 col-md-12 mb-4">
                <div class="card card-outline card-primary h-100">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa-solid fa-list mr-2"></i><?= htmlspecialchars($list['name'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>
                        <div class="card-tools">
                            <span class="badge badge-info"><?= number_format($list['stats']['member_count'] ?? 0) ?> <?= gettext('subscribers') ?></span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped mb-0">
                            <tbody>
                                <tr>
                                    <td><i class="fa-solid fa-users text-info mr-2"></i><?= gettext('Subscribers') ?></td>
                                    <td class="text-right"><strong><?= number_format($list['stats']['member_count'] ?? 0) ?></strong></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-paper-plane text-primary mr-2"></i><?= gettext('Campaigns Sent') ?></td>
                                    <td class="text-right"><strong><?= number_format($list['stats']['campaign_count'] ?? 0) ?></strong></td>
                                </tr>
                                <?php if (!empty($list['stats']['campaign_last_sent'])) : ?>
                                <tr>
                                    <td><i class="fa-solid fa-calendar-check text-success mr-2"></i><?= gettext('Last Campaign') ?></td>
                                    <td class="text-right"><?= date('M j, Y', strtotime($list['stats']['campaign_last_sent'])) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($list['stats']['last_sub_date'])) : ?>
                                <tr>
                                    <td><i class="fa-solid fa-user-plus text-success mr-2"></i><?= gettext('Last Subscriber') ?></td>
                                    <td class="text-right"><?= date('M j, Y', strtotime($list['stats']['last_sub_date'])) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><i class="fa-solid fa-user-minus text-warning mr-2"></i><?= gettext('Unsubscribed') ?></td>
                                    <td class="text-right"><?= number_format($list['stats']['unsubscribe_count'] ?? 0) ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-broom text-secondary mr-2"></i><?= gettext('Cleaned') ?></td>
                                    <td class="text-right"><?= number_format($list['stats']['cleaned_count'] ?? 0) ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-envelope-open text-success mr-2"></i><?= gettext('Avg Open Rate') ?></td>
                                    <td class="text-right"><?= number_format(($list['stats']['open_rate'] ?? 0) * 100, 1) ?>%</td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-mouse-pointer text-info mr-2"></i><?= gettext('Avg Click Rate') ?></td>
                                    <td class="text-right"><?= number_format(($list['stats']['click_rate'] ?? 0) * 100, 1) ?>%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="btn-group btn-group-sm d-flex" role="group">
                            <a href="<?= SystemURLs::getRootPath() ?>/plugins/mailchimp/list/<?= $list['id'] ?>/unsubscribed" class="btn btn-outline-primary flex-fill">
                                <i class="fa-solid fa-user-plus mr-1"></i><?= gettext('Not Subscribed') ?>
                            </a>
                            <a href="<?= SystemURLs::getRootPath() ?>/plugins/mailchimp/list/<?= $list['id'] ?>/missing" class="btn btn-outline-warning flex-fill">
                                <i class="fa-solid fa-user-slash mr-1"></i><?= gettext('Not in CRM') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($isMailChimpActive) : ?>
    <!-- No Lists Found -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fa-brands fa-mailchimp fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted"><?= gettext('No Audiences Found') ?></h4>
                    <p class="text-muted"><?= gettext('Your MailChimp account does not have any audiences (lists) configured.') ?></p>
                    <a href="https://mailchimp.com/help/create-audience/" target="_blank" class="btn btn-primary">
                        <i class="fa-solid fa-external-link-alt mr-1"></i><?= gettext('Create an Audience in MailChimp') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php else : ?>
    <!-- Not Configured -->
    <div class="row">
        <div class="col-12">
            <div class="callout callout-warning">
                <h5><i class="fa-solid fa-exclamation-triangle mr-2"></i><?= gettext('MailChimp is not configured') ?></h5>
                <p><?= gettext('Please configure your MailChimp API key to use this integration.') ?></p>
                <a href="<?= SystemURLs::getRootPath() ?>/plugins/management/mailchimp" class="btn btn-warning">
                    <i class="fa-solid fa-cog mr-1"></i><?= gettext('Configure MailChimp') ?>
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
