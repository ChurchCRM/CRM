    
##Install:

To use the SDK in your app, create a composer.json file with the following require section and run `composer update`

"require": {
    "php": ">=5.3.0",
    "ext-curl": "*",
    "ext-json": "*",
    "authorizenet/sdk-php" : "1.8.*"
}

##Requirements:
    - cURL PHP Extension
    - PHP 5.2+
    - An Authorize.Net Merchant Account or Test Account. You can get a 
      free test account at http://developer.authorize.net/testaccount/

    
##Usage Examples:
    See below for basic usage examples. View the tests/ folder for more examples of each API.
      
###AuthorizeNetAIM.php Quick Usage Example:
    <?php
    require_once 'anet_php_sdk/AuthorizeNet.php'; 
    define("AUTHORIZENET_API_LOGIN_ID", "YOURLOGIN");
    define("AUTHORIZENET_TRANSACTION_KEY", "YOURKEY");
    define("AUTHORIZENET_SANDBOX", true);
    $sale = new AuthorizeNetAIM;
    $sale->amount = "5.99";
    $sale->card_num = '6011000000000012';
    $sale->exp_date = '04/15';
    $response = $sale->authorizeAndCapture();
    if ($response->approved) {
        $transaction_id = $response->transaction_id;
    }
    ?>
    
###AuthorizeNetAIM.php Advanced Usage Example:
    <?php
    require_once 'anet_php_sdk/AuthorizeNet.php'; 
    define("AUTHORIZENET_API_LOGIN_ID", "YOURLOGIN");
    define("AUTHORIZENET_TRANSACTION_KEY", "YOURKEY");
    define("AUTHORIZENET_SANDBOX", true);
    $auth = new AuthorizeNetAIM;
    $auth->amount = "45.00";

    // Use eCheck:
    $auth->setECheck(
        '121042882',
        '123456789123',
        'CHECKING',
        'Bank of Earth',
        'Jane Doe',
        'WEB'
    );
    
    // Set multiple line items:
    $auth->addLineItem('item1', 'Golf tees', 'Blue tees', '2', '5.00', 'N');
    $auth->addLineItem('item2', 'Golf shirt', 'XL', '1', '40.00', 'N');
    
    // Set Invoice Number:
    $auth->invoice_num = time();
    
    // Set a Merchant Defined Field:
    $auth->setCustomField("entrance_source", "Search Engine");
    
    // Authorize Only:
    $response  = $auth->authorizeOnly();

    if ($response->approved) {
        $auth_code = $response->transaction_id;
        
        // Now capture:
        $capture = new AuthorizeNetAIM;
        $capture_response = $capture->priorAuthCapture($auth_code);
        
        // Now void:
        $void = new AuthorizeNetAIM;
        $void_response = $void->void($capture_response->transaction_id);
    }
    ?>

###AuthorizeNetARB.php Usage Example:
    <?php
    require_once 'anet_php_sdk/AuthorizeNet.php';
    define("AUTHORIZENET_API_LOGIN_ID", "YOURLOGIN");
    define("AUTHORIZENET_TRANSACTION_KEY", "YOURKEY");
    $subscription                          = new AuthorizeNet_Subscription;
    $subscription->name                    = "PHP Monthly Magazine";
    $subscription->intervalLength          = "1";
    $subscription->intervalUnit            = "months";
    $subscription->startDate               = "2011-03-12";
    $subscription->totalOccurrences        = "12";
    $subscription->amount                  = "12.99");
    $subscription->creditCardCardNumber    = "6011000000000012";
    $subscription->creditCardExpirationDate= "2018-10";
    $subscription->creditCardCardCode      = "123";
    $subscription->billToFirstName         = "Rasmus";
    $subscription->billToLastName          = "Doe";

    // Create the subscription.
    $request = new AuthorizeNetARB;
    $response = $request->createSubscription($subscription);
    $subscription_id = $response->getSubscriptionId();
    ?>

###AuthorizeNetCIM.php Usage Example:
    <?php
    require_once 'anet_php_sdk/AuthorizeNet.php';
    define("AUTHORIZENET_API_LOGIN_ID", "YOURLOGIN");
    define("AUTHORIZENET_TRANSACTION_KEY", "YOURKEY");
    $request = new AuthorizeNetCIM;
    // Create new customer profile
    $customerProfile                    = new AuthorizeNetCustomer;
    $customerProfile->description       = "Description of customer";
    $customerProfile->merchantCustomerId= time();
    $customerProfile->email             = "test@domain.com";
    $response = $request->createCustomerProfile($customerProfile);
    if ($response->isOk()) {
        $customerProfileId = $response->getCustomerProfileId();
    }
    ?>

###AuthorizeNetSIM.php Usage Example:
    <?php
    require_once 'anet_php_sdk/AuthorizeNet.php';
    define("AUTHORIZENET_API_LOGIN_ID", "YOURLOGIN");
    define("AUTHORIZENET_MD5_SETTING", "");
    $message = new AuthorizeNetSIM;
    if ($message->isAuthorizeNet()) {
        $transactionId = $message->transaction_id;
    }
    ?>
    
###AuthorizeNetDPM.php Usage Example:
    <?php // Filename: direct_post.php
    require_once 'anet_php_sdk/AuthorizeNet.php'; // The SDK
    $url = "http://YOUR_DOMAIN.com/direct_post.php";
    $api_login_id = 'YOUR_API_LOGIN_ID';
    $transaction_key = 'YOUR_TRANSACTION_KEY';
    $md5_setting = 'YOUR_MD5_SETTING'; // Your MD5 Setting
    $amount = "5.99";
    AuthorizeNetDPM::directPostDemo($url, $api_login_id, $transaction_key, $amount, $md5_setting);
    ?>

###AuthorizeNetCP.php Usage Example:
    <?php
    require_once 'anet_php_sdk/AuthorizeNet.php';
    define("AUTHORIZENET_API_LOGIN_ID", "YOURLOGIN");
    define("AUTHORIZENET_TRANSACTION_KEY", "YOURKEY");
    define("AUTHORIZENET_MD5_SETTING", "");
    $sale = new AuthorizeNetCP;
    $sale->amount = '59.99';
    $sale->device_type = '4';
    $sale->setTrack1Data('%B4111111111111111^CARDUSER/JOHN^1803101000000000020000831000000?');
    $response = $sale->authorizeAndCapture();
    $trans_id = $response->transaction_id;
    ?>

###AuthorizeNetTD.php Usage Example:
    <?php
    require_once 'anet_php_sdk/AuthorizeNet.php';
    define("AUTHORIZENET_API_LOGIN_ID", "YOURLOGIN");
    define("AUTHORIZENET_TRANSACTION_KEY", "YOURKEY");
    $request = new AuthorizeNetTD;
    $response = $request->getTransactionDetails("12345");
    echo $response->xml->transaction->transactionStatus;
    ?>
    
##Test Credit Card Numbers:
    - Set the expiration date to anytime in the future.
    - American Express Test Card=> 370000000000002
    - Discover Test Card        => 6011000000000012
    - Visa Test Card            => 4007000000027
    - Second Visa Test Card     => 4012888818888
    - JCB                       => 3088000000000017
    - Diners Club/ Carte Blanche=> 38000000000006

##PHPDoc:
  To autogenerate PHPDocs run:
  phpdoc -t phpdocs/ -f AuthorizeNet.php -d lib

