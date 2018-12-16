<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="row">
  <div class="col-lg-8 col-md-4 col-sm-4">
    <div class="box">
      <div class="box-header">
        <h3 class="box-title">Members</h3>
      </div>
      <div class="box-body table-responsive no-padding">
        <table class="table table-hover">
          <tr>
            <th></th>
            <th>Name</th>
            <th>Email</th>
          </tr>
           <?php
            foreach ($missingEmailInMailChimp as $Person) {
              /* @var $Person ChurchCRM\Person */
            ?>
            <tr>
              <td><img src="<?= SystemURLs::getRootPath(); ?>/api/person/<?= $Person->getId() ?>/thumbnail" alt="User Image" class="user-image initials-image" width="85" height="85" /></td>
              <td><a href='<?=SystemURLs::getRootPath()?>/PersonView.php?PersonID=<?= $Person->getId() ?>'><?= $Person->getFullName() ?></a></td>
              <td><?= $Person->getEmail() ?></td>
            </tr>
            <?php  } ?>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
