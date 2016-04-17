# ChurchCRM API
ChurchCRM leverages Slim 2.6.2 to provide REST access to the data elements.

## People
* [GET] /api/persons/search/:query
  * Returns a list of the members who's first name or last name matches the :query parameter

* [GET] /api/persons/:id/photo
  * Returns a the correct photo for that person for a person with the :id value

## Families

* [GET] /families/byCheckNumber/:tScanString
  * Returns a family string based on the scan string of an MICR reader containing a routing and account number
* [GET] /families/byEnvelopeNumber/:tEnvelopeNumber
  * Returns a family string based on the the requested envelope number
* [GET] /api/families/search/:query
  * Returns a list of the families who's name matches the :query parameter
* [GET] /api/families/lastedited
  * Returns a the last 10 updated families 

## Groups
*  [POST] /api/groups
  *Creates a new group with groupData in POST Data
  
*  [POST] /api/groups/:groupID/removeuser/:userID
  * Removes the user with ID :userID from group with ID :groupID
  
*  [POST] /api/groups/:groupID/adduser/:userID
  * Adds the user with ID :userID to the group with ID :groupID
  
*  [DELETE] /api/groups/:groupID
  * Deletes the group with ID :groupID
  
*  [GET] /api/groups/:groupID
  * Returns a JSON objecte representing the group with ID :groupID
  
*  [POST] /api/groups/:groupID/roles/:roleID
  * Alters the role with ID :roleID for group with ID :groupID/adduser/
  * Requires JSON with either "groupRoleName", or "groupRoleOrder" properties set
  
*  [DELETE] /api/groups/:groupID/roles/:roleID
  *  Deletes the specified roleID in the group

*  [POST]  /api/groups/:groupID/defaultRole
  *  Sets the default role fo the group with ID :groupID 
  *  Requres JSON in the POST body with the "roleID"  property set
  
*  [POST] /api/groups/:groupID/roles/
  * Creates a new group role for group with ID :groupID
  * requires JSON in the POST body with roleName set to the new role's name
  
*  [POST] /api/groups/:groupID/userRole/:userID 
  *  Sets the role of a user in a group
  *  requires JSON in the POST body with roleID set to the ID of the user's role in the specified group
*  [POST]  /api/:groupID/setGroupSpecificPropertyStatus
  *  requires JSON property GroupSpecificPropertyStatus either true or false
  

  
## Deposits
*  [GET] /
  * Returns all deposits

*  [POST] /
  *  Creates a new deposit
  *  Requires JSON body with properties: depositType, depositComment, depositDate
  
*  [GET] /:id
  * Returns the deposit with the selected ID

*  [POST]  /:id
  *  Updates the deposit
  *  requires JSON body with properties: depositType, depositComment, depositDate, depositClosed

*  [GET] /:id/ofx
  *  Returns an OFX file representing the requested deposit
  
*  [GET] /:id/pdf
  *  Returns a PDF file representing the requested deposit
  
*  [GET]  /:id/csv
  *  Returns a CSV file representing the requested deposit
  
*  [DELETE] /:id
  * Deletes the selected deposit
  
*  [GET] /:id/payments
  * Returns all payments associated with the supplied deposit ID

## Payments
* [GET] /
  * Returns all payments
  
* [POST] / 
  * Posts a new payment.  Validates the input

* [GET] /:id
  * Gets the specified payment by ID
  
* [GET] /byFamily/:familyID(/:fyid)
  * Gets all payments be family, and optionally by the fiscal year ID
  
* [DELETE] /:groupKey
  * Deleted the payment with the specified GroupKey 

## Search
*  [GET]  /api/search/:query
  *  a search query.  Returns all instances of Persons, Families, Groups, Deposits, Checks, Payments that match the search query
 

## Events
No API Calls yet for Events

## Utilities
### Seed Data (/data/seed)
* [POST] /data/seed/families
  * Expects: A JSON element containing one element: families of type (int)
  * Actions: Generates the supplied number of families in the ChurchCRM database using the randomuser.me web service.
  * Returns: A JSON element containing statistics about the families added, as well as the raw data from the  randomuser.me data query.
* [POST] /data/seed/sundaySchoolClasses (Not yet implemented)
  * Expects:
  * Actions:
  * Returns:
* [POST] /data/seed/deposits (Not yet implemented)
  * Expects:
  * Actions:
  * Returns:
* [POST] /data/seed/events (Not yet implemented)
  * Expects:
  * Actions:
  * Returns:
* [POST] /data/seed/fundraisers (Not yet implemented)
  * Expects:
  * Actions:
  * Returns: