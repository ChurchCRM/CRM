# ChurchCRM API
ChurchCRM leverages Slim 2.6.2 to provide REST access to the data elements.

## People
* [GET] /api/persons/search/:query
  * Returns a list of the members who's first name or last name matches the :query parameter

* [GET] /api/persons/:id/photo
  * Returns a the correct photo for that person for a person with the :id value

## Families
* [GET] /api/families/search/:query
  * Returns a list of the families who's name matches the :query parameter
  
* [GET] /api/families/lastedited
  * Returns a the last 10 updated families 

## Deposits
No API Calls yet for Deposits

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