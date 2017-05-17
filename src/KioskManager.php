<?php
/*******************************************************************************
 *
 *  filename    : Dashboard.php
 *  last change : 2014-11-29
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2014
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';


//Set the page title
$sPageTitle = gettext('Kiosk Manager');

require 'Include/Header.php';
?>
<div class="row">
  <div class="col-lg-4 col-md-2 col-sm-2">
    <div class="box">
      <div class="box-header">
        <h3 class="box-title"><?= gettext('Kiosk Manager') ?></h3>
      </div>
      <div class="box-body">
       
      </div>
    </div>
  </div>
</div>

<?php

require 'Include/Footer.php';
?>
