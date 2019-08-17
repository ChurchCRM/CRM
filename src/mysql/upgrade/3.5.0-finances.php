<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Base\FamilyQuery;
use ChurchCRM\PledgeQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Propel\Runtime\Exception\PropelException;
use ChurchCRM\Base\Family as BaseFamily;
use ChurchCRM\Base\PersonQuery;
use ChurchCRM\Contrib;
use ChurchCRM\ContribSplit;

// // create typeofmbr table
$sSQL = "CREATE TABLE IF NOT EXISTS `typeofmbr` (
    `typeid` tinyint(3) NOT NULL,
    `Name` tinytext NOT NULL,
    PRIMARY KEY (`typeid`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
  
 $bval = RunQuery($sSQL);

 //echo "<script>alert('Create typeofmbr " . $bval . "');</script>";

 // 
$sSQL = "IF NOT EXISTS (SELECT * FROM typeofmbr LIMIT 1)
        THEN
            INSERT INTO `typeofmbr` (`typeid`, `Name`) VALUES (1, 'Business'), (2, 'Family'), (3, 'Person');
        END IF";

$bval = RunQuery($sSQL);

// create table to hold contributions
$sSQL = "CREATE TABLE IF NOT EXISTS `contrib_con` (
  `con_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `con_ContribID` mediumint(9) unsigned NOT NULL,
  `con_TypeOfMbr` enum('0','1','2','3') COLLATE utf8_unicode_ci DEFAULT NULL,
  `con_DepID` mediumint(9) unsigned DEFAULT NULL,
  `con_Date` date DEFAULT NULL,
  -- `con_Amount` decimal(8,2) unsigned NOT NULL,
  `con_Method` enum('CREDITCARD','CHECK','CASH','BANKDRAFT','EGIVE'),
  `con_CheckNo` bigint(16) unsigned,
  `con_Comment` text COLLATE utf8_unicode_ci,
  `con_DateEntered` datetime DEFAULT NULL,
  `con_EnteredBy` mediumint(9) unsigned DEFAULT NULL,
  `con_DateLastEdited` datetime DEFAULT NULL,
  `con_EditedBy` mediumint(9) unsigned DEFAULT NULL,
  PRIMARY KEY (`con_ID`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

 $bval = RunQuery($sSQL);

// create table to hold splits
$sSQL = "CREATE TABLE IF NOT EXISTS `contrib_split` (
  `spl_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `spl_ConID` mediumint(9) unsigned NOT NULL,
  `spl_FundID` tinyint(3) unsigned NOT NULL,
  `spl_Amount` decimal(8,2) unsigned NOT NULL,
  `spl_Comment` text COLLATE utf8_unicode_ci,
  `spl_NonDeductible` tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`spl_ID`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
  
 $bval = RunQuery($sSQL);

// move pledge from family to person
$plgGroup = PledgeQuery::create()->filterByPledgeOrPayment('Payment')->groupByGroupkey()->find();


  foreach ($plgGroup as $grp) {
    // create contribution
    // assign contribution to person with lowest role number
    $FamID = $grp->getFamID();
    $people = PersonQuery::create()->filterByFamId($FamID)->orderByFmrId()->findOne();
    $PerID = $people->getId();

    // create contribution info
    $contributions = new Contrib();
    $contributions->setConID($PerID);
    $contributions->setTypeOfMbr(3);
    $contributions->setDepId($grp->getDepId());
    $contributions->setDate($grp->getDate());
    $contributions->setMethod($grp->getMethod());
    $contributions->setCheckNo($grp->getCheckNo());
    // $contributions->setComment();
    // $contributions->setDateentered();
    // $contributions->setEnteredby();
    $contributions->setDatelastedited($grp->getDatelastedited());
    $contributions->setEditedby($grp->getEditedby());
    $contributions->save();
    $ConID = $contributions->getId();


  $pledges = PledgeQuery::create()->filterByGroupkey($grp->getGroupkey())->find();
  foreach ($pledges as $plg) {
    // create associated splits
    $Amt = $plg->getAmount();
    $DedAmt = $plg->getNondeductible();
    // echo "Amt: $Amt, DedAmt: $DedAmt";
    // add taxable amount
    if ($Amt != 0) {
      // echo "Reg Split Added";
      $split = new ContribSplit();
      $split->setConId($ConID);
      $split->setFundId($plg->getFundid());
      $split->setAmount($plg->getAmount());
      $split->setNondeductible(false);
      // $split->setDatelastedited($plg->getDatelastedited());
      // $split->setEditedby($plg->getEditedby());
      $split->save();

    }
    // add non taxable amount
    if ($DedAmt != 0) {
      // echo "Non-Ded Split Added";
      $split = new ContribSplit();
      $split->setConId($ConID);
      $split->setFundId($plg->getFundid());
      $split->setAmount($plg->getNondeductible());
      $split->setNondeductible(true);
      // $split->setDatelastedited($plg->getDatelastedited());
      // $split->setEditedby($plg->getEditedby());
      $split->save();
    }
  }
}