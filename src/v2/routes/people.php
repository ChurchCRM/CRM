<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemURLs;
use Slim\Views\PhpRenderer;
use ChurchCRM\PersonQuery;
use ChurchCRM\Service\TimelineService;
use ChurchCRM\PropertyQuery;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

use ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\GroupQuery;


// entity can be a person, family, or business
$app->group('/people', function () {
    //echo "<script>alert('test')</script>";
    $this->get('','listPeople');
    $this->get('/', 'listPeople');
    $this->get('/verify','viewPeopleVerify');

});

function viewPeopleVerify(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/people/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
    ];

    if ($request->getQueryParam("EmailsError")) {
        $errorArgs = ['sGlobalMessage' => gettext("Error sending email(s)") . " - " . gettext("Please check logs for more information"), "sGlobalMessageClass" => "danger"];
        $pageArgs =  array_merge($pageArgs, $errorArgs);
    }

    if ($request->getQueryParam("AllPDFsEmailed")) {
        $headerArgs = ['sGlobalMessage' =>  gettext('PDFs successfully emailed ').$request->getQueryParam("AllPDFsEmailed").' '.gettext('families').".",
        "sGlobalMessageClass" => "success"];
        $pageArgs =  array_merge($pageArgs, $headerArgs);
    }

    return $renderer->render($response, 'people-verify-view.php', $pageArgs);
}

function listPeople(Request $request, Response $response, array $args)
{
    // get list of family properties so we can filter on person-list.php
    $pL = [];
    $pP = PropertyQuery::create()
        ->filterByProClass("p")
        ->find();

    $pL[] = "Unassigned";
    foreach($pP as $element) {
        $pL[] = $element->getProName();
    }

    // get person custom list so we can filter on  person-list.php
    $cL = [];
    $cM = PersonCustomMasterQuery::create()
        ->find();
    
    $cL[] = "Unassigned";
    foreach ($cM as $element) {
        $cL[] = $element->getName();
    }

    // get person group list so we can filter on  person-list.php
    $gL = [];
    $gQ = GroupQuery::create()
        ->find();
    
    $gL[] = "Unassigned";
    foreach ($gQ as $element) {
        $gL[] = $element->getName();
    }

    $renderer = new PhpRenderer('templates/people/');
    // Filter received user input as needed
    // Classification
    // Gender
    // FamilyRole
  
    // filterByClsId: src\ChurchCRM\model\ChurchCRM\Base\PersonQuery.php
    $members = PersonQuery::create();
    // set default sMode
    $sMode = "People";
    
    if (isset($_GET['Classification'])) {
        $id = InputUtils::LegacyFilterInput($_GET['Classification']);
        
        $members->filterByClsId($id)
        ->leftJoinFamily()
        ->where('family_fam.fam_DateDeactivated IS NULL');

        $option =  ListOptionQuery::create()->filterById(1)->filterByOptionId($id)->findOne();
        $sMode = $option->getOptionName(); 
    }

    if (isset($_GET['FamilyRole'])) {
        $id = InputUtils::LegacyFilterInput($_GET['FamilyRole']);
        
        $members->filterByFmrId($id);

        $option =  ListOptionQuery::create()->filterById(2)->filterByOptionId($id)->findOne();
        
        if ($id == 0) {
            $sMode = gettext('Unknown');
        } else {
            $sMode = $option->getOptionName(); 
        }
    }

    if (isset($_GET['Gender'])) {
        $id = InputUtils::LegacyFilterInput($_GET['Gender']);
        
        $members->filterByGender($id);

        switch ($id) {
            case 0:
                $sMode = $sMode . " - " . gettext('Unknown');
                break;
            case 1:
                $sMode = $sMode . " - " . gettext('Male');
                break;
            case 2:
                $sMode = $sMode . " - " . gettext('Female');
                break;
        }
    }
    // groupassign
    if (isset($_GET['groupassign'])) {
        $sMode = gettext('Group Assignment Helper');
    } 

    $pageArgs = [
        'sMode' => $sMode,
        'sRootPath' => SystemURLs::getRootPath(),
        'members' => $members,
        'fp' => json_encode($pL), // FamilyProperties
        'cl' => json_encode($cL), // CustomFields
        'gl' => json_encode($gL) // GroupList
    ];

    return $renderer->render($response, 'person-list.php', $pageArgs);
}
