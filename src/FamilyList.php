<?php
require "Include/Config.php";
require "Include/Functions.php";

$sSQL = "select * from family_fam fam order by fam_Name";
$rsFamilies = RunQuery($sSQL);

// Set the page title and include HTML header
$sPageTitle = gettext("Family List");
require "Include/Header.php";

?>

<div class="pull-right">
  <a class="btn btn-success" role="button" href="FamilyEditor.php"> <span class="fa fa-plus"
                                                                          aria-hidden="true"></span><?= gettext("Add Family") ?>
  </a>
</div>
<p><br/><br/></p>
<div class="box">
  <div class="box-body table-responsive">
    <table id="families" class="table table-striped table-bordered data-table" cellspacing="0" width="100%">
      <thead>
      <tr>
        <th><?= gettext("Name") ?></th>
        <th><?= gettext("Home Phone") ?></th>
        <th><?= gettext("Address") ?></th>
        <th><?= gettext("City") ?></th>
        <th><?= gettext("State") ?></th>
        <th><?= gettext("Zip") ?></th>
        <th><?= gettext("Created") ?></th>
        <th><?= gettext("Edited") ?></th>
      </tr>
      </thead>
      <tbody>
      <?php

      while ($aRow = mysql_fetch_array($rsFamilies)) {
      extract($aRow);
      ?>
      <tr>
        <td><a href='FamilyView.php?FamilyID=<?= $fam_ID ?>'>
                        <span class="fa-stack">
                            <i class="fa fa-square fa-stack-2x"></i>
                            <i class="fa fa-search-plus fa-stack-1x fa-inverse"></i>
                        </span>
          </a>
          <a href='FamilyEditor.php?FamilyID=<?= $fam_ID ?>'>
                        <span class="fa-stack">
                            <i class="fa fa-square fa-stack-2x"></i>
                            <i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
                        </span>
          </a>
          <?= $fam_Name ?></td>
        <?php
        echo "<td>" . $fam_HomePhone . "</td>";
        echo "<td>" . $fam_Address1 . " " . $fam_Address2 . " </td>";
        echo "<td>" . $fam_City . " </td>";
        echo "<td>" . $fam_State . " </td>";
        echo "<td>" . $fam_Zip . "</td>";
        echo "<td>" . FormatDate($fam_DateEntered, false) . "</td>";
        echo "<td>" . FormatDate($fam_DateLastEdited, false) . "</td>";
        echo "</tr>";
        } ?>
      </tbody>
    </table>
  </div>
</div>

<script type="text/javascript">
  $(document).ready(function () {
    $('#families').dataTable({
      "language": {
        "url": window.CRM.root + "/skin/locale/datatables/" + window.CRM.locale + ".json"
      },
      responsive: true,
      "dom": 'T<"clear">lfrtip',
      "tableTools": {
        "sSwfPath": "//cdn.datatables.net/tabletools/2.2.3/swf/copy_csv_xls_pdf.swf"
      }
    });
  });
</script>

<?php
require "Include/Footer.php";
?>
