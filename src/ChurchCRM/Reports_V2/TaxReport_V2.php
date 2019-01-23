<?php

use ChurchCRM\Family;
use ChurchCRM\Person;
use ChurchCRM\FamilyQuery;
use ChurchCRM\PledgeQuery;
use Propel\Runtime\ActiveQuery\Criteria;

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class TaxReport_V2 {
  private $filterFamilies;
  private $filterClassifications;
  private $filterDonationFunds;
  private $filterStartDate;
  private $filterEndDate;
  private $filterDeposits;
  private $filterMinimumAmount;
  
  public static function create() {
    return new TaxReport_V2();
  }
  
  public function __construct() {
    
  }
  
  public function filterByFamily($Families) {
    $this->filterFamilies = $Families;
    return $this;
  }
  
  public function filterByClassification($Classifications) {
    $this->filterClassifications = $Classifications;
    return $this;
  }
  
  public function filterByDonationFund($DonationFunds) {
    $this->filterDonationFunds = $DonationFunds;
    return $this;
  }
  
  public function filterByDate($StartDate,$EndDate) {
    $this->filterStartDate = $StartDate;
    $this->filterEndDate = $EndDate;
    return $this;
  }
  
  public function filterByDeposit($Deposits) {
    $this->filterDeposits = $Deposit;
    return $this;
  }
  
  public function filterByMinimumAmount($Amount) {
    $this->filterMinimumAmount = $Amount;
    return $this;
  }
  
  private function Execute() {
    
    $ReportFamilyQuery = FamilyQuery::create();
    
    if (!is_null($this->filterFamilies)) {
      $ReportFamilyQuery->filterById($this->filterFamilies);
    }
    
    if (!is_null($this->filterClassifications))
    {

    }
    
    $ReportFamilies = $ReportFamilyQuery->find();
    
    $ReportPaymentQuery = PledgeQuery::create();
    $ReportPaymentQuery->filterByFamily($ReportFamilies);
    
    if(!is_null($this->filterDonationFunds)) {
       $ReportPaymentQuery->filterByDonationFund($this->filterDonationFunds);
    }
    
    if(!is_null($this->filterStartDate) && !is_null($this->filterEndDate)) {
       $ReportPaymentQuery->filterByDate($this->filterStartDate, Criteria::GREATER_EQUAL);
       $ReportPaymentQuery->filterByDate($this->filterEndDate, Criteria::LESS_EQUAL);
    }
            
    $ReportPayments = $ReportPaymentQuery
            ->orderByDate()
            ->find();
    
    $PDF = new PDF_TaxReport_V2($this->filterStartDate,$this->filterEndDate,"none",false);
    
    Foreach($ReportFamilies as $ReportFamily) 
    {
      $FamilyPayments = array();
      /* @var $ReportPayment ChurchCRM\Pledge */
      foreach ($ReportPayments as $ReportPayment)
      {
        if ($ReportPayment->getFamId() == $ReportFamily->getId()) {
          array_push($FamilyPayments, $ReportPayment);
        }
      }
      $familySum = 0;
      /* @var $FamilyPayment ChurchCRM\Pledge */
      foreach ($FamilyPayments as $FamilyPayment) {
       $familySum += $FamilyPayment->getAmount();
      }      
     $PDF->NewFamilyPaymentsPage($ReportFamily, $FamilyPayments);
    }
    $PDF->Output("TaxReport.pdf","D");
    return;
   }
  
  public function GetPDF() {
    $this->Execute();
  }
  
  
  public function GetCSV() {
     $this->Execute();
  }
  
}