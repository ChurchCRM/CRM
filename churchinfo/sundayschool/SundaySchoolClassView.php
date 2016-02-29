<?php

require "../Include/Config.php";
require "../Include/Functions.php";
require "../Include/PersonFunctions.php";

$iGroupId = "-1";
$iGroupName = "Unknown";
if (isset($_GET['groupId'])) {
    $iGroupId = FilterInput($_GET["groupId"], "int");
}

$sSQL = "select * from group_grp where grp_ID =". $iGroupId;
$rsSundaySchoolClass = RunQuery($sSQL);
while ($aRow = mysql_fetch_array($rsSundaySchoolClass)) {
    $iGroupName = $aRow['grp_Name'];
}

// Get all the groups
$sSQL = "select grp.grp_Name sundayschoolClass, kid.per_ID kidId, kid.per_Gender kidGender, kid.per_FirstName firstName, kid.per_Email kidEmail, kid.per_LastName LastName, kid.per_BirthDay birthDay,  kid.per_BirthMonth birthMonth, kid.per_BirthYear birthYear, kid.per_CellPhone mobilePhone,
fam.fam_HomePhone homePhone,
dad.per_ID dadId, dad.per_FirstName dadFirstName, dad.per_LastName dadLastName, dad.per_CellPhone dadCellPhone, dad.per_Email dadEmail,
mom.per_ID momId, mom.per_FirstName momFirstName, mom.per_LastName momLastName, mom.per_CellPhone momCellPhone, mom.per_Email momEmail,
fam.fam_Email famEmail, fam.fam_Address1 Address1, fam.fam_Address2 Address2, fam.fam_City city, fam.fam_State state, fam.fam_Zip zip

from person_per kid, family_fam fam
left Join person_per dad on fam.fam_id = dad.per_fam_id and dad.per_Gender = 1 and dad.per_fmr_ID = 1
left join person_per mom on fam.fam_id = mom.per_fam_id and mom.per_Gender = 2 and mom.per_fmr_ID = 2
,`group_grp` grp, `person2group2role_p2g2r` person_grp

where kid.per_fam_id = fam.fam_ID and person_grp.p2g2r_rle_ID = 2 and grp.grp_ID = ".$iGroupId." and
grp_Type = 4 and grp.grp_ID = person_grp.p2g2r_grp_ID  and person_grp.p2g2r_per_ID = kid.per_ID
order by grp.grp_Name, fam.fam_Name";
$rsKids = RunQuery($sSQL);

$sSQL = "select count(*) numb,  kid.per_BirthMonth birthMonth
from person_per kid, `group_grp` grp, `person2group2role_p2g2r` person_grp
where person_grp.p2g2r_rle_ID = 2 and grp.grp_ID = ".$iGroupId." and grp_Type = 4 and grp.grp_ID = person_grp.p2g2r_grp_ID  and person_grp.p2g2r_per_ID = kid.per_ID
group by birthMonth;";

$rsKidsByMonth = RunQuery($sSQL);

$sSQL = "select count(*) numb,  per_Gender
from person_per kid, `group_grp` grp, `person2group2role_p2g2r` person_grp
where person_grp.p2g2r_rle_ID = 2 and grp.grp_ID = ".$iGroupId." and grp_Type = 4 and grp.grp_ID = person_grp.p2g2r_grp_ID  and person_grp.p2g2r_per_ID = kid.per_ID
group by kid.per_Gender";

$rsKidsByGender = RunQuery($sSQL);

$sSQL = "select person_per.*
from person_per,`group_grp` grp, `person2group2role_p2g2r` person_grp

where person_grp.p2g2r_rle_ID = 1 and grp.grp_ID = ".$iGroupId." and grp_Type = 4 and grp.grp_ID = person_grp.p2g2r_grp_ID  and person_grp.p2g2r_per_ID = per_ID
order by per_FirstName";

$rsTeachers = RunQuery($sSQL);
$sPageTitle = gettext("Sunday School: " . $iGroupName);

$TeachersEmails = array();
$KidsEmails = array();
$ParentsEmails = array();

