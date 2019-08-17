<?php

include "../../vendor/autoload.php";

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Base\FamilyQuery;
use ChurchCRM\PledgeQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Propel\Runtime\Exception\PropelException;
use ChurchCRM\Base\Family as BaseFamily;
use ChurchCRM\Base\PersonQuery;
use ChurchCRM\DonationFundQuery;
use ChurchCRM\Transaction;
use ChurchCRM\TransactionSplit;
use ChurchCRM\Account;
use ChurchCRM\AccountQuery;

// // create typeofmbr table
$sSQL = "CREATE TABLE `account_acct` (
    `acct_id` SMALLINT(9) NOT NULL AUTO_INCREMENT,
    `acct_name` TEXT NOT NULL,
    `acct_type` enum('DONOR','FUND'),
    `acct_person_id` SMALLINT NOT NULL,
    `acct_family_id` SMALLINT NOT NULL,
    PRIMARY KEY (`acct_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
  
$bval = RunQuery($sSQL);

// create table to hold contributions
$sSQL = "CREATE TABLE `transaction_tran` (
    `tran_ID` SMALLINT(9) NOT NULL AUTO_INCREMENT,
    `tran_DepID` SMALLINT(9),
    `tran_method` enum('CREDITCARD','CHECK','CASH','BANKDRAFT','EGIVE'),
    `tran_Comment` TEXT,
    `tran_DateEntered` DATETIME,
    `tran_EnteredBy` SMALLINT(9) DEFAULT 0,
    `tran_DateLastEdited` DATETIME,
    `tran_EditedBy` SMALLINT(9) DEFAULT 0,
    `tran_CheckNo` bigint(16) unsigned,
    PRIMARY KEY (`tran_ID`)    
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

 $bval = RunQuery($sSQL);

// create table to hold splits
$sSQL = "CREATE TABLE `tran_split`
(
    `tran_split_ID` SMALLINT(9) NOT NULL AUTO_INCREMENT,
    `tran_split_trans_ID` SMALLINT(9) NOT NULL,
    `tran_split_Acct_ID` SMALLINT(9),
    `tran_split_Fund_ID` TINYINT(3) NOT NULL,
    `tran_split_Amount` DECIMAL(8,2),
    `tran_split_Comment` TEXT,
    `tran_split_NonDeductible` DECIMAL(8,2) NOT NULL,
    PRIMARY KEY (`tran_split_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
  
 $bval = RunQuery($sSQL);


// create accounts for funds

$funds = DonationFundQuery::create()->find();

foreach ($funds as $fund) {
  $account = new Account();
  $account->setName($fund->getName());
  $account->setAccountType("FUND");
  $account->save();
}

// move pledge from family to person
$plgGroup = PledgeQuery::create()->filterByPledgeOrPayment('Payment')->groupByGroupkey()->find();


foreach ($plgGroup as $grp) {
  // create contribution
  // assign contribution to person with lowest role number
  $FamID = $grp->getFamID();
  $people = PersonQuery::create()->filterByFamId($FamID)->orderByFmrId()->findOne();
  $PerID = $people->getId();

  // create contribution info
  $transaction = new Transaction();
  $transaction->setDepId($grp->getDepId());
  $transaction->setDateEntered($grp->getDate());
  $transaction->setMethod($grp->getMethod());
  $transaction->setCheckNo($grp->getCheckNo());
  // $transaction->setComment();
  // $transaction->setDateentered();
  // $transaction->setEnteredby();
  $transaction->setDatelastedited($grp->getDatelastedited());
  $transaction->setEditedby($grp->getEditedby());
    
   


  $pledges = PledgeQuery::create()->filterByGroupkey($grp->getGroupkey())->find();
  $totalAmount = 0;
  foreach ($pledges as $plg) {
    // create associated splits
    $Amt = $plg->getAmount();
    $DedAmt = $plg->getNondeductible();

    $totalAmount += $Amt;
    $FundAccount = AccountQuery::create()->findOneByName($plg->getDonationFund()->getName());
    // echo "Amt: $Amt, DedAmt: $DedAmt";
    // add taxable amount
    if ($Amt != 0) {
      // echo "Reg Split Added";
      $split = new TransactionSplit();
      $split->setAccount($FundAccount);
      $split->setAmount($plg->getAmount());
      $split->setNondeductible(false);
      // $split->setDatelastedited($plg->getDatelastedited());
      // $split->setEditedby($plg->getEditedby());
      $transaction->addTransactionSplit($split);

    }
    // add non taxable amount
    if ($DedAmt != 0) {
      // echo "Non-Ded Split Added";
      $split = new TransactionSplit();
      $split->setAccount($FundAccount);
      $split->setAmount($plg->getNondeductible());
      $split->setNondeductible(true);
      // $split->setDatelastedited($plg->getDatelastedited());
      // $split->setEditedby($plg->getEditedby());
      $transaction->addTransactionSplit($split);
    }
  }

  $PersonAccount = AccountQuery::create()->filterByOwnerPersonId($PerID)->findOneOrCreate();
  $PersonAccount->setAccountType("DONOR");
  $PersonAccount->setName($people->getFullName());
  $split = new TransactionSplit();
  $split->setAccount($PersonAccount);
  $split->setAmount($totalAmount * -1);
  $split->setNondeductible(false);
  // $split->setDatelastedited($plg->getDatelastedited());
  // $split->setEditedby($plg->getEditedby());
  $transaction->addTransactionSplit($split);

  $transaction->save();
}

?>