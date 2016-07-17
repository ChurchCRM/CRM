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
    if ( $this->getPledges()->count() == 0 ) {
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
    
    foreach ( $this->getFundTotals() as $fund ) {
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
     foreach( $this->getPledges() as $pledge )
     {
       if ( $pledge->getFundid() && is_null($funds[ $pledge->getFundid() ]) )
       {
         $funds[ $pledge->getFundid() ] =  new \stdClass();
       }
      $funds[ $pledge->getFundid() ]->Total += $pledge->getAmount();
      $funds[ $pledge->getFundid() ]->Name = $pledge->getDonationFund()->getName();
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
}
