<?php

require_once __DIR__ . '/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Utils\PHPToMomentJSConverter;

function Header_modals(): void
{
    ?>
    <!-- Issue Report Modal -->
    <div id="IssueReportModal" class="modal fade" role="dialog">
        <div class="modal-dialog modal-lg">
            <!-- Modal content-->
            <div class="modal-content" id="bugForm">
                <form name="issueReport">
                    <input type="hidden" name="pageName" value="<?= $_SERVER['REQUEST_URI'] ?>"/>
                    <div class="modal-header">
                        <h5 class="modal-title"><?= gettext('Issue Report!') ?></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">x</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                            <h5><i class="icon fa-solid fa-info"></i>Alert!</h5>
                            <?= gettext('When you click "Submit to GitHub" you will be directed to GitHub issues page with your system info prefilled.') ?> <?= gettext('No personally identifiable information will be submitted unless you purposefully include it.') ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="submitIssue"><?= gettext('Submit to GitHub') ?></button>
                    </div>
                </form>
            </div>
            <div id="bug-content">
                test
            </div>
        </div>
    </div>
    <!-- End Issue Report Modal -->

    <?php
}

function Header_body_scripts(): void
{
    $localeInfo = Bootstrapper::getCurrentLocale();
    $currentUser = AuthenticationManager::getCurrentUser();
    $tableSizeSetting = $currentUser->getSetting("ui.table.size");
    if (empty($tableSizeSetting)) {
        $tableSize = 10;
    } else {
        $tableSize = $tableSizeSetting->getValue();
    } ?>
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        // Initialize window.CRM if not already created by webpack bundles
        if (!window.CRM) {
            window.CRM = {};
        }
        
        // Extend window.CRM with server-side configuration (preserving existing properties like notify)
        Object.assign(window.CRM, {
            root: "<?= SystemURLs::getRootPath() ?>",
            fullURL:"<?= SystemURLs::getURL() ?>",
            lang: "<?= $localeInfo->getLanguageCode() ?>",
            userId: "<?= AuthenticationManager::getCurrentUser()->getId() ?>",
            systemLocale: "<?= $localeInfo->getSystemLocale() ?>",
            locale: "<?= $localeInfo->getLocale() ?>",
            shortLocale: "<?= $localeInfo->getShortLocale() ?>",
            maxUploadSize: "<?= SystemService::getMaxUploadFileSize(true) ?>",
            maxUploadSizeBytes: "<?= SystemService::getMaxUploadFileSize(false) ?>",
            datePickerformat:"<?= SystemConfig::getValue('sDatePickerPlaceHolder') ?>",
            churchWebSite:"<?= SystemConfig::getValue('sChurchWebSite') ?>",
            systemConfigs: {
              sDateTimeFormat: "<?= PHPToMomentJSConverter::convertFormatString(SystemConfig::getValue('sDateTimeFormat'))?>",
            },
            iDashboardServiceIntervalTime:"<?= SystemConfig::getValue('iDashboardServiceIntervalTime') ?>",
            bEnableGravatarPhotos: <?= SystemConfig::getBooleanValue('bEnableGravatarPhotos') ? 'true' : 'false' ?>,
            plugin: {
                dataTable : {
                    "pageLength": <?= $tableSize ?>,
                    "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    "language": {
                        "url": "<?= SystemURLs::getRootPath() ?>/locale/vendor/datatables/<?= $localeInfo->getDataTables() ?>.json"
                    },
                    responsive: true,
                    dom: "<'row'<'col-sm-4'<?= $currentUser->isCSVExport() ? "B" : "" ?>><'col-sm-4'r><'col-sm-4 searchStyle'f>>" +
                            "<'row'<'col-sm-12't>>" +
                            "<'row'<'col-sm-4'l><'col-sm-4'i><'col-sm-4'p>>"<?php if ($currentUser->isCSVExport()) { ?>,
                    buttons: ['copy', 'csv', 'excel', 'pdf', 'print']<?php } ?>
                }
            },
            PageName:"<?= $_SERVER['REQUEST_URI']; ?>"
        });
        // Initialize moment locale if available
        if (typeof moment !== 'undefined' && window.CRM.shortLocale) {
            moment.locale(window.CRM.shortLocale);
        }
    </script>
    <script src="<?= SystemURLs::assetVersioned('/skin/js/CRMJSOM.js') ?>"></script>
    <?php
}
