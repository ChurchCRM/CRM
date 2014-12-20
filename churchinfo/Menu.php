<?php
/*******************************************************************************
*
*  filename    : Menu.php
*  description : menu that appears after login, shows login attempts
*
*  http://www.churchdb.org/
*  Copyright 2001-2002 Phillip Hullquist, Deane Barker, Michael Wilt
*
*  Additional Contributors:
*  2006 Ed Davis
*
*
*  Copyright Contributors
*
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

// Set the page title
$sPageTitle = gettext('Welcome to ChurchInfo');

require 'Include/Header.php';
?>

<div class="row">
    <div class="col-lg-3 col-sm-6 col-xs-12">
        <div class="main-box infographic-box">
            <i class="fa fa-user red-bg"></i>
            <span class="headline">Persons</span>
            <span class="value">2562</span>
        </div>
        <div class="main-box infographic-box">
            <i class="fa fa-shopping-cart emerald-bg"></i>
            <span class="headline">Cart</span>
            <span class="value">2562</span>
        </div>
        <div class="main-box infographic-box">
            <i class="fa fa-money green-bg"></i>
            <span class="headline">Income</span>
            <span class="value">2562</span>
        </div>
    </div>
</div>

<?php

require 'Include/Footer.php';
?>
