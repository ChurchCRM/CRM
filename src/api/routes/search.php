<?php
/*******************************************************************************
*
*  filename    : api/routes/search.php
*  last change : 2017/10/29 Philippe Logel
*  description : Search terms like : Firstname, Lastname, phone, address, 
*								 groups, families, etc...
*
******************************************************************************/
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
              _or()->filterByWorkEmail($searchLikeString, Criteria::LIKE)->
							_or()->filterByHomePhone($searchLikeString, Criteria::LIKE)->
							_or()->filterByCellPhone($searchLikeString, Criteria::LIKE)->
							_or()->filterByWorkPhone($searchLikeString, Criteria::LIKE)->
						limit(SystemConfig::getValue("bSearchIncludePersonsMax"))->find();
			
		
					if (!empty($people))
					{
						$data = [];
						$id++;
						
						foreach ($people as $person) {
							$elt = ['id'=>$id++,
									'text'=>$person->getFullName(),
									'uri'=>$person->getViewURI()];
					
							array_push($data, $elt);
						}        	
			
						if (!empty($data))
						{
							$dataPerson = ['children' => $data,
							'id' => 0,
							'text' => gettext('Persons')];
					
							$resultsArray = array ($dataPerson);
						}
					}
        } catch (Exception $e) {
            $this->Logger->warn($e->getMessage());
        }
    }
    
    //Person Search by address
    if (SystemConfig::getBooleanValue("bSearchIncludeAddresses")) {
        try {
        	$searchLikeString = '%'.$query.'%';
					$addresses = FamilyQuery::create()->
						filterByCity($searchLikeString, Criteria::LIKE)->
						_or()->filterByAddress1($searchLikeString, Criteria::LIKE)->
						_or()->filterByAddress2($searchLikeString, Criteria::LIKE)->
						_or()->filterByZip($searchLikeString, Criteria::LIKE)->
						_or()->filterByState($searchLikeString, Criteria::LIKE)->
						limit(SystemConfig::getValue("bSearchIncludeAddressesMax"))->find();
			
					if (!empty($addresses))
					{					
						$data = [];
						$id++;
					
						foreach ($addresses as $address) {
							$elt = ['id'=>$id++,
									'text'=>$address->getFamilyString(SystemConfig::getBooleanValue("bSearchIncludeFamilyHOH")),
									'uri'=>SystemURLs::getRootPath().$address->getViewURI()
							];
					
							array_push($data, $elt);
						}        	
			
						if (!empty($data))
						{
							$dataAddress = ['children' => $data,
							'id' => 1,
							'text' => gettext('Address')];
					
							array_push($resultsArray,$dataAddress);
						}
					}
        } catch (Exception $e) {
            $this->Logger->warn($e->getMessage());
        }
    }
    
    
    //family search
    if (SystemConfig::getBooleanValue("bSearchIncludeFamilies")) {
        try {
          $results = [];
          $families = FamilyQuery::create()->
          		filterByName("%$query%", Criteria::LIKE)->
              _or()->filterByHomePhone($searchLikeString, Criteria::LIKE)->
              _or()->filterByEmail($searchLikeString, Criteria::LIKE) ->
							_or()->filterByCellPhone($searchLikeString, Criteria::LIKE)->
							_or()->filterByWorkPhone($searchLikeString, Criteria::LIKE)->
              limit(SystemConfig::getValue("bSearchIncludeFamiliesMax"))->find();

					if (!empty($families))
					{
						$data = []; 
						$id++;					
					
						foreach ($families as $family)
						{          					
								$searchArray=[
								"id" => $id++,
								"text" => $family->getFamilyString(SystemConfig::getBooleanValue("bSearchIncludeFamilyHOH")),
								"uri" => SystemURLs::getRootPath().$family->getViewURI()
							];
					
							array_push($data,$searchArray);
						}
						
						if (!empty($data))
						{
							$dataFamilies = ['children' => $data,
								'id' => 2,
								'text' => gettext('Families')];
			
							array_push($resultsArray, $dataFamilies);
						}
					}
        } catch (Exception $e) {
            $this->Logger->warn($e->getMessage());
        }
    }
    
    // Group Search
    if (SystemConfig::getBooleanValue("bSearchIncludeGroups")) {
        try {
            $groups = GroupQuery::create()
                ->filterByName("%$query%", Criteria::LIKE)
                ->limit(SystemConfig::getValue("bSearchIncludeGroupsMax"))
                ->withColumn('grp_Name', 'displayName')
                ->withColumn('CONCAT("' . SystemURLs::getRootPath() . '/GroupView.php?GroupID=",Group.Id)', 'uri')
                ->select(['displayName', 'uri'])
                ->find();
            
            
            if (!empty($groups))
						{ 
	            $data = [];   
							$id++;
							
							foreach ($groups as $group) {
								$elt = ['id'=>$id++,
									'text'=>$group['displayName'],
									'uri'=>$group['uri']];
					
								array_push($data, $elt);
							}
			
							if (!empty($data))
							{
								$dataGroup = ['children' => $data,
									'id' => 3,
									'text' => gettext('Groups')];
	
								array_push($resultsArray, $dataGroup);
							}
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
                    ->limit(SystemConfig::getValue("bSearchIncludeDepositsMax"));
              
              if (!empty($Deposits))
							{      
								$data = [];               
								$id++;				
							
								foreach ($Deposits as $Deposit) {				
									$elt = ['id'=>$id++,
										'text'=>$Deposit['displayName'],
										'uri'=>$Deposit['uri']];
				
									array_push($data, $elt);
								}
				
								if (!empty($data))
								{
									$dataDeposit = ['children' => $data,
									'id' => 4,
									'text' => gettext('Deposits')];

									array_push($resultsArray, $dataDeposit);
								}
							}
						} catch (Exception $e) {
								$this->Logger->warn($e->getMessage());
						}
					}

					//Search Payments
					if (SystemConfig::getBooleanValue("bSearchIncludePayments")) 
					{
						try {
							$Payments = $this->FinancialService->searchPayments($query);
									
							if (!empty($Payments))
							{  
								$data = [];   
								$id++;
				
								foreach ($Payments as $Payment) {
									$elt = ['id'=>$id++,
										'text'=>$Payment['displayName'],
										'uri'=>$Payment['uri']];
				
									array_push($data, $elt);
								}
				
								if (!empty($data))
								{
									$dataPayements = ['children' => $data,
									'id' => 5,
									'text' => gettext('Payments')];

									array_push($resultsArray, $dataPayements);
								}
							}
				
            } catch (Exception $e) {
                $this->Logger->warn($e->getMessage());
            }
        }
    }
    
    return $response->withJson(array_filter($resultsArray));
});
