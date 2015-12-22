# ChurchCRM API
ChurchCRM leverages Slim 2.6.2 to provide REST access to the data elements.

## People
* [GET] /members/list/search/:query
  * Returns a list of the members who's first name or last name matches the :query parameter

## Families
* [GET] /families/byCheckNumber/:tScanString
  * Returns a family string based on the scan string of an MICR reader containing a routing and account number
* [GET] /families/byEnvelopeNumber/:tEnvelopeNumber
  * Returns a family string based on the the requested envelope number
  
## Deposits
* [GET] /
  * Returns all deposits
* [GET] /:id
  * Returns the deposit with the selected ID
* [GET] /:id/payments
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

 

## Events
No API Calls yet for Events

## 