<?php
	$sPageTitle = "Cart";
	require "Include/Header.php";
?>

<div class="Help_Section">
	<div class="Help_Header">What is the Cart?</div>
	<table width="100%" class="LightShadedBox"><tr><td><p>The Cart is a temporary holding space for People records.  You can add People to the Cart, then process these People records all at once, by generating labels or dumping the contents of the cart to a group.</p>
		<p>You may put an unlimited number of People in the Cart.  Putting someone in the Cart does nothing to their record, they are just temporarily assigned to the Cart.  You can put someone in the Cart, then remove then without doing any processing, and their record will remain unchanged.</p>
		<p>The Cart is user- and session-specific.  Every User has his or her own Cart, and that Cart will only last until the User logs off -- Carts do not span sessions.</p>
	</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How can I see what's in my Cart?</div>
	<table width="100%" class="LightShadedBox"><tr><td><p>On the top menu, second row, right side, a real-time counter will tell you how many records you have in the Cart. This counter will go up or down as you add or remove records.</p>
		<p>To see the actual records in your Cart, click on "List Cart Items" (under &quot;Cart&quot;). This will display all records currently in the Cart.</p>
	</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I add a Person to the Cart?</div>
	<table width="100%" class="LightShadedBox"><tr><td><p>There several ways to do this:</p>
		<p><b>To add an individual Person:</b>
		<ol>
		  <li>From the top menu, either click on "View All Persons" (under &quot;People/Families&quot;) and list all persons, or enter a name into the filter box in the top menu and press Enter.
		  <li>When the results of the filter are displayed, there will be a link on the far right of every Person record called "Add to Cart."  Click this link for the desired Person.
		  <li>If this Person does not already exist in the cart, they will be added.
		</ol>
		Alternately, you can view the desired Person record, and within that record will be a link for "Add to Cart."  Clicking this link accomplishes the same thing as the process described above.</p>
		<p><b>To add the results of a report:</b><br>
		Some reports will allow you to dump the results to the Cart, and some won't -- it depends what the report returns.  Since the cart holds People, a report that returned Family records will not allow the results to be placed in the Cart.
		<ol>
		  <li>Run the desired report.
		  <li>If the report is Cart-enabled, at the bottom of the results you will find a button labeled "Add Results to Cart."  Clicking this button will add all the results of that report to the cart.
		</ol>
		<p><b>To add all people assigned to a Group:</b>
		<ol>
		  <li>From the top menu, click "Empty Cart to Group&quot; (under &quot;Cart&quot;).
		  <li>Click on the desired Group or Create a New Group - Don't worry, if you make a new group, you can empty the cart to it as well.
		  <li>If you choose to make a new group, on the New Group page, there is a box for &quot;Empty Cart to this Group?&quot;. It should already be checked, so just let InfoCentral make the move for you.
		</ol>
	</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I remove a person from the Cart?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
			<li>From the top menu, click "List Cart Items" (under &quot;Cart&quot;).
			<li>On the resulting screen, all the People currently in the Cart will be listed, with a "Remove" link to the far right of their name.  Clicking this link will remove the specified Person from the Cart.
		</ol>
		<p>Note: To empty the Cart completely, click the "Empty Cart" link at the bottom of the page.  Do NOT, however, confuse this with "Empty Cart to Group."  "Empty Cart" simply removes all People from the Cart, without moving them anywhere.</p>
	</td></tr></table>
</div>

<?php
	require "Include/Footer.php";
?>