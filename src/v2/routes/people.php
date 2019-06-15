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

//use ChurchCRM\PersonCustomMasterQuery;
//use ChurchCRM\PersonCustomQuery;
//use ChurchCRM\GroupQuery;
use ChurchCRM\ListOptionQuery;
//use ChurchCRM\Service\GroupService;

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
    //echo "<script>alert('listPeople')</script>";
    $renderer = new PhpRenderer('templates/people/');
    //$sMode = 'Active';
    // Filter received user input as needed
    // Classification
    // Gender
    // FamilyRole
    //echo "<script>alert($sMode)</script>";
    // filterByClsId: src\ChurchCRM\model\ChurchCRM\Base\PersonQuery.php
    $member = PersonQuery::create();
    // set default sMode
    $sMode = "People";
    
    if (isset($_GET['Classification'])) {
        $id = InputUtils::LegacyFilterInput($_GET['Classification']);
        
        $member->filterByClsId($id);

        $option =  ListOptionQuery::create()->filterById(1)->filterByOptionId($id)->find();
        
        foreach($option as $element) {
            $sMode = $element->getOptionName(); 
        }
    }

    if (isset($_GET['FamilyRole'])) {
        $id = InputUtils::LegacyFilterInput($_GET['FamilyRole']);
        
        $member->filterByFmrId($id);

        $option =  ListOptionQuery::create()->filterById(2)->filterByOptionId($id)->find();
        
        if ($id == 0) {
            $sMode = gettext('Unknown');
        } else {
            foreach($option as $element) {
                $sMode = $element->getOptionName(); 
            }
        }
    }

      if (isset($_GET['Gender'])) {
        $id = InputUtils::LegacyFilterInput($_GET['Gender']);
        
        $member->filterByGender($id);

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

    $pageArgs = [
        'sMode' => $sMode,
        'sRootPath' => SystemURLs::getRootPath(),
        'member' => $member
    ];

    return $renderer->render($response, 'person-list.php', $pageArgs);
}
