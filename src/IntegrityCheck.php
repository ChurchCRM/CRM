<?php

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

//Set the page title
$sPageTitle = gettext('Integrity Check Results');
if (!$_SESSION['bAdmin'])
{
  Redirect("index.php");
  exit;
}
require 'Include/Header.php';
$integrityCheckFile =  __DIR__ ."/integrityCheck.json";
$IntegrityCheckDetails = json_decode(file_get_contents($integrityCheckFile));

if ($IntegrityCheckDetails->status == "failure")
{
  ?>
  <div class="callout callout-danger">
    <h4><?= gettext("Integrity Check Failure") ?> </h4>
    <p><?= gettext("The previous integrity check failed") ?></p>
    <p><?= gettext("Details:")?> <?=  $IntegrityCheckDetails->message ?></p>
    <?php
      if(count($IntegrityCheckDetails->files) > 0 )
      {
        ?>
        <p><?= gettext("Files failing integrity check") ?>:
        <ul>
          <?php
          foreach ($IntegrityCheckDetails->files as $file)
          {
            ?>
            <li><?= gettext("File Name")?>: <?= $file->filename ?>
              <?php
              if($file->status == "File Missing")
              {
                ?>
                <ul>
                 <li><?= gettext("File Missing")?></li>
                </ul>
                <?php
              }
              else
              {
                ?>
                <ul>
                 <li><?= gettext("Expected Hash")?>: <?= $file->expectedhash ?></li>
                 <li><?= gettext("Actual Hash") ?>: <?= $file->actualhash ?></li>
                </ul>
                <?php
              }
              ?>
            </li>
            <?php
          }
          ?>
        </ul>
        <?php
      }
    ?>
  </div>
<?php
}
else
{
  ?>
  <div class="callout callout-success">
    <h4><?= gettext("Integrity Check Passed") ?> </h4>
    <p><?= gettext("The previous integrity check passed") ?></p>
  </div>
  <?php
}
?>

<?php
require 'Include/Footer.php';
?>
