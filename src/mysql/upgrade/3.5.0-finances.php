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
use ChurchCRM\Utils\LoggerUtils;

// $connection = Propel::getConnection();
// $logger = LoggerUtils::getAppLogger();

// $logger->info("Upgrade person contributions started ");
echo "Upgrade person contributions started<br>";
//Include the function library
// require '../../Include/Config.php';
// require '../../Include/Functions.php';

// copy pledge from family to person
$plgGroup = PledgeQuery::create()->filterByPledgeOrPayment('Payment')->groupByGroupkey()->find();
echo "Ran PledgeQuery<br>";

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
// $logger->info("Pledge data copied to contrib_con");
// $logger->info("Upgrade to person contributions finished ");
echo "Upgrade person contribtuions finished";