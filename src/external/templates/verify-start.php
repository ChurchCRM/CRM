<?php

// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Login";
require ("../Include/HeaderNotLoggedIn.php");
?>

  <div class="row">
    <!-- left column -->
    <div class="col-md-6">
      <!-- general form elements -->
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title">Enter Family info to verify</h3>
        </div>
        <!-- /.box-header -->
        <!-- form start -->
        <form role="form" method="post">
          <div class="box-body">
            <div class="form-group">
              <input class="form-control" id="familyNameInput" placeholder="Family Name" required>
            </div>
            <div class="form-group">
              <input class="form-control" id="emailInput" placeholder="Family Member Email" required type="email">
            </div>

          </div>
          <!-- /.box-body -->

          <div class="box-footer">
            <button type="submit" class="btn btn-primary">Submit</button>
          </div>
        </form>
      </div>
      <!-- /.box -->


    </div>
    <!--/.col (left) -->
  </div>

<?php
// Add the page footer
require ("../Include/FooterNotLoggedIn.php");
