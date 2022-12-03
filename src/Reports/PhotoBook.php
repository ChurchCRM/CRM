<?php
/*******************************************************************************
*
*  filename    : Reports/PhotoBook.php
*  last change : 2017-11-04 Philippe Logel
*  description : Creates a PDF for a Sunday School Class List
*
******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\Reports\ChurchInfoReport;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\GroupQuery;
use ChurchCRM\Map\PersonTableMap;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\Person2group2roleP2g2rQuery;

$iGroupID = InputUtils::LegacyFilterInput($_GET['GroupID']);
$aGrp = explode(',', $iGroupID);

$iFYID = InputUtils::LegacyFilterInput($_GET['FYID'], 'int');

class PDF_PhotoBook extends ChurchInfoReport
{
    private $group;
    private $FYIDString;
    private $currentX;
    private $currentY;
    private $pageMarginL;
    private $pageMarginR;
    private $pageMarginT;
    private $pageMarginB;
    private $personMarginL;
    private $personMarginR;
    private $personImageHeight;
    private $personImageWidth;
  
    // Constructor
    public function __construct($iFYID)
    {
        parent::__construct('P', 'mm', $this->paperFormat);
        $this->pageMarginL = 15;
        $this->pageMarginR = 15;
        $this->pageMarginT = 20;
        $this->pageMarginB = 5;
        $this->personMarginL = 2.5;
        $this->personMarginR = 2.5;
        $this->personImageHeight = 30;
        $this->personImageWidth = 30;
        $this->FYIDString = MakeFYString($iFYID);
    }
    
    public function drawGroup($iGroupID)
    {
        $this->group = GroupQuery::Create()->findOneById($iGroupID);
        $this->SetMargins(0, 0); // use our own margin logic.
        $this->SetFont('Times', '', 14);
        $this->SetAutoPageBreak(false);
        $this->AddPage();
        $this->drawGroupMebersByRole("Teacher", gettext("Teachers"));
        $this->AddPage();
        $this->drawGroupMebersByRole("Student", gettext("Students"));
    }
    
    private function drawPageHeader($title)
    {
        $this->currentX = $this->pageMarginL;
        $this->currentY = $this->pageMarginT;

        $this->SetFont('Times', 'B', 16);
        $this->WriteAt($this->currentX, $this->currentY, $title);
        $this->currentX = 170;
        $this->WriteAt($this->currentX, $this->currentY, $this->FYIDString);
        $this->SetLineWidth(0.5);
        $this->Line($this->pageMarginL, 25.25, $this->GetPageWidth() - $this->pageMarginR, 25.25);
    }
    
    private function drawPersonBlock($name, $thumbnailURI)
    {
   
    # Draw a bounding box around the image placeholder centered around the name text.
        $this->currentX += $this->personMarginL;
        $this->SetFont('Times', '', 10);
        $NameWidth = $this->GetStringWidth($name);
        $offset = ($NameWidth/2) - ($this->personImageWidth /2)+2;
    
        $this->SetLineWidth(0.25);
        $this->Rect($this->currentX, $this->currentY, $this->personImageWidth, $this->personImageHeight);
   
    
        # Draw the image or an x
        if (file_exists($thumbnailURI)) {
            $this->Image($thumbnailURI, $this->currentX+.25, $this->currentY+.25, $this->personImageWidth-.5, $this->personImageHeight-.5, 'PNG');
        } else {
            $this->Line($this->currentX, $this->currentY, $this->currentX + $this->personImageWidth, $this->currentY + $this->personImageHeight);
            $this->Line($this->currentX+$this->personImageWidth, $this->currentY, $this->currentX, $this->currentY + $this->personImageHeight);
        }
     
        # move the cursor, and draw the teacher name
        $this->currentX -= $offset;
        $this->currentY += $this->personImageHeight + 2;
        $this->WriteAt($this->currentX, $this->currentY, $name);
    
        $this->currentX += $offset;
        $this->currentY -= $this->personImageHeight + 2;
    
        $this->currentX += $this->personImageWidth;
        $this->currentX += $this->personMarginR;
    }
  
    private function drawGroupMebersByRole($roleName, $roleDisplayName)
    {
        $RoleListID =$this->group->getRoleListId();
        $groupRole = ListOptionQuery::create()->filterById($RoleListID)->filterByOptionName($roleName)->findOne();
        $groupRoleMemberships = Person2group2roleP2g2rQuery::create()
                            ->filterByGroup($this->group)
                            ->filterByRoleId($groupRole->getOptionId())
                            ->joinWithPerson()
                            ->orderBy(PersonTableMap::COL_PER_LASTNAME)
                            ->_and()->orderBy(PersonTableMap::COL_PER_FIRSTNAME)
                            ->find();
        $this->drawPageHeader((gettext("PhotoBook").' - '.$this->group->getName().' - '.$roleDisplayName." (".$groupRoleMemberships->count().")"));
        $this->currentX = $this->pageMarginL;
        $this->currentY += 10;
        foreach ($groupRoleMemberships as $roleMembership) {
            $person = $roleMembership->getPerson();
            $this->drawPersonBlock($person->getFullName(), $person->getPhoto()->getPhotoURI());
            if ($this->currentX + $this->personMarginL + $this->personImageWidth + $this->personMarginR  >= $this->GetPageWidth() - $this->pageMarginR) { //can we fit another on the page?
                $this->currentY += 50;
                $this->currentX = $this->pageMarginL;
            }
            if ($this->currentY + $this->personImageHeight+10 >= $this->GetPageHeight() - $this->pageMarginB) {
                $this->AddPage();
                $this->drawPageHeader((gettext("PhotoBook").' - '.$this->group->getName().' - '.$roleDisplayName." (".$groupRoleMemberships->count().")"));
                $this->currentX = $this->pageMarginL;
                $this->currentY += 10;
            }
        }
    }
}
// Instantiate the directory class and build the report.
$pdf = new PDF_PhotoBook($iFYID);
foreach ($aGrp as $groupID) {
    $pdf->drawGroup($groupID);
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if ($iPDFOutputType == 1) {
    $pdf->Output('ClassList'.date(SystemConfig::getValue("sDateFilenameFormat")).'.pdf', 'D');
} else {
    $pdf->Output();
}
