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