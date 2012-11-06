<?php
	$sPageTitle = "Fundraiser";
	require "Include/Header.php";
?>

<div class="Help_Section">
	<div class="Help_Header">What is the Fundraiser feature for?</div>
	<table width="100%" class="LightShadedBox"><tr>
	<td><p>Fundraiser automation is used for events where members are buying and selling
	items and/or services to benefit the church.  One example is a goods and services auction, 
	where members donate items and services to be auctioned off.  This feature is designed
	for events where most of the buyers and sellers are in the database.</p>
	</tr></table>
</div>

<div class="Help_Section">
	<div class="Help_Header">How is a fundraiser created?</div>
	<table width="100%" class="LightShadedBox"><tr>
	<td><p>Select Fundraiser->Create New Fundraiser.  Enter a date, title and description and press Save.</p>
	</tr></table>
</div>

<div class="Help_Section">
	<div class="Help_Header">How are donated items entered into the fundraiser?</div>
	<table width="100%" class="LightShadedBox"><tr>
	<td><p>Once the fundraiser has been saved more buttons appear across the top.  Press Add Donated Item
	to enter a new item.  The fields are:
      <ul>
        <li><strong>Item:</strong>This Item identifier will be used to sort the items so you can easily rearrange them.</li>
        <li><strong>Multiple items: Sell to everyone:</strong>If this is enabled many copies of this item may be sold.
        There will be a count on the buyer's page and the buyer will be charged for whatever count is entered.</li>
        <li><strong>Donor:</strong>The doner is a person in the database.</li>
        <li><strong>Title:</strong>This is a very short description of the item.</li>
        <li><strong>Estimated Price:</strong>This is an estimated value for the item, for reference.</li>
        <li><strong>Material Value:</strong>The material value is the donation value, not including labor.</li>
        <li><strong>Estimated Price:</strong>The minimum price is for reference, in case the donor does not want it to sell too low.</li>
        <li><strong>Description:</strong>A longer description, used in the catalog and bid sheet.</li>
        <li><strong>Buyer:</strong>The person who purchased the item.  The buyers are registered before they can buy things (see below).
        This field and the Final Price are filled in once the purchase is finalized.</li>
        <li><strong>Final Price: The price paid for the item.  For "Sell to everyone" items this price will apply to all purchases.</li>
      </ul><p></td>
	</tr></table>
</div>

<div class="Help_Section">
	<div class="Help_Header">Why and how are buyers registered?</div>
	<table width="100%" class="LightShadedBox"><tr>
	<td><p>Buyers are registered so they can purchase multiple items and then check out at the end and pay for everything.
	To enter buyers, select Fundraiser->View Buyers.  Press Add Buyer to add a buyer.  The buyer numbers increment automatically,
	or you can type them in (perhaps to match a bidding paddle number).  The Buyer is a person in the database.  The Buyer must be a 
	member of a family in the database.</p>
	</td>
	</tr></table>
</div>

<div class="Help_Section">
	<div class="Help_Header">How is a single purchase recorded?</div>
	<table width="100%" class="LightShadedBox"><tr>
	<td><p>Select Fundraiser->Edit Fundraiser to see the list of items.  Click the link to the left for the item to bring
	up the donated item editor page.  Select the buyer and enter the price on the right side, then press Save.</p>
	</td>
	</tr></table>
</div>

<div class="Help_Section">
	<div class="Help_Header">Is there a way to enter lots of purchases quickly?</div>
	<table width="100%" class="LightShadedBox"><tr>
	<td><p>Select Fundraiser->Edit Fundraiser and then press Batch Winner Entry (upper-right).  This page
	allows ten items to be entered quickly.  For each item, select the Item and Winner and enter the price.
	Press the Enter Winners button to enter all the items on the page at once.</p>
	</td>
	</tr></table>
</div>

<div class="Help_Section">
	<div class="Help_Header">How are the multiple purchase items recorded?</div>
	<table width="100%" class="LightShadedBox"><tr>
	<td><p>Select Fundraiser->View Buyers and then click the link for a buyer.  There is a place on this
	page to enter the quantity for each of the "Sell to Everyone" items.</p>
	</td>
	</tr></table>
</div>

<div class="Help_Section">
	<div class="Help_Header">How does someone check out and pay?</div>
	<table width="100%" class="LightShadedBox"><tr>
	<td><p>Select Fundraiser->View Buyers and then click the link for a buyer.  Check that the "Sell to
	Everyone" quantities are correct, then press Generate Statement.  This will create a PDF statement
	showing both donations and purchases for this buyer.  The total of purchases is shown so that is the
	amount to be paid at check-out.  The statement may be printed and given to the buyer.  There is a 
	payment stub portion at the bottom to help record the payment.</p>
	</td>
	</tr></table>
</div>

<div class="Help_Section">
	<div class="Help_Header">What is someone donates but does not attend?  How can a statement be prepared
	to show the donations?</div>
	<table width="100%" class="LightShadedBox"><tr>
	<td><p>Once the fundraiser is over and all of the donations and purchases have been entered select the
	menu option Fundraiser->Add Doners to Buyer List.  This will create a buyer record for anyone who
	donated items but was not already listed as a buyer.  Once these buyer records are created they can
	be selected and their statements may be generated.  These statements may be helpful to the doners
	for tax purposes.</p>
	</td>
	</tr></table>
</div>

<?php
	require "Include/Footer.php";
?>
