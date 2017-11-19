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
use ChurchCRM\Service\NotificationService;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\GroupQuery;
use ChurchCRM\Group;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\ListOption;
use ChurchCRM\MenuConfigQuery;
use ChurchCRM\MenuConfig;
use ChurchCRM\UserConfigQuery;
use ChurchCRM\UserConfig;

function Header_system_notifications()
{
    if (NotificationService::hasActiveNotifications()) {
        ?>
        <div class="systemNotificationBar">
            <?php
            foreach (NotificationService::getNotifications() as $notification) {
                echo "<a href=\"" . $notification->link . "\">" . $notification->title . "</a>";
            } ?>
        </div>
        <?php
    }
}

function Header_head_metatag()
{
    global $sMetaRefresh, $sPageTitle;

    if (strlen($sMetaRefresh) > 0) {
        echo $sMetaRefresh;
    } ?>
    <title>ChurchCRM: <?= $sPageTitle ?></title>
    <?php
}

function Header_modals()
{
    ?>
    <!-- Issue Report Modal -->
    <div id="IssueReportModal" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
                <form name="issueReport">
                    <input type="hidden" name="pageName" value="<?= $_SERVER['SCRIPT_NAME'] ?>"/>
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
                            <li><?= gettext('No personally identifiable information will be submitted unless you purposefully include it.') ?>
                                "
                            </li>
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
    global $localeInfo;
    $systemService = new SystemService(); ?>
    <script>
        window.CRM = {
            root: "<?= SystemURLs::getRootPath() ?>",
            lang: "<?= $localeInfo->getLanguageCode() ?>",
            locale: "<?= $localeInfo->getLocale() ?>",
            shortLocale: "<?= $localeInfo->getShortLocale() ?>",
            maxUploadSize: "<?= $systemService->getMaxUploadFileSize(true) ?>",
            maxUploadSizeBytes: "<?= $systemService->getMaxUploadFileSize(false) ?>",
            datePickerformat:"<?= SystemConfig::getValue('sDatePickerPlaceHolder') ?>",
            plugin: {
                dataTable : {
                   "language": {
                        "url": "<?= SystemURLs::getRootPath() ?>/locale/datatables/<?= $localeInfo->getDataTables() ?>.json"
                    },
                    responsive: true,
                    "dom": 'T<"clear">lfrtip',
                    "tableTools": {
                        "sSwfPath": "//cdn.datatables.net/tabletools/2.2.3/swf/copy_csv_xls_pdf.swf"
                    }
                }
            }
        };
    </script>
    <script src="<?= SystemURLs::getRootPath() ?>/skin/js/CRMJSOM.js"></script>
    <?php
}

$security_matrix = GetSecuritySettings();

// return the security group to table
function GetSecuritySettings()
{
    $aSecurityListPrimal[] = 'bAdmin';
    $aSecurityListPrimal[] = 'bAddRecords';
    $aSecurityListPrimal[] = 'bEditRecords';
    $aSecurityListPrimal[] = 'bDeleteRecords';
    $aSecurityListPrimal[] = 'bMenuOptions';
    $aSecurityListPrimal[] = 'bManageGroups';
    $aSecurityListPrimal[] = 'bFinance';
    $aSecurityListPrimal[] = 'bNotes';
    $aSecurityListPrimal[] = 'bCommunication';
    $aSecurityListPrimal[] = 'bCanvasser';
    $aSecurityListPrimal[] = 'bAddEvent';
    $aSecurityListPrimal[] = 'bSeePrivacyData';
    
    $ormSecGrpLists = UserConfigQuery::Create()
                        ->filterByPeronId(0)
                        ->filterByCat('SECURITY')
                        ->orderById()
                        ->find();

    foreach ($ormSecGrpLists as $ormSecGrpList) {
        $aSecurityListPrimal[] = $ormSecGrpList->getName();
    }

    asort($aSecurityListPrimal);
    
    $aSecurityListFinal = array('bALL');
    for ($i = 0; $i < count($aSecurityListPrimal); $i++) {
        if (array_key_exists($aSecurityListPrimal[$i], $_SESSION) && $_SESSION[$aSecurityListPrimal[$i]]) {
            $aSecurityListFinal[] = $aSecurityListPrimal[$i];
        } elseif ($aSecurityListPrimal[$i] == 'bAddEvent' && $_SESSION['bAdmin']) {
            $aSecurityListFinal[] = 'bAddEvent';
        }
    }
    
    return $aSecurityListFinal;
}

