<?php
	$sPageTitle = "Geographic Support";
	require "Include/Header.php";
?>

<div class="Help_Section">
	<div class="Help_Header">How does ChurchInfo know exactly where Families live?</div>
	<table width="100%" class="LightShadedBox">
	<tr><td>
	<P>ChurchInfo stores the latitude and longitude with each Family.  These numbers may
	   be entered into the Family edit page, or looked up based on the address.  In the United States, 
	   this information is found automatically by using the Internet service rpc.geocoder.us.  
	   If you know of a similar service for other countries please let us know!</P>
	</td></tr></table>
</div><p>

<div class="Help_Section">
	<div class="Help_Header">How do I find Families that live close to each other?</div>
	<table width="100%" class="LightShadedBox">
	<tr><td>
	<P>Select Family Geographic Utilities from the People/Families menu, then select a Family from the list.
	Press Show Neighbors and this page will update with the nearest neighbor families listed at the bottom.
	The Maximum number of neighbors and Maximum distance fields are used to limit the number of neighbor
	families displayed.
	</P>
	</td></tr></table>
</div><p>

<div class="Help_Section">
	<div class="Help_Header">How do I see where Families live on a map?</div>
	<table width="100%" class="LightShadedBox">
	<tr><td>
	<P>The easiest way is to select Family Map from the People/Familes menu.  This map is generated
	using the Google mapping service.  For this feature to work, the Google map key must be set specifically
	for your web site URL.  The setting is near the bottom of the General Settings page available from the Admin 
	menu.  The web site to obtain your unique key from Google is: 
	<a href="http://maps.google.com/apis/maps/signup.html"> here</a>.
	</P>
	</td></tr></table>
</div><p>

<div class="Help_Section">
	<div class="Help_Header">Are other types of maps available?</div>
	<table width="100%" class="LightShadedBox">
	<tr><td>
	<P>
	The Family Geographic Utilities page can also make annotation files for the 
	<a href="http://www.gpsvisualizer.com/map">	GPS Visualizer</a> web site
	or the Delorme Street Atlas USA map program.  To make an annotation file select the desired format and
	press Make Data File.
	</P>
	</td></tr></table>
</div><p>



<?php
	require "Include/Footer.php";
?>
