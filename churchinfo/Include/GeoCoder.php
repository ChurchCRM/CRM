<?php
/*******************************************************************************
*
*  filename    : /Include/GeoCoder.php
*  website     : http://www.churchdb.org
*
*  Contributors:
*  2006-07 Ed Davis
*
*
*  Copyright Contributors
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/

require('GoogleMapAPI/GoogleMapAPI.class.php');

$googleMapObj = new GoogleMapAPI('map');
$googleMapObj->setAPIKey($sGoogleMapKey);

$bHaveXML = FALSE;
$pathArray = explode( PATH_SEPARATOR, get_include_path() );
foreach ($pathArray as $onePath) {
	$fullpath = $onePath . DIRECTORY_SEPARATOR . $sXML_RPC_PATH;
	if (file_exists($fullpath) && is_readable($fullpath)) {
		require_once ("$sXML_RPC_PATH");
		$bHaveXML = TRUE;
	}
}

if ($bHaveXML == 0) { // Maybe the user entered absolute path, let's check
	if (file_exists($sXML_RPC_PATH) && is_readable($sXML_RPC_PATH)) {
		require_once ("$sXML_RPC_PATH");
		$bHaveXML = TRUE;
	}
}

// Function takes latitude and longitude
// of two places as input and returns the
// distance in miles.
function LatLonDistance($lat1, $lon1, $lat2, $lon2)
{
	// Formula for calculating radians between
	// latitude and longitude pairs.

	// Uses the Spherical Law of Cosines to find great circle distance.
	// Length of arc on surface of sphere

	// convert to radians to work with trig functions
	$lat1 = deg2rad($lat1);		$lon1 = deg2rad($lon1);
	$lat2 = deg2rad($lat2);		$lon2 = deg2rad($lon2);

	// determine angle between between points in radians
	$radians  = acos(sin($lat1)*sin($lat2) + cos($lat1)*cos($lat2)*cos($lon1-$lon2));

	// mean radius of Earth in kilometers
	$radius = 6371.0;

	// distance in kilometers is $radians times $radius
	$kilometers  = $radians * $radius;

	// convert to miles
	$miles = 0.6213712 * $kilometers;

	// Return distance to three figures
	if ($miles < 10.0) {
		$distance = sprintf("%0.2f",$miles);
	} elseif ($miles < 100.0) {
		$distance = sprintf("%0.1f",$miles);
	} else {
		$distance = sprintf("%0.0f",$miles);		
	}

	return $distance ;
}


function LatLonBearing($lat1, $lon1, $lat2, $lon2)
{
	// Formula for determining the bearing from ($lat1,$lon1) to ($lat2,$lon2)

	// This is the initial bearing which if followed in a straight line will take
	// you from the start point to the end point; in general, the bearing you are 
	// following will have varied by the time you get to the end point (if you were 
	// to go from say 35°N,45°E (Baghdad) to 35°N,135°E (Osaka), you would start on 
	// a bearing of 60° and end up on a bearing of 120°!).

	// If you are standing at ($lat1,$lon1) and pointing the shortest distance to
	// ($lat2,$lon2) this function tells you which direction you are pointing.  
	// Returns one of the following 16 directions.
	// N, NNE, NE, ENE, E, ESE, SE, SSE, S, SSW, SW, WSW, W, WNW, NW, NNW

	// convert to radians to work with trig functions
	$lat1 = deg2rad($lat1);		$lon1 = deg2rad($lon1);
	$lat2 = deg2rad($lat2);		$lon2 = deg2rad($lon2);

	$y = sin($lon2-$lon1)*cos($lat2);
	$x = cos($lat1)*sin($lat2) - sin($lat1)*cos($lat2)*cos($lon2-$lon1);
	$bearing = atan2($y, $x);

	// Covert from radians to degrees
	$bearing = sprintf("%5.1f",rad2deg($bearing));

	// Convert to directions
	// -180=S   -135=SW   -90=W   -45=NW   0=N   45=NE   90=E   135=SE   180=S
	if ($bearing < -191.25) {
		$direction = "---";
	} elseif ($bearing < -168.75){
		$direction = "S";
	} elseif ($bearing < -146.25){
		$direction = "SSW";
	} elseif ($bearing < -123.75) {
		$direction = "SW";
	} elseif ($bearing < -101.25) {
		$direction = "WSW";
	} elseif ($bearing < -78.75) {
		$direction = "W";
	} elseif ($bearing < -56.25){
		$direction = "WNW";
	} elseif ($bearing < -33.75) {
		$direction = "NW";
	} elseif ($bearing < -11.25) {
		$direction = "NNW";
	} elseif ($bearing < 11.25){
		$direction = "N";
	} elseif ($bearing < 33.75) {
		$direction = "NNE";
	} elseif ($bearing < 56.25) {
		$direction = "NE";
	} elseif ($bearing < 78.75) {
		$direction = "ENE";
	} elseif ($bearing < 101.25) {
		$direction = "E";
	} elseif ($bearing < 123.75) {
		$direction = "ESE";
	} elseif ($bearing < 146.25) {
		$direction = "SE";
	} elseif ($bearing < 168.75) {
		$direction = "SSE";
	} elseif ($bearing < 191.25) {
		$direction = "S";
	} else {
		$direction = "+++";
	}

//    $direction  = $bearing . " " . $direction;

	return $direction ;
}

class AddressLatLon {

	var $street;
	var $city;
	var $state;
	var $zip;

	var $lat;
	var $lon;

	var $client;

	var $errMsg;

	function GetError () { return $this->errMsg; }
	function GetLat () { return $this->lat; }
	function GetLon () { return $this->lon; }

	function AddressLatLon () {
		global $sGeocoderID, $sGeocoderPW, $bHaveXML;
		if (! $bHaveXML)
			return;
		if ($sGeocoderID) { // Use credentials if available for unthrottled access to the geocoder server
			$this->client = new XML_RPC_Client('/member/service/xmlrpc', 'rpc.geocoder.us');
			$this->client->SetCredentials ($sGeocoderID, $sGeocoderPW);
		} else {
			$this->client = new XML_RPC_Client('/service/xmlrpc', 'rpc.geocoder.us');
		}
	}

	function SetAddress ($newStreet, $newCity, $newState, $newZip) {
		$this->street = $newStreet;
		$this->city = $newCity;
		$this->state = $newState;
		$this->zip = $newZip;
	}

	function Lookup () {
		global $bHaveXML;
		global $bUseGoogleGeocode;
		global $googleMapObj;

		$address = $this->street . "," . $this->city . "," . $this->state . "," . $this->zip;

		if ($bUseGoogleGeocode) {
			$geocode = $googleMapObj->geoGetCoords($address);
        
			$this->lat = $geocode['lat'];
			$this->lon = $geocode['lon'];
		} else {
			if (! $bHaveXML)
				return (-4);

			$params = array(new XML_RPC_Value($address, 'string'));
			$message = new XML_RPC_Message('geocode', $params);
			$response = $this->client->send($message);

			if (!$response) {
				$errMsg = 'Communication error: ' . $client->errstr;
				return (-1);
			}

			if (!$response->faultCode()) {
				$value = $response->value();
				$address_data = XML_RPC_decode($value);
				$data0 = $address_data[0];
				$this->lat = $data0["lat"];
				$this->lon = $data0["long"];

				if ($this->lat == "") {
					$this->errMsg = "Unable to find " . $data0["number"] . " " . $data0["street"] . ", " . $data0["city"] . ", " . $data0["state"] . " " . $data0["zip"];
					return (-3);
				}
				return (0);
			} else {
				/*
				 * Display problems that have been gracefully cought and
				 * reported by the xmlrpc.php script
				 */
				$this->errMsg = "Fault Code: " . $response->faultCode() . ",";
				$this->errMsg .= "Fault Reason: " . $response->faultString() . "\n";
				return (-2);
			}
		}
	}
}

?>
