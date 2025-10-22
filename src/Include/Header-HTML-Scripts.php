<?php

use ChurchCRM\dto\SystemURLs;

?>
<title>ChurchCRM: <?= $sPageTitle ?></title>

<!-- Webpack-bundled CSS (includes Bootstrap, AdminLTE, FontAwesome, Select2, DataTables, etc) -->
<link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.min.css">

<!-- Webpack-bundled JavaScript (includes jQuery, Bootstrap, plugins, etc) -->
<!-- CRITICAL: No defer - must load immediately so jQuery is available for page-specific scripts -->
<script src="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.js"></script>
