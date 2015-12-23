# Geographic Utilities

Included in ChurchCRM is a geographic mapping feature that lets you map groups of [people](person.md) or [families](families.md).

## How does ChurchCRM know exactly where Families live?

ChurchCRM stores the latitude and longitude with each Family. These numbers may be entered into the Family edit page, or looked up based on the address. In the United States, this information is found automatically by using the Internet service [rpc.geocoder.us](http://rpc.geocoder.us). If you know of a similar service for other countries please let us know!

## How do I find Families that live close to each other?

Select _"Family Geographic Utilities"_ from the _"Members"_ menu, then select a Family from the list. Press _"Show Neighbors"_ and this page will update with the nearest neighbor families listed at the bottom. The Maximum number of neighbors and Maximum distance fields are used to limit the number of neighbor families displayed.

## How do I see where Families live on a map?

The easiest way is to select _"Family Map"_ from the _"Members"_ menu. This map is generated using the Google mapping service. For this feature to work, the Google map key must be set specifically for your web site URL. The setting is near the bottom of the General Settings page available from the Admin menu. You can obtain a unique Google Maps API key on the [Google Developers page](https://developers.google.com/maps/documentation/javascript/?hl=en).

## Are other types of maps available?

The _"Family Geographic Utilities"_ page can also make annotation files for the GPS Visualizer web site or the Delorme Street Atlas USA map program. To make an annotation file select the desired format and press _"Make Data File"_.
