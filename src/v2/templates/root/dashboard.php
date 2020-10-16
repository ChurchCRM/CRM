<?php


use ChurchCRM\dto\SystemURLs;

//Set the page title
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title"><?= gettext('People') ?></h3>
        <div class="pull-right">
            <div class="btn-group">
                <a href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php">
                    <button type="button" class="btn btn-success"><?= gettext('Add New Person') ?></button>
                </a>
                <a href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php"
                <button type="button" class="btn btn-success"><?= gettext('Add New Family') ?></button>
                </a>
            </div>
        </div>
    </div>
    <div class="box-body">
        <div class="row">
            <div class="col-md-12">
                <!-- Custom Tabs -->
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#ppl-tab_1" data-toggle="tab"><?= gettext('Latest Families') ?></a></li>
                        <li><a href="#ppl-tab_2" data-toggle="tab"><?= gettext('Updated Families') ?></a></li>
                        <li><a href="#ppl-tab_3" data-toggle="tab"><?= gettext('Latest Persons') ?></a></li>
                        <li><a href="#ppl-tab_4" data-toggle="tab"><?= gettext('Updated Persons') ?></a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="ppl-tab_1">
                            <table class="table table-striped" width="100%" id="latestFamiliesDashboardItem"></table>
                        </div>
                        <!-- /.tab-pane -->
                        <div class="tab-pane" id="ppl-tab_2">
                            <table class="table table-striped" width="100%" id="updatedFamiliesDashboardItem"></table>
                        </div>
                        <!-- /.tab-pane -->
                        <div class="tab-pane" id="ppl-tab_3">
                            <table class="table table-striped" width="100%" id="latestPersonDashboardItem"></table>
                        </div>
                        <!-- /.tab-pane -->
                        <div class="tab-pane" id="ppl-tab_4">
                            <table class="table table-striped" width="100%" id="updatedPersonDashboardItem"></table>
                        </div>
                        <!-- /.tab-pane -->
                    </div>
                    <!-- /.tab-content -->
                </div>
                <!-- nav-tabs-custom -->
            </div>
        </div>
    </div>
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/MainDashboard.js"></script>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
