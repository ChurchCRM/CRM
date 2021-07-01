<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;


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
    $sMode = "Person";
    // by default show only active families
    $familyActiveStatus = "active";
    if ($_GET['familyActiveStatus'] == "inactive") {
        $familyActiveStatus = "inactive";
    } else if ($_GET['familyActiveStatus'] == "all") {
        $familyActiveStatus = "all";
    }

    if ($familyActiveStatus == "active") {
        $members->leftJoinFamily()->where('family_fam.fam_DateDeactivated is null');
    } else {
        if ($familyActiveStatus == "inactive") {
            $members->leftJoinFamily()->where('family_fam.fam_DateDeactivated is not null');
        }
    }

    $members->find();

    $filterByClsId = '';
    if (isset($_GET['Classification'])) {
        $id = InputUtils::LegacyFilterInput($_GET['Classification']);
        $option =  ListOptionQuery::create()->filterById(1)->filterByOptionId($id)->findOne();
        if ($id == 0) {
            $filterByClsId = gettext('Unassigned');
            $sMode = $filterByClsId;
        } else {
           $filterByClsId = $option->getOptionName();
            $sMode = $filterByClsId;
        }

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
