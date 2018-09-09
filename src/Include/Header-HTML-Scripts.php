<?php
use ChurchCRM\dto\SystemURLs;

$bundle = new \DotsUnited\BundleFu\Bundle();
$bundle->setDocRoot(SystemURLs::getDocumentRoot());
$bundle->setCssCacheUrl("/CRM/css/cache");
$bundle->setJsCacheUrl("/CRM/js/cache");
$bundle->setCssFilter(new ChurchCRM\CSSFilter(SystemURLs::getDocumentRoot()));
?>
<title>ChurchCRM: <?= $sPageTitle ?></title>
<?php 
$bundle->start();
?>
<!-- Bootstrap CSS -->
<link rel="stylesheet" type="text/css" href="skin/adminlte/bootstrap/css/bootstrap.min.css">
<!-- Custom ChurchCRM styles -->
<link rel="stylesheet" href="skin/churchcrm.min.css">
<!-- jQuery 2.1.4 -->
<script src="skin/adminlte/plugins/jQuery/jquery-2.2.3.min.js"></script>
<!-- jQuery UI -->
<script src="skin/external/jquery-ui/jquery-ui.min.js"></script>
<script src="skin/external/moment/moment-with-locales.min.js"></script>
<!-- Bootstrap 3.3.5 -->
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/bootstrap/js/bootstrap.min.js"></script>
<!-- SlimScroll -->
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/slimScroll/jquery.slimscroll.min.js"></script>
<!-- AdminLTE App -->
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/dist/js/app.min.js"></script>

<!-- InputMask -->
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/input-mask/jquery.inputmask.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/input-mask/jquery.inputmask.date.extensions.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/input-mask/jquery.inputmask.extensions.js"></script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/datepicker/bootstrap-datepicker.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/daterangepicker/daterangepicker.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/timepicker/bootstrap-timepicker.js"></script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/datatables/dataTables.bootstrap.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/datatables/extensions/Responsive/js/dataTables.responsive.min.js" ></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/datatables/extensions/TableTools/js/dataTables.tableTools.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/datatables/extensions/Select/dataTables.select.min.js"></script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/chartjs/Chart.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/pace/pace.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/select2/select2.full.min.js"></script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-notify/bootstrap-notify.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/fullcalendar/fullcalendar.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootbox/bootbox.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/fastclick/fastclick.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-toggle/bootstrap-toggle.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/i18next/i18next.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/locale/js/<?= $localeInfo->getLocale() ?>.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-validator/validator.min.js"></script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/IssueReporter.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/DataTables.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/Tooltips.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/Events.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/Footer.js"></script>
<?php
$bundle->end();
echo $bundle->renderCss();
echo $bundle->renderJs();
?>