<?php

namespace ChurchCRM;

use ChurchCRM\Base\Deposit as BaseDeposit;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\PledgeQuery as ChildPledgeQuery;

/**
 * Skeleton subclass for representing a row from the 'deposit_dep' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class Deposit extends BaseDeposit
{
  
  public function getOFX()
  {
    $OFXReturn = new \stdClass();
    if ($this->getPledges()->count() == 0) {
      throw new Exception("No Payments on this Deposit",404);
    }

    $orgName = "ChurchCRM Deposit Data";
    $OFXReturn->content = "OFXHEADER:100" . PHP_EOL .
      "DATA:OFXSGML" . PHP_EOL .
      "VERSION:102" . PHP_EOL .
      "SECURITY:NONE" . PHP_EOL .
      "ENCODING:USASCII" . PHP_EOL .
      "CHARSET:1252" . PHP_EOL .
      "COMPRESSION:NONE" . PHP_EOL .
      "OLDFILEUID:NONE" . PHP_EOL .
      "NEWFILEUID:NONE" . PHP_EOL . PHP_EOL;
    $OFXReturn->content .= "<OFX>";
    $OFXReturn->content .= "<SIGNONMSGSRSV1><SONRS><STATUS><CODE>0<SEVERITY>INFO</STATUS><DTSERVER>" . date("YmdHis.u[O:T]") . "<LANGUAGE>ENG<FI><ORG>" . $orgName . "<FID>12345</FI></SONRS></SIGNONMSGSRSV1>";
    $OFXReturn->content .= "<BANKMSGSRSV1>" .
      "<STMTTRNRS>" .
      "<TRNUID>" .
      "<STATUS>" .
      "<CODE>0" .
      "<SEVERITY>INFO" .
      "</STATUS>";


   foreach ( $this->getFundTotals() as $fund ) 
   {
          $OFXReturn->content .= "<STMTRS>" .
            "<CURDEF>USD" .
            "<BANKACCTFROM>" .
            "<BANKID>" . $orgName .
            "<ACCTID>" . $fund->Name .
            "<ACCTTYPE>SAVINGS" .
            "</BANKACCTFROM>";
          $OFXReturn->content .=
            "<STMTTRN>" .
            "<TRNTYPE>CREDIT" .
            "<DTPOSTED>" . $this->getDate("Ymd") .
            "<TRNAMT>" . $fund->Total .
            "<FITID>" .
            "<NAME>" . $this->getComment() .
            "<MEMO>" . $fund->Name .
            "</STMTTRN></STMTRS>";
    }

    $OFXReturn->content .= "</STMTTRNRS></BANKTRANLIST></OFX>";
    // Export file
    $OFXReturn->header = "Content-Disposition: attachment; filename=ChurchCRM-Deposit-" . $depID . "-" . date("Ymd-Gis") . ".ofx";
    return $OFXReturn;
  }
  
  public function getTotalAmount()
  {
    return $this->getVirtualColumn("totalAmount");
  }
  
  public function getTotalChecks()
  {
    $totalChecks = 0;
    foreach ( $this->getPledges() as $pledge)
    {
      if ($pledge->getMethod() == "CHECK")
      {
        $totalChecks += $pledge->getAmount();
      }
    }
    return $totalChecks;
  }
 
  public function getTotalCash()
  {
    $totalCash= 0;
    foreach ( $this->getPledges() as $pledge)
    {
      if ($pledge->getMethod() == "CASH")
      {
        $totalCash += $pledge->getAmount();
      }
    }
    return $totalCash;
  }
  
  public function getCountChecks()
  {
    $countChecks = 0;
    foreach ( $this->getPledges() as $pledge)
    {
      if ($pledge->getMethod() == "CHECK")
      {
        $countChecks += 1;
      }
    }
    return $countChecks;
  }
 
  public function getCountCash()
  {
    $countCash= 0;
    foreach ( $this->getPledges() as $pledge)
    {
      if ($pledge->getMethod() == "CASH")
      {
        $countCash += 1;
      }
    }
    return $countCash;
  }
  
  
  public function getFundTotals()
  {
     //there is probably a better way to do this with Propel ORM...
     $funds = array();
     foreach($this->getPledges() as $pledge)
     {
       if ($pledge->getFundid() && is_null($funds[$pledge->getFundid()]))
       {
         $funds[$pledge->getFundid()] =  new \stdClass();
       }
      $funds[$pledge->getFundid()]->Total += $pledge->getAmount();
      $funds[$pledge->getFundid()]->Name = $pledge->getDonationFund()->getName();
    }
    return $funds;
  }
  
   public function getPledgesJoinAll(Criteria $criteria = null, ConnectionInterface $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildPledgeQuery::create(null, $criteria);
        $query->joinWith('Family', Criteria::RIGHT_JOIN);
        $query->joinWith('DonationFund', Criteria::RIGHT_JOIN);
        return $this->getPledges($query, $con);
    }
    public function getSearchResult()
    {
      $ret = new \stdClass();
      $ret->id = $this->
      $ret['familyID'] = $row['per_fam_ID'];
      $ret['firstName'] = $row['per_FirstName'];
      $ret['lastName'] = $row['per_LastName'];
      $ret['displayName'] = $row['per_FirstName'] . " " . $row['per_LastName'];
      $ret['uri'] = $this->getViewURI($row['per_ID']);
    }
    
    public function loadAuthorized()
    {
       requireUserGroupMembership("bFinance");

    // Create all the payment records that have been authorized

    //Get all the variables from the request object and assign them locally
    $dDate = FilterInput($_POST["Date"]);
    $sComment = FilterInput($_POST["Comment"]);
    if (array_key_exists("Closed", $_POST))
      $bClosed = FilterInput($_POST["Closed"]);
    else
      $bClosed = false;
    $sDepositType = FilterInput($_POST["DepositType"]);
    if (!$bClosed)
      $bClosed = 0;

    // Create any transactions that are authorized as of today
    if ($dep_Type == "CreditCard") {
      $enableStr = "aut_EnableCreditCard=1";
    } else {
      $enableStr = "aut_EnableBankDraft=1";
    }

    // Get all the families with authorized automatic transactions
    $sSQL = "SELECT * FROM autopayment_aut WHERE " . $enableStr . " AND aut_NextPayDate<='" . date('Y-m-d') . "'";

    $rsAuthorizedPayments = RunQuery($sSQL);

    while ($aAutoPayment = mysql_fetch_array($rsAuthorizedPayments)) {
      extract($aAutoPayment);
      if ($dep_Type == "CreditCard") {
        $method = "CREDITCARD";
      } else {
        $method = "BANKDRAFT";
      }
      $dateToday = date("Y-m-d");

      $amount = $aut_Amount;
      $FYID = $aut_FYID;
      $interval = $aut_Interval;
      $fund = $aut_Fund;
      $authDate = $aut_NextPayDate;
      $sGroupKey = genGroupKey($aut_ID, $aut_FamID, $fund, $dateToday);

      // Check for this automatic payment already loaded into this deposit slip
      $sSQL = "SELECT plg_plgID FROM pledge_plg WHERE plg_depID=" . $dep_ID . " AND plg_aut_ID=" . $aut_ID;
      $rsDupPayment = RunQuery($sSQL);
      $dupCnt = mysql_num_rows($rsDupPayment);

      if ($amount > 0.00 && $dupCnt == 0) {
        $sSQL = "INSERT INTO pledge_plg (plg_FamID,
                                                plg_FYID, 
                                                plg_date, 
                                                plg_amount, 
                                                plg_method, 
                                                plg_DateLastEdited, 
                                                plg_EditedBy, 
                                                plg_PledgeOrPayment, 
                                                plg_fundID, 
                                                plg_depID,
                                                plg_aut_ID,
                                                plg_CheckNo,
                                                plg_GroupKey)
                                    VALUES (" .
          $aut_FamID . "," .
          $FYID . "," .
          "'" . date("Y-m-d") . "'," .
          $amount . "," .
          "'" . $method . "'," .
          "'" . date("Y-m-d") . "'," .
          $_SESSION['iUserID'] . "," .
          "'Payment'," .
          $fund . "," .
          $dep_ID . "," .
          $aut_ID . "," .
          $aut_Serial . "," .
          "'" . $sGroupKey . "')";
        RunQuery($sSQL);
      }
    }
    }
    
    public function runTransactions()
    {
      requireUserGroupMembership("bFinance");
      // Process all the transactions



      if ($sElectronicTransactionProcessor == "AuthorizeNet") {
        // This file is generated by Composer
        require_once dirname(__FILE__) . '/../vendor/autoload.php';
        include("Include/AuthorizeNetConfig.php"); // Specific account information is in here
      }

      if ($sElectronicTransactionProcessor == "Vanco") {
        include "Include/vancowebservices.php";
        include "Include/VancoConfig.php";
      }

      foreach( $this->getPledges() as $pledge) {
        if ($pledge->getAutCleared()) // If this one already cleared do not submit it again.
          continue;

        if ($sElectronicTransactionProcessor == "AuthorizeNet") {
          $this->processAuthorizeNet();

        } else if ($sElectronicTransactionProcessor == "Vanco") {
          $this->processVanco();
        }
      }
    }
}
