<?php
require "../Include/Config.php";
require "../Include/Functions.php";

// Get all the groups
$sSQL = "select grp.grp_Name sundayschoolClass, kid.per_ID kidId, kid.per_FirstName firstName, kid.per_LastName LastName, kid.per_BirthDay birthDay,  kid.per_BirthMonth birthMonth, kid.per_BirthYear birthYear, kid.per_CellPhone mobilePhone,
fam.fam_HomePhone homePhone,
dad.per_FirstName dadFirstName, dad.per_LastName dadLastName, dad.per_CellPhone dadCellPhone, dad.per_Email dadEmail,
mom.per_FirstName momFirstName, mom.per_LastName momLastName, mom.per_CellPhone momCellPhone, mom.per_Email momEmail,
fam.fam_Email famEmail, fam.fam_Address1 Address1, fam.fam_Address2 Address2, fam.fam_City city, fam.fam_State state, fam.fam_Zip zip

from person_per kid, family_fam fam
left Join person_per dad on fam.fam_id = dad.per_fam_id and dad.per_Gender = 1 and dad.per_fmr_ID = 1
left join person_per mom on fam.fam_id = mom.per_fam_id and mom.per_Gender = 2 and mom.per_fmr_ID = 2
,`group_grp` grp, `person2group2role_p2g2r` person_grp

where kid.per_fam_id = fam.fam_ID and person_grp.p2g2r_rle_ID = 2 and kid.per_cls_ID = 2 and
grp_Type = 4 and grp.grp_ID = person_grp.p2g2r_grp_ID  and person_grp.p2g2r_per_ID = kid.per_ID
order by grp.grp_Name, fam.fam_Name";
$rsKids = RunQuery($sSQL);

$sSQL = "select kid.per_ID kidId, kid.per_FirstName firstName, kid.per_LastName LastName, kid.per_BirthDay birthDay,  kid.per_BirthMonth birthMonth, kid.per_BirthYear birthYear, kid.per_CellPhone mobilePhone,
fam.fam_Address1 Address1, fam.fam_Address2 Address2, fam.fam_City city, fam.fam_State state, fam.fam_Zip zip
from person_per kid, family_fam fam
where per_fam_id = fam.fam_ID and per_cls_ID = 2 and kid.per_fmr_ID = 3 and
	per_ID not in
	(select per_id from person_per,`group_grp` grp, `person2group2role_p2g2r` person_grp
		where person_grp.p2g2r_rle_ID = 2 and grp_Type = 4 and grp.grp_ID = person_grp.p2g2r_grp_ID  and person_grp.p2g2r_per_ID = kid.per_ID)";
$rsKidsMissing = RunQuery($sSQL);

// Set the page title and include HTML header
$sPageTitle = gettext("Sunday School Class List");
require "../Include/Header.php";

?>

<div class="box">
  <div class="box-header">
    <h3 class="box-title">Current Sunday School Kids</h3>
  </div>
  <!-- /.box-header -->
  <div class="box-body table-responsive">
    <table id="sundayschool" class="table table-striped table-bordered data-table" cellspacing="0" width="100%">
      <thead>
      <tr>
        <th></th>
        <th>Class</th>
        <th>First Name</th>
        <th>Last Name</th>
        <th>Birth Date</th>
        <th>Age</th>
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
          $birthDate = $birthDay . "/" . $birthMonth . "/" . $birthYear;
        }

        echo "<tr>";
        echo "<td><a href='../PersonView.php?PersonID=" . $kidId . "'>";
        echo "	<span class=\"fa-stack\">";
        echo "	<i class=\"fa fa-square fa-stack-2x\"></i>";
        echo "	<i class=\"fa fa-search-plus fa-stack-1x fa-inverse\"></i>";
        echo "	</span></a></td>";
        echo "<td>" . $sundayschoolClass . "</td>";
        echo "<td>" . $firstName . "</td>";
        echo "<td>" . $LastName . "</td>";
        echo "<td>" . $birthDate . "</td>";
        echo "<td>" . FormatAge($birthMonth, $birthDay, $birthYear, "") . "</td>";
        echo "<td>" . $mobilePhone . "</td>";
        echo "<td>" . $homePhone . "</td>";
        echo "<td>" . $Address1 . " " . $Address2 . " " . $city . " " . $state . " " . $zip . "</td>";
        echo "<td>" . $dadFirstName . " " . $dadLastName . "</td>";
        echo "<td>" . $dadCellPhone . "</td>";
        echo "<td>" . $dadEmail . "</td>";
        echo "<td>" . $momFirstName . " " . $momLastName . "</td>";
        echo "<td>" . $momCellPhone . "</td>";
        echo "<td>" . $momEmail . "</td>";
        echo "</tr>";
      }
      ?>
      </tbody>
    </table>
  </div>
  <div class="box-footer">
    <a href="SundaySchoolClassListExport.php" role="button" class="btn btn-info"><i class="glyphicon glyphicon-export"></i> Export to CSV</a>
  </div>
</div>


<div class="box">
  <div class="box-header">
    <h3 class="box-title">Kids not in a Sunday School Class</h3>
  </div>
  <!-- /.box-header -->
  <div class="box-body table-responsive">
    <table id="sundayschoolMissing" class="table table-striped table-bordered data-table" cellspacing="0" width="100%">
      <thead>
      <tr>
        <th></th>
        <th>First Name</th>
        <th>Last Name</th>
        <th>Birth Date</th>
        <th>Age</th>
        <th>Home Address</th>
      </tr>
      </thead>
      <tbody>
      <?php

      while ($aRow = mysql_fetch_array($rsKidsMissing)) {
        extract($aRow);
        $birthDate = "";
        if ($birthYear != "") {
          $birthDate = $birthDay . "/" . $birthMonth . "/" . $birthYear;
        }

        echo "<tr>";
        echo "<td><a href='../PersonView.php?PersonID=" . $kidId . "'>";
        echo "	<span class=\"fa-stack\">";
        echo "	<i class=\"fa fa-square fa-stack-2x\"></i>";
        echo "	<i class=\"fa fa-search-plus fa-stack-1x fa-inverse\"></i>";
        echo "	</span></a></td>";
        echo "<td>" . $firstName . "</td>";
        echo "<td>" . $LastName . "</td>";
        echo "<td>" . $birthDate . "</td>";
        echo "<td>" . FormatAge($birthMonth, $birthDay, $birthYear, "") . "</td>";
        echo "<td>" . $Address1 . " " . $Address2 . " " . $city . " " . $state . " " . $zip . "</td>";
        echo "</tr>";
      }

      ?>
      </tbody>
    </table>
  </div>
</div>

<?php require "../Include/Footer.php" ?>