require "../Include/Header.php";

?>

    <div class="btn-group pull-right clearfix">
        <a class="btn btn-success" data-toggle="modal" data-target="#compose-modal"><i class="fa fa-pencil"></i> Compose Message</a>
        <a class="btn btn-info" href="../GroupView.php?GroupID=<?= $iGroupId?>"><i class="fa fa-eye-slash"></i> LegacyView </a>
    </div>

    <p><br/></p><p><br/></p>

    <div class="box box-success">
        <div class="box-header">
            <h3 class="box-title">Teachers</h3>
        </div><!-- /.box-header -->
        <div class="box-body row">
            <?php while ($aRow = mysql_fetch_array($rsTeachers)) {
                extract($aRow);
                array_push($TeachersEmails, "<".$per_FirstName . " " . $per_LastName."> ". $per_Email);
                ?>
                <div class="col-sm-2">
                    <!-- Begin user profile -->
                    <div class="box box-info text-center user-profile-2">
                        <div class="user-profile-inner">
                            <h4 class="white"><?= $per_FirstName . " " . $per_LastName ?></h4>
                            <img src="<?= $personService->getPhoto($per_ID);?>" class="img-circle profile-avatar" alt="User avatar" width="80" height="80">
                            <a href="mailto:<?= $per_Email ?>" type="button" class="btn btn-primary btn-sm btn-block"><i class="fa fa-envelope"></i> Send Message</a>
                            <a href="../PersonView.php?PersonID=<?= $per_ID ?>" type="button" class="btn btn-primary btn-info btn-block"><i class="fa fa-envelope"></i> View Profile</a>
                        </div>
                    </div>
                </div>
             <?php } ?>
        </div>
    </div>

    <div class="box box-info">
        <div class="box-header">
            <h3 class="box-title">Quick Status</h3>
        </div><!-- /.box-header -->
        <div class="box-body row">
            <div class="col-sm-7">
                <!-- Bar chart -->
                <div class="box box-primary">
                    <div class="box-header">
                        <i class="fa fa-bar-chart-o"></i>
                        <h3 class="box-title">Birthdays by Month</h3>
                    </div>
                    <div class="box-body">
                        <div id="bar-chart" style="height: 300px;"></div>
                    </div><!-- /.box-body-->
                </div><!-- /.box -->
            </div>
            <div class="col-sm-3">
                <!-- Donut chart -->
                <div class="box box-primary">
                    <div class="box-header">
                        <i class="fa fa-bar-chart-o"></i>
                        <h3 class="box-title">Gender</h3>
                    </div>
                    <div class="box-body">
                        <div id="donut-chart" style="height: 300px;"></div>
                    </div><!-- /.box-body-->
                </div><!-- /.box -->
            </div>
        </div>
    </div>

    <div class="box box-primary">
        <div class="box-header">
            <h3 class="box-title">Kids</h3>
        </div><!-- /.box-header -->
        <div class="box-body table-responsive">
            <table id="sundayschool" class="table table-striped table-bordered" cellspacing="0" width="100%">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Birth Date</th>
                    <th>Age</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Home Phone</th>
                    <th>Home Address</th>
                    <th>Dad Name</th>
                    <th>Dad Mobile</th>
                    <th>Dad Email</th>
                    <th>Mom Name</th>
                    <th>Mom Mobile</th>
                    <th>Mom Email</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($aRow = mysql_fetch_array($rsKids)) {
                    extract($aRow);
                    $birthDate = "";
                    if ($birthYear != "") {
                        $birthDate = $birthMonth."/".$birthDay."/".$birthYear;
                    }
                    if ($dadEmail != "")
                        array_push($ParentsEmails, "<".$dadFirstName." ".$dadLastName."> ". $dadEmail);
                    if ($momEmail != "")
                        array_push($ParentsEmails, "<".$momFirstName." ".$momLastName."> ". $momEmail);
                    if ($kidEmail != "")
                        array_push($KidsEmails, "<".$firstName." ".$LastName."> ". $kidEmail);
                    echo "<tr>";
                        echo "<td><img src='". $personService->getPhoto($kidId). "' hight='30' width='30' > <a href='../PersonView.php?PersonID=" .$kidId."'>".$firstName.", ". $LastName. "</a></td>";
                        echo "<td>".$birthDate."</td>";
                        echo "<td>".FormatAge($birthMonth,$birthDay, $birthYear, "")."</td>";
                        echo "<td>".$kidEmail."</td>";
                        echo "<td>".$mobilePhone."</td>";
                        echo "<td>".$homePhone."</td>";
                        echo "<td>".$Address1." ".$Address2." ".$city." ".$state." ".$zip."</td>";
                        echo "<td><a href='../PersonView.php?PersonID=" .$dadId."'>".$dadFirstName." ".$dadLastName."</a></td>";
                        echo "<td>".$dadCellPhone."</td>";
                        echo "<td>".$dadEmail."</td>";
                        echo "<td><a href='../PersonView.php?PersonID=" .$momId."'>".$momFirstName." ".$momLastName."</td>";
                        echo "<td>".$momCellPhone."</td>";
                        echo "<td>".$momEmail."</td>";
                    echo "</tr>";
                }

                ?>
                </tbody>
            </table>
        </div>
    </div>

