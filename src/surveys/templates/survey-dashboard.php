<?php
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
require SystemURLs::getDocumentRoot() . '/Include/SimpleConfig.php';
//Set the page title
$sPageTitle = gettext("Survey Dashboard");
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
/**
 * @var $sessionUser \ChurchCRM\User
 */
$sessionUser = $_SESSION['user'];

?>

<div class="row">
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3 id="surveyDefinitionCountDashboard">
                    0
                </h3>
                <p>
                    <?= gettext('Survey Definitions') ?>
                </p>
            </div>
            <div class="icon">
                <i class="fa fa-users"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/surveys/definitions" class="small-box-footer">
                <?= gettext('See all Survey Definitions') ?> <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-green">
            <div class="inner">
                <h3 id="surveyResponsesCountDashboard">
                    0
                </h3>
                <p>
                    <?= gettext('Survey Responses') ?>
                </p>
            </div>
            <div class="icon">
                <i class="fa fa-user"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/surveys/responses" class="small-box-footer">
                <?= gettext('See All Responses') ?> <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
</div><!-- /.row -->

<div class="row">
    <div class="col-lg-6">
        <div class="box box-solid">
            <div class="box-header">
                <i class="fa fa-user-plus"></i>
                <h3 class="box-title"><?= gettext('Survey Definitions') ?></h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                    </button>
                    <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i>
                    </button>
                </div>
            </div><!-- /.box-header -->
            <div class="box-body clearfix">
                <div class="table-responsive" style="overflow:hidden">
                    <table class="dataTable table table-striped table-condensed" id="surveyDefinitionsDashboardItem">
                        <thead>
                        <tr>
                            <th data-field="name"><?= gettext('Survey Definition Name') ?></th>
                            <th data-field="name"><?= gettext('Survey Definition Responses') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
  <div class="col-lg-6">
        <div class="box box-solid">
            <div class="box-header">
                <i class="fa fa-user-plus"></i>
                <h3 class="box-title"><?= gettext('Survey Responses') ?></h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                    </button>
                    <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i>
                    </button>
                </div>
            </div><!-- /.box-header -->
            <div class="box-body clearfix">
                <div class="table-responsive" style="overflow:hidden">
                    <table class="dataTable table table-striped table-condensed" id="surveyResponsesDashboardItem">
                        <thead>
                        <tr>
                            <th data-field="name"><?= gettext('Survey Definition Name') ?></th>
                            <th data-field="name"><?= gettext('Survey Response Time') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>