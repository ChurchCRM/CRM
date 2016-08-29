<?php

namespace ChurchCRM;

use ChurchCRM\Base\Pledge as BasePledge;
use Propel\Runtime\Exception\PropelException;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

/**
 * Skeleton subclass for representing a row from the 'pledge_plg' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class Pledge extends BasePledge
{
  public function preDelete()
  {
    
   $deposit = DepositQuery::create()->findOneById($this->getDepid());
    if (!$deposit->getClosed()) {
      return true;
    } else {
     throw new PropelException("Cannot delete a payment from a closed deposit",500);
    }
  }
  
  function processAuthorizeNet()
  {
    global $sAUTHORIZENET_API_LOGIN_ID, $sAUTHORIZENET_TRANSACTION_KEY;
    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
    $merchantAuthentication->setName($sAUTHORIZENET_API_LOGIN_ID);
    $merchantAuthentication->setTransactionKey($sAUTHORIZENET_TRANSACTION_KEY);
    $refId = 'ref' . time();
    $paymentOne = new AnetAPI\PaymentType();
    echo "Determining Payment Type: ".$this->getDeposit()->getType();
    if($this->getDeposit()->getType() == "CreditCard")
    {
      echo "Setting Credit Card \r\n";
     // Create the payment data for a credit card
      $creditCard = new AnetAPI\CreditCardType();
      $creditCard->setCardNumber("4111111111111111");
      $creditCard->setExpirationDate("1226");
      $creditCard->setCardCode("123");
      $paymentOne->setCreditCard($creditCard);
    }
    elseif($this->getDeposit()->getType() == "eGive")
    {
      echo "Setting Checking \r\n";
        // Create the payment data for a Bank Account
      $bankAccount = new AnetAPI\BankAccountType();
      //$bankAccount->setAccountType('CHECKING');
      $bankAccount->setEcheckType('WEB');
      $bankAccount->setRoutingNumber('121042882');
      $bankAccount->setAccountNumber('123456789123');
      $bankAccount->setNameOnAccount('Jane Doe');
      $bankAccount->setBankName('Bank of the Earth'); 
      $paymentOne->setBankAccount($bankAccount);
    }
    
    $transactionRequestType = new AnetAPI\TransactionRequestType();
    $transactionRequestType->setTransactionType("authCaptureTransaction");
    $transactionRequestType->setAmount($this->getAmount());
    $transactionRequestType->setPayment($paymentOne);
    
    $request = new AnetAPI\CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setRefId( $refId);
    $request->setTransactionRequest( $transactionRequestType);
    $controller = new AnetController\CreateTransactionController($request);
    $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::SANDBOX);
    if ($response != null)
    {
      $tresponse = $response->getTransactionResponse();
      if (($tresponse != null) && ($tresponse->getResponseCode()== "1") )   
      {
        echo  "Debit Bank Account APPROVED  :" . "\n";
        echo " Debit Bank Account AUTH CODE : " . $tresponse->getAuthCode() . "\n";
        echo " Debit Banlk Account TRANS ID  : " . $tresponse->getTransId() . "\n";
      }
      elseif (($tresponse != null) && ($tresponse->getResponseCode()=="2") )
      {
        echo  "Debit Bank Account ERROR : DECLINED" . "\n";
        $errorMessages = $tresponse->getErrors();
        echo  "Error : " . $errorMessages[0]->getErrorText() . "\n";
      }
      elseif (($tresponse != null) && ($tresponse->getResponseCode()=="4") )
      {
          echo  "Debit Bank Account ERROR: HELD FOR REVIEW:"  . "\n";
      }
      else
      {
          echo  "Debit Bank Account 3 response returned";
      }
    }
    else
    {
      echo  "Debit Bank Account Null response returned";
    }
    return $response;
  }

}
