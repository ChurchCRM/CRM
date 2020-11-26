<?php
/*******************************************************************************
 *
 *  filename    : Include/Header-functions.php
 *  website     : http://www.churchcrm.io
 *  description : page header used for most pages
 *
 *  Copyright 2001-2004 Phillip Hullquist, Deane Barker, Chris Gebhardt, Michael Wilt
 *  Update 2017 Philippe Logel
 *
 *
 ******************************************************************************/

require_once 'Functions.php';

use ChurchCRM\Service\SystemService;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\MenuConfigQuery;
use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\PHPToMomentJSConverter;
use ChurchCRM\Bootstrapper;

function Header_modals()
{
    ?>
    <!-- Issue Report Modal -->
    <div id="IssueReportModal" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
                <form name="issueReport">
                    <input type="hidden" name="pageName" value="<?= $_SERVER['REQUEST_URI'] ?>"/>
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><?= gettext('Issue Report!') ?></h4>
                    </div>
                    <div class="modal-body">
                        <div class="container-fluid">
                            <div class="row">
                                <div class="col-xl-3">
                                    <label
                                            for="issueTitle"><?= gettext('Enter a Title for your bug / feature report') ?>
                                        : </label>
                                </div>
                                <div class="col-xl-3">
                                    <input type="text" name="issueTitle">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-xl-3">
                                    <label
                                            for="issueDescription"><?= gettext('What were you doing when you noticed the bug / feature opportunity?') ?></label>
                                </div>
                                <div class="col-xl-3">
                                    <textarea rows="10" cols="50" name="issueDescription"></textarea>
                                </div>
                            </div>
                        </div>
                        <ul>
                            <li><?= gettext('When you click "submit," an error report will be posted to the ChurchCRM GitHub Issue tracker.') ?></li>
                            <li><?= gettext('Please do not include any confidential information.') ?></li>
                            <li><?= gettext('Some general information about your system will be submitted along with the request such as Server version and browser headers.') ?></li>
                            <li><?= gettext('No personally identifiable information will be submitted unless you purposefully include it.') ?></li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="submitIssue"><?= gettext('Submit') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- End Issue Report Modal -->

    <?php
}

function Header_body_scripts()
{
    $localeInfo = Bootstrapper::GetCurrentLocale();
    $tableSizeSetting =  AuthenticationManager::GetCurrentUser()->getSetting("ui.table.size");
    if (empty($tableSizeSetting)) {
        $tableSize = 10;
    } else {
        $tableSize = $tableSizeSetting->getValue();
    } ?>
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        window.CRM = {
            root: "<?= SystemURLs::getRootPath() ?>",
            fullURL:"<?= SystemURLs::getURL() ?>",
            lang: "<?= $localeInfo->getLanguageCode() ?>",
            userId: "<?= AuthenticationManager::GetCurrentUser()->getId() ?>",
            systemLocale: "<?= $localeInfo->getSystemLocale() ?>",
            locale: "<?= $localeInfo->getLocale() ?>",
            shortLocale: "<?= $localeInfo->getShortLocale() ?>",
            maxUploadSize: "<?= SystemService::getMaxUploadFileSize(true) ?>",
            maxUploadSizeBytes: "<?= SystemService::getMaxUploadFileSize(false) ?>",
            datePickerformat:"<?= SystemConfig::getValue('sDatePickerPlaceHolder') ?>",
            churchWebSite:"<?= SystemConfig::getValue('sChurchWebSite') ?>",
            systemConfigs: {
              sDateTimeFormat: "<?= PHPToMomentJSConverter::ConvertFormatString(SystemConfig::getValue('sDateTimeFormat'))?>",
            },
            iDashboardServiceIntervalTime:"<?= SystemConfig::getValue('iDashboardServiceIntervalTime') ?>",
            plugin: {
                dataTable : {
                    "pageLength": "<?= $tableSize  ?>",
                    "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    "language": {
                        "url": "<?= SystemURLs::getRootPath() ?>/locale/datatables/<?= $localeInfo->getDataTables() ?>.json"
                    },
                    responsive: true,
                    dom: "<'row'<'col-sm-4'<?= AuthenticationManager::GetCurrentUser()->isCSVExport() ? "B" : "" ?>><'col-sm-4'r><'col-sm-4 searchStyle'f>>" +
                            "<'row'<'col-sm-12't>>" +
                            "<'row'<'col-sm-4'l><'col-sm-4'i><'col-sm-4'p>>"
                }
            },
            PageName:"<?= $_SERVER['REQUEST_URI']; ?>"
        };
    </script>
    <script src="<?= SystemURLs::getRootPath() ?>/skin/js/CRMJSOM.js"></script>
    <?php
}
?>