<?php
function implodeUnique($array, $withQuotes) {
    array_unique($array);
    asort($array);
    if (count($array) > 0) {
        if ($withQuotes) {
            $string = implode("','", $array);
            return "'" . $string . "'";
        } else {
            return implode(",", $array);
        }
    }
    return "";
}

?>

    <!-- COMPOSE MESSAGE MODAL -->
    <div class="modal fade" id="compose-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content large">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-envelope-o"></i> Compose New Message</h4>
                </div>
                <form action="SendEmail.php" method="post">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Kids Emails</label>
                            <input name="email_to" class="form-control email-recepients-kids" value="<?= implodeUnique($KidsEmails, false) ?>">
                        </div>
                        <div class="form-group">
                            <label>Parents Emails</label>
                            <input name="email_to_2" class="form-control email-recepients-parents" value="<?= implodeUnique($ParentsEmails, false) ?>">
                        </div>
                        <div class="form-group">
                            <label>Teachers Emails</label>
                            <input name="email_cc" class="form-control email-recepients-teachers" value="<?= implodeUnique($TeachersEmails, false) ?>">
                        </div>
                        <div class="form-group">
                            <textarea name="message" id="email_message" class="form-control" placeholder="Message" style="height: 120px;"></textarea>
                        </div>
                        <div class="form-group">
                            <div class="btn btn-success btn-file">
                                <i class="fa fa-paperclip"></i> Attachment
                                <input type="file" name="attachment"/>
                            </div>
                            <p class="help-block">Max. 32MB</p>
                        </div>

                    </div>
                    <div class="modal-footer clearfix">

                        <button type="button" class="btn btn-danger" data-dismiss="modal"><i class="fa fa-times"></i> Discard</button>

                        <button type="submit" class="btn btn-primary pull-left"><i class="fa fa-envelope"></i> Send Message</button>
                    </div>
                </form>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->

    <!-- FLOT CHARTS -->
    <script src="<?= $sRootPath ?>/skin/adminlte/plugins/flot/jquery.flot.min.js" type="text/javascript"></script>
    <!-- FLOT RESIZE PLUGIN - allows the chart to redraw when the window is resized -->
    <script src="<?= $sRootPath ?>/skin/adminlte/plugins/flot/jquery.flot.resize.min.js" type="text/javascript"></script>
    <!-- FLOT PIE PLUGIN - also used to draw donut charts -->
    <script src="<?= $sRootPath ?>/skin/adminlte/plugins/flot/jquery.flot.pie.min.js" type="text/javascript"></script>
    <!-- FLOT CATEGORIES PLUGIN - Used to draw bar charts -->
    <script src="<?= $sRootPath ?>/skin/adminlte/plugins/flot/jquery.flot.categories.min.js" type="text/javascript"></script>

    <script type="text/javascript" charset="utf-8">
        $(document).ready(function() {
            $('#sundayschool').dataTable( {
                "dom": 'T<"clear">lfrtip',
                "tableTools": {
                    "sSwfPath": "//cdn.datatables.net/tabletools/2.2.3/swf/copy_csv_xls_pdf.swf"
                }
            } );

            // turn the element to select2 select style
            $('.email-recepients-kids').select2({
                placeholder: 'Enter recepients',
                tags: [<?php implodeUnique($KidsEmails, true) ?>]
            });
            $('.email-recepients-teachers').select2({
                placeholder: 'Enter recepients',
                tags: [<?= implodeUnique($TeachersEmails, true) ?>]
            });
            $('.email-recepients-parents').select2({
                placeholder: 'Enter recepients',
                tags: [<?= implodeUnique($ParentsEmails, true) ?>]
            });
        } );

        /*
         * BAR CHART
         * ---------
         */

        var bar_data = {
            data: [
                <?php while ($row = mysql_fetch_array($rsKidsByMonth)) {
                    $iMonth = $row['birthMonth'];
                    $iKidsCount = $row['numb'];
                    if ($iMonth == 1 ) { ?>
                        ["January", <?= $iKidsCount ?>],
                    <?php } else if ($iMonth == 2 ) { ?>
                        ["February", <?= $iKidsCount ?>],
                    <?php } else if ($iMonth == 3 ) { ?>
                        ["March", <?= $iKidsCount ?>],
                    <?php } else if ($iMonth == 4 ) { ?>
                        ["April", <?= $iKidsCount ?>],
                    <?php } else if ($iMonth == 5 ) { ?>
                        ["May", <?= $iKidsCount ?>],
                    <?php } else if ($iMonth == 6 ) { ?>
                        ["June", <?= $iKidsCount ?>],
                    <?php } else if ($iMonth == 7 ) { ?>
                        ["July", <?= $iKidsCount ?>],
                    <?php } else if ($iMonth == 8 ) { ?>
                        ["August", <?= $iKidsCount ?>],
                    <?php } else if ($iMonth == 9 ) { ?>
                        ["September", <?= $iKidsCount ?>],
                    <?php } else if ($iMonth == 10 ) { ?>
                        ["October", <?= $iKidsCount ?>],
                    <?php } else if ($iMonth == 11 ) { ?>
                        ["November", <?= $iKidsCount ?>],
                    <?php } else if ($iMonth == 12 ) { ?>
                        ["December", <?= $iKidsCount ?>]
                    <?php }
                } ?>
            ],
            color: "#3c8dbc"
};
$.plot("#bar-chart", [bar_data], {
grid: {
    borderWidth: 1,
    borderColor: "#f3f3f3",
    tickColor: "#f3f3f3"
},
series: {
    bars: {
        show: true,
        barWidth: 0.5,
        align: "center"
    }
},
xaxis: {
    mode: "categories",
    tickLength: 0
}
});
/* END BAR CHART */

/*
* DONUT CHART
* -----------
*/

    var donutData = [
  <?php while ($row = mysql_fetch_array($rsKidsByGender)) {
        if ($row['per_Gender'] == 1 ) {
            echo "{label: \"Boys\", data: ". $row['numb'] ."},";
        }
        if ($row['per_Gender'] == 2 ) {
            echo "{label: \"Girls\", data: ". $row['numb'] ."}";
        }
    } ?>
            ];

        $.plot("#donut-chart", donutData, {
            series: {
                pie: {
                    show: true,
                    radius: 1,
                    innerRadius: 0.5,
                    label: {
                        show: true,
                        radius: 2 / 3,
                        formatter: labelFormatter,
                        threshold: 0.1
                    }

                }
            },
            legend: {
                show: false
            }
        });
        /*
         * END DONUT CHART
         */
        /*
         * Custom Label formatter
         * ----------------------
         */
        function labelFormatter(label, series) {
            return "<div style='font-size:13px; text-align:center; padding:2px; color: #fff; font-weight: 600;'>"
                + label
                + "<br/>"
                + Math.round(series.percent) + "%</div>";
        }

    </script>



<?php

require "../Include/Footer.php";

?>
