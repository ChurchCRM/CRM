<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\RecordEditorView;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
echo (new RecordEditorView())->render($recordTemplate, $recordValues);
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