function addMenu($menu)
{
    global $security_matrix;
    
    $ormMenus = MenuConfigQuery::Create()
                        ->filterByParent('%'.$menu.'%', Criteria::LIKE)
                        ->filterByActive(1);
    
    $firstTime = 1;
    for ($i = 0; $i < count($security_matrix); $i++) {
        if ($firstTime) {
            $ormMenus->filterBySecurityGroup($security_matrix[$i]);
        } else {
            $ormMenus->_or()->filterBySecurityGroup($security_matrix[$i]);
        }
        $firstTime = 0;
    }
    
    $ormMenus->orderBySortOrder()
                        ->find();
    
    $item_cnt = count($ormMenus);
    
    $idx = 1;
    $ptr = 1;
    foreach ($ormMenus as $ormMenu) {
        if (addMenuItem($ormMenu, $idx)) {
            if ($ptr == $item_cnt) {
                $idx++;
            }
            $ptr++;
        } else {
            $item_cnt--;
        }
    }
}

function addMenuItem($ormMenu, $mIdx)
{
    global $security_matrix;
    $maxStr = 25;
    
    $link = ($ormMenu->getURI() == '') ? '' : SystemURLs::getRootPath() . '/' . $ormMenu->getURI();
    $text = $ormMenu->getStatus();
    if (!is_null($ormMenu->getSessionVar())) {
        if (($link > '') && ($ormMenu->getSessionVarInURI()) && isset($_SESSION[$ormMenu->getSessionVar()])) {
            if (strstr($link, '?') && true) {
                $cConnector = '&';
            } else {
                $cConnector = '?';
            }
            $link .= $cConnector . $ormMenu->getURLParmName() . '=' . $_SESSION[$ormMenu->getSessionVar()];
        }
        if (($text > '') && ($ormMenu->getSessionVarInText()) && isset($_SESSION[$ormMenu->getSessionVar()])) {
            $text .= ' ' . $_SESSION[$ormMenu->getSessionVar()];
        }
    }
    if ($ormMenu->getMenu()) {
        $ormItemCnt = MenuConfigQuery::Create()
                        ->filterByParent('%'.$ormMenu->getName().'%', Criteria::LIKE)
                        ->filterByActive(1);
    
        $firstTime = 1;
        for ($i = 0; $i < count($security_matrix); $i++) {
            if ($firstTime) {
                $ormItemCnt->filterBySecurityGroup($security_matrix[$i]);
            } else {
                $ormItemCnt->_or()->filterBySecurityGroup($security_matrix[$i]);
            }
            $firstTime = 0;
        }
        
        $ormItemCnt->orderBySortOrder()
                        ->find();
    
        $numItems = count($ormItemCnt);
    }
    if (!($ormMenu->getMenu()) || ($numItems > 0)) {
        if ($link) {
            if ($ormMenu->getName() != 'sundayschool-dash' && $ormMenu->getName() != 'listgroups') { // HACK to remove the sunday school 2nd dashboard and groups
                echo "<li><a href='$link'>";
                if ($ormMenu->getIcon() != '') {
                    echo '<i class="fa ' . $ormMenu->getIcon() . '"></i>';
                }
                if ($ormMenu->getParent() != 'root') {
                    echo '<i class="fa fa-angle-double-right"></i> ';
                }
                if ($ormMenu->getParent() == 'root') {
                    echo '<span>' . gettext($ormMenu->getContent()) . '</span></a>';
                } else {
                    echo gettext($ormMenu->getContent()) . '</a>';
                }
            } elseif ($ormMenu->getName() == 'listgroups') {
                echo "<li><a href='" . SystemURLs::getRootPath() . "/GroupList.php'><i class='fa fa-angle-double-right'></i>" . gettext('List Groups') . '</a></li>';
                                                
                $listOptions = ListOptionQuery::Create()
                    ->filterById(3)
                    ->orderByOptionName()
                    ->find();
                                                            
                foreach ($listOptions as $listOption) {
                    if ($listOption->getOptionId() != 4) {// we avoid the sundaySchool, it's done under
                        $groups=GroupQuery::Create()
                            ->filterByType($listOption->getOptionId())
                            ->orderByName()
                            ->find();
                                            
                        if (count($groups)>0) {// only if the groups exist : !empty doesn't work !
                            echo "<li><a href='#'><i class='fa fa-user-o'></i>" . $listOption->getOptionName(). '</a>';
                            echo '<ul class="treeview-menu">';
                        
                            foreach ($groups as $group) {
                                $str = $group->getName();
                                if (strlen($str)>$maxStr) {
                                    $str = substr($str, 0, $maxStr-3)." ...";
                                }
                                        
                                echo "<li><a href='" . SystemURLs::getRootPath() . 'GroupView.php?GroupID=' . $group->getID() . "'><i class='fa fa-angle-double-right'></i> " .$str. '</a></li>';
                            }
                            echo '</ul></li>';
                        }
                    }
                }
                                
                // now we're searching the unclassified groups
                $groups=GroupQuery::Create()
                            ->filterByType(0)
                            ->orderByName()
                            ->find();
                                
                if (count($groups)>0) {// only if the groups exist : !empty doesn't work !
                    echo "<li><a href='#'><i class='fa fa-user-o'></i>" . gettext("Unassigned"). '</a>';
                    echo '<ul class="treeview-menu">';

                    foreach ($groups as $group) {
                        echo "<li><a href='" . SystemURLs::getRootPath() . 'GroupView.php?GroupID=' . $group->getID() . "'><i class='fa fa-angle-double-right'></i> " . $group->getName() . '</a></li>';
                    }
                    echo '</ul></li>';
                }
            }
        } else {
            echo "<li class=\"treeview\">\n";
            echo "    <a href=\"#\">\n";
            if ($ormMenu->getIcon() != '') {
                echo '<i class="fa ' . $ormMenu->getIcon() . "\"></i>\n";
            }
            echo '<span>' . gettext($ormMenu->getContent()) . "</span>\n";
            echo "<i class=\"fa fa-angle-left pull-right\"></i>\n";
          
            if ($ormMenu->getName() == 'deposit') {
                echo '<small class="badge pull-right bg-green">' . $_SESSION['iCurrentDeposit'] . "</small>\n";
            } ?>  </a>
      <ul class="treeview-menu">
      <?php
            //Get the Properties assigned to all the sunday Group
            $sSQL = "SELECT pro_Name,grp_ID, r2p_Value, prt_Name, pro_prt_ID, grp_Name
              FROM property_pro
              LEFT JOIN record2property_r2p ON r2p_pro_ID = pro_ID
              LEFT JOIN propertytype_prt ON propertytype_prt.prt_ID = property_pro.pro_prt_ID
              LEFT JOIN group_grp ON group_grp.grp_ID = record2property_r2p.r2p_record_ID
              WHERE pro_Class = 'g' AND grp_Type = '4' AND prt_Name = 'MENU' ORDER BY pro_Name, grp_Name ASC";
            $rsAssignedProperties = RunQuery($sSQL);
                
            //Get the sunday groups not assigned by properties
                
            $sSQL = "SELECT grp_ID , grp_Name,prt_Name,pro_prt_ID
                  FROM group_grp
                  LEFT JOIN record2property_r2p ON record2property_r2p.r2p_record_ID = group_grp.grp_ID
                  LEFT JOIN property_pro ON property_pro.pro_ID = record2property_r2p.r2p_pro_ID
                  LEFT JOIN propertytype_prt ON propertytype_prt.prt_ID = property_pro.pro_prt_ID
                  WHERE ((record2property_r2p.r2p_record_ID IS NULL) OR (propertytype_prt.prt_Name != 'MENU')) AND grp_Type = '4' ORDER BY grp_Name ASC";
            $rsWithoutAssignedProperties = RunQuery($sSQL);
                
                    
            if ($ormMenu->getName() == 'sundayschool') {
                echo "<li><a href='" . SystemURLs::getRootPath() . "/sundayschool/SundaySchoolDashboard.php'><i class='fa fa-angle-double-right'></i>" . gettext('Dashboard') . '</a></li>';
                                                
                $property = '';
                while ($aRow = mysqli_fetch_array($rsAssignedProperties)) {
                    if ($aRow[pro_Name] != $property) {
                        if (!empty($property)) {
                            echo '</ul></li>';
                        }

                        echo '<li><a href="#"><i class="fa fa-user-o"></i><pan>'.$aRow[pro_Name].'</span></a>';
                        echo '<ul class="treeview-menu">';
                        

                        $property = $aRow[pro_Name];
                    }
                            
                    $str = gettext($aRow[grp_Name]);
                    if (strlen($str)>$maxStr) {
                        $str = substr($str, 0, $maxStr-3)." ...";
                    }
                                                    
                    echo "<li><a href='" . SystemURLs::getRootPath() . '/sundayschool/SundaySchoolClassView.php?groupId=' . $aRow[grp_ID] . "'><i class='fa fa-angle-double-right'></i> " .$str. '</a></li>';
                }
                        
                if (!empty($property)) {
                    echo '</ul></li>';
                }
                    
                // the non assigned group to a group property
                while ($aRow = mysqli_fetch_array($rsWithoutAssignedProperties)) {
                    $str = gettext($aRow[grp_Name]);
                    if (strlen($str)>$maxStr) {
                        $str = substr($str, 0, $maxStr-3)." ...";
                    }
                                        
                    echo "<li><a href='" . SystemURLs::getRootPath() . '/sundayschool/SundaySchoolClassView.php?groupId=' . $aRow[grp_ID] . "'><i class='fa fa-angle-double-right'></i> " . $str . '</a></li>';
                }
            }
        }
        if (($ormMenu->getMenu()) && ($numItems > 0)) {
            echo "\n";
            addMenu($ormMenu->getName());
            echo "</ul>\n</li>\n";
        } else {
            echo "</li>\n";
        }

        return true;
    } else {
        return false;
    }
}

?>
