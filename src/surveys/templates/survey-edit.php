<?php
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
require SystemURLs::getDocumentRoot() . '/Include/SimpleConfig.php';
//Set the page title
$sPageTitle = gettext("Survey Editor");
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
/**
 * @var $sessionUser \ChurchCRM\User
 */
$sessionUser = $_SESSION['user'];

?>

<script src="<?= SystemURLs::getRootPath()?>/skin/external/surveyjs-editor/knockout-latest.js"></script>
<link rel="stylesheet" href="<?= SystemURLs::getRootPath()?>/skin/external/surveyjs-editor/surveyeditor.css"/>
<script src="<?= SystemURLs::getRootPath()?>/skin/external/surveyjs-editor/survey.ko.min.js"></script>
<script src="<?= SystemURLs::getRootPath()?>/skin/external/surveyjs-editor/surveyeditor.min.js"></script>


<div id="surveyEditorContainer"></div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
 var editorOptions = {showEmbededSurveyTab: false}; //see examples below
  var survey = new SurveyEditor.SurveyEditor("surveyEditorContainer", editorOptions);
  //set function on save callback

  survey.text= <?= json_encode($survey->getdefinition()) ?>;
</script>



<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>