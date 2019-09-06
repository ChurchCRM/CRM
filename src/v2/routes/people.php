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

    $renderer = new PhpRenderer('templates/people/');
    // Filter received user input as needed
    // Classification
    // Gender
    // FamilyRole
  
    $members = PersonQuery::create();
    // set default sMode
    $sMode = "People";
    
    $filterByClsId = '';
    if (isset($_GET['Classification'])) {
        $id = InputUtils::LegacyFilterInput($_GET['Classification']);
        $option =  ListOptionQuery::create()->filterById(1)->filterByOptionId($id)->findOne();
        $filterByClsId = $option->getOptionName(); 
        $sMode = $filterByClsId;
    }

    $filterByFmrId = '';
    if (isset($_GET['FamilyRole'])) {
        $id = InputUtils::LegacyFilterInput($_GET['FamilyRole']);
        $option =  ListOptionQuery::create()->filterById(2)->filterByOptionId($id)->findOne();
        
        if ($id == 0) {
            $filterByFmrId = gettext('Unassigned');
            $sMode = $filterByFmrId;
        } else {
            $filterByFmrId = $option->getOptionName();
            $sMode = $filterByFmrId;
        }
    }

    $filterByGender = '';
    if (isset($_GET['Gender'])) {
        $id = InputUtils::LegacyFilterInput($_GET['Gender']);

        switch ($id) {
            case 0:
                $filterByGender = gettext('Unassigned');
                $sMode = $sMode . " - " . $filterByGender;
                break;
            case 1:
                $filterByGender = gettext('Male');
                $sMode = $sMode . " - " . $filterByGender;
                break;
            case 2:
            $filterByGender = gettext('Female');
                $sMode = $sMode . " - " . $filterByGender;
                break;
        }
    }

    $pageArgs = [
        'sMode' => $sMode,
        'sRootPath' => SystemURLs::getRootPath(),
        'members' => $members,
        'filterByClsId' => $filterByClsId,
        'filterByFmrId' => $filterByFmrId,
        'filterByGender' => $filterByGender
    ];

    return $renderer->render($response, 'person-list.php', $pageArgs);
}
