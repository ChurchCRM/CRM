<?php
use ChurchCRM\DepositQuery;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\FamilyQuery;
use ChurchCRM\GroupQuery;
use ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;

// Routes search

// search for a string in Persons, families, groups, Financial Deposits and Payments
$app->get('/search/{query}', function ($request, $response, $args) {
    $query = $args['query'];
    $resultsArray = [];
    
    $id = 1;
    
    //Person Search
    if (SystemConfig::getBooleanValue("bSearchIncludePersons")) {
        try {
        	$searchLikeString = '%'.$query.'%';
			$people = PersonQuery::create()->
				filterByFirstName($searchLikeString, Criteria::LIKE)->
					_or()->filterByLastName($searchLikeString, Criteria::LIKE)->
					_or()->filterByEmail($searchLikeString, Criteria::LIKE)->
				limit(15)->find();
			
			$data = [];
		
			$id = 2;
		
			foreach ($people as $person) {
				$elt = ['id'=>$id++,
						'text'=>$person->getFirstName()." ".$person->getLastName(),
						'uri'=>$person->getViewURI()];
					
				array_push($data, $elt);
			}        	
        	
        	$c = count($data);
        	
        	if ($c >0)
        	{
				$dataPerson = ['children' => $data,
				'id' => 0,
				'text' => gettext('Persons')];
					
				$resultsArray = array ($dataPerson);

				$id+=count($arr);			
			}
        } catch (Exception $e) {
            $this->Logger->warn($e->getMessage());
        }
    }
    
    
     //family search
    if (SystemConfig::getBooleanValue("bSearchIncludeFamilies")) {
        try {
          $results = [];
          $families = FamilyQuery::create()
              ->filterByName("%$query%", Propel\Runtime\ActiveQuery\Criteria::LIKE)
              ->limit(15)
              ->find();

		  if (count($families))
		  {
		  	  $id++;
		  	  
		  	  $data = []; 
		  	  
			  foreach ($families as $family)
			  {          					
  				  $searchArray=[
					  "id" => $id++,
					  "text" => $family->getFamilyString(SystemConfig::getBooleanValue("bSearchIncludeFamilyHOH")),
					  "uri" => SystemURLs::getRootPath() . '/FamilyView.php?FamilyID=' . $family->getId()
				  ];
				  
				array_push($data,$searchArray);
			  }
		  
			  $dataFamilies = ['children' => $data,
				'id' => 1,
				'text' => gettext('families')];
		  
			  array_push($resultsArray, $dataFamilies);
			}
        } catch (Exception $e) {
            $this->Logger->warn($e->getMessage());
        }
    }
    
    if (SystemConfig::getBooleanValue("bSearchIncludeGroups")) {
        try {
            $groups = GroupQuery::create()
                ->filterByName("%$query%", Propel\Runtime\ActiveQuery\Criteria::LIKE)
                ->limit(15)
                ->withColumn('grp_Name', 'displayName')
                ->withColumn('CONCAT("' . SystemURLs::getRootPath() . '/GroupView.php?GroupID=",Group.Id)', 'uri')
                ->select(['displayName', 'uri'])
                ->find();
            
            $data = [];   
            
            if (count($groups))
		  	{ 
            	$id++;
            	
            	foreach ($groups as $group) {
					$elt = ['id'=>$id++,
						'text'=>$group['displayName'],
						'uri'=>$group['uri']];
					
					array_push($data, $elt);
				}
			
				$dataGroup = ['children' => $data,
				'id' => 2,
				'text' => gettext('Groups')];
	
				array_push($resultsArray, $dataGroup);
			}
        } catch (Exception $e) {
            $this->Logger->warn($e->getMessage());
        }
    }
    
    
    if ($_SESSION['bFinance']) 
    {
        //Deposits Search
        if (SystemConfig::getBooleanValue("bSearchIncludeDeposits")) 
        {
            try {
                /*$q = DepositQuery::create();
                $q->filterByComment("%$query%", Criteria::LIKE)
                    ->_or()
                    ->filterById($query)
                    ->_or()
                    ->usePledgeQuery()
                    ->filterByCheckno("%$query%", Criteria::LIKE)
                    ->endUse()
                    ->withColumn('CONCAT("#",Deposit.Id," ",Deposit.Comment)', 'displayName')
                    ->withColumn('CONCAT("' . SystemURLs::getRootPath() . '/DepositSlipEditor.php?DepositSlipID=",Deposit.Id)', 'uri')
                    ->limit(5);
                array_push($resultsArray, $q->find()->toJSON());*/
                $Deposits = DepositQuery::create();
                $Deposits->filterByComment("%$query%", Criteria::LIKE)
                    ->_or()
                    ->filterById($query)
                    ->_or()
                    ->usePledgeQuery()
                    ->filterByCheckno("%$query%", Criteria::LIKE)
                    ->endUse()
                    ->withColumn('CONCAT("#",Deposit.Id," ",Deposit.Comment)', 'displayName')
                    ->withColumn('CONCAT("' . SystemURLs::getRootPath() . '/DepositSlipEditor.php?DepositSlipID=",Deposit.Id)', 'uri')
                    ->limit(5);
                    
                $data = [];   
            
				$id++;
				
				$realCount = 0;			
				foreach ($Deposits as $Deposit) {
				
					$elt = ['id'=>$id++,
						'text'=>$Deposit['displayName'],
						'uri'=>$Deposit['uri']];
				
					array_push($data, $elt);
					
					$realCount++;
				}
				
				if ($realCount>0)
				{
					$dataDeposit = ['children' => $data,
					'id' => 3,
					'text' => gettext('Deposits')];

					array_push($resultsArray, $dataDeposit);
				}
            } catch (Exception $e) {
                $this->Logger->warn($e->getMessage());
            }
        }

        //Search Payments
        if (SystemConfig::getBooleanValue("bSearchIncludePayments")) 
        {
            try {
            	//array_push($resultsArray, $this->FinancialService->getPaymentJSON($this->FinancialService->searchPayments($query)));
            	$Payments = $this->FinancialService->searchPayments($query);
                    
                $data = [];   
            
				$id++;
				
				$realCount = 0;			
				foreach ($Payments as $Payment) {
				
					$elt = ['id'=>$id++,
						'text'=>$Payment['displayName'],
						'uri'=>$Payment['uri']];
				
					array_push($data, $elt);
					
					$realCount++;
				}
				
				if ($realCount>0)
				{
					$dataPayements = ['children' => $data,
					'id' => 4,
					'text' => gettext('Payments')];

					array_push($resultsArray, $dataPayements);
				}
				
            } catch (Exception $e) {
                $this->Logger->warn($e->getMessage());
            }
        }
    }

    
    return $response->withJson(array_filter($resultsArray));
});
