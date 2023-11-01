<?php


$sPageTitle = 'ChurchCRM – Setup Error';
require '../Include/HeaderNotLoggedIn.php';
?>
<style>
    .wizard .content > .body {
        width: 100%;
        height: auto;
        padding: 15px;
        position: relative;
    }

</style>
<h1 class="text-center">Welcome to ChurchCRM Setup Error</h1>
<p/><br/>

  <div class="error-page">
    <h2 class="headline text-yellow">500</h2>
    <div class="error-content">
      <h3><i class="fa fa-warning text-yellow"></i> PHP <?= substr(phpversion(), 0, 3)   ?> not a supported </h3>
      <p/>
      <h4>See <a target="php" href="https://php.net/supported-versions.php" > supported versions</a></h4>
    </div>
    <!-- /.error-content -->
  </div>
  <!-- /.error-page -->

<?php
require '../Include/FooterNotLoggedIn.php';
?>
