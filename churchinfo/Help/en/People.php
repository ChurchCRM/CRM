<?php
	$sPageTitle = "People";
	require "Include/Header.php";
?>

<div class="Help_Section">
	<div class="Help_Header">What is a Person?</div>
	<table width="100%" class="LightShadedBox"><tr><td>Not surprisingly, a Person record represents a single individual.  Person records can be grouped together into Families, can belong to Groups, can have Properties, and can be made Users of InfoCentral.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I find a specific Person?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
		<li>On left menu, find the input box just below the "People" heading
		<li>Enter a search string in this box, and press Enter
		<li>The system will return all Person records containing that search string in the first or last name.
		<li>Clicking on the Person's name will reveal the Person View which lists all information about that Person, including any Assigned Properties, Assigned Groups, and Notes.
	</ol>
	<p>This is a wild-card search, meaning the system is looking for that sequence of characters, no matter where in the first or last name they may appear.  For example, searching for "Ian" will return all People records with the first name of "Ian" or Brian" (or anything else containing the characters "ian" in that order).</p>
	<p>Leaving the search box empty and pressing Enter will result in the display of ALL Person records in the system.  This is not recommended as it will consume system resources and  produce a long delay before the page is displayed.</p></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">Why is some of the information on the Person View in red text?</div>
	<table width="100%" class="LightShadedBox"><tr><td><p>This is information inherited from an associated Family record.  People can grouped into Families.  People assigned to the same Family will likely share much of the same information -- the same Address, the same Phone, the same Email, etc.  In these cases, this information only needs to be entered for the Family and all People assigned to that Family will "inherit" that information, unless the Person record in question has its own information.</p>
	<p>For example, the Smith family has four members: John, Mary, Billy, and Sally.  None of the four Person records have an address, phone, or email address listed, but this information is present in the Smith Family record.  When Sally Smith's Person View is displayed, the system displays the address from the Family record.  It uses red text to indicate that this information has been inherited.  Say that Sally goes to college, and an address for her dorm room is entered in her Person record.  Since she now has her own address, that address will display in black text on her Person View.</p>
	<p>This makes it easy to change common information for all members of a Family.  For a Family of 10 Person records, changing 10 addresses every time they move invites an error to creep in somewhere. By inheriting the Family information, the address needs to be changed in only one place.</p></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I add a new Person?</div>
	<table width="100%" class="LightShadedBox"><tr><td><p>There are two ways to add a new Person:</p>
	<ol>
		<li>From the left menu, click on "Add a New Person"
		<li>Complete the form
		<li>Press "Save" or "Save and Add."  The latter will add the person and return you to an empty form to add another person, which is handy for large amounts of data entry.
	</ol>
	<p>However, to enter a new Family and several Person records at once which you plan to assign to that Family, use the Family Editor.</p></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">What is a Classification?</div>
	<table width="100%" class="LightShadedBox"><tr><td>This defines the Person's role within the church.  Common Classifications are Member, Guest, Regular Attender, Non-Attender, etc.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I enter a person's age?</div>
	<table width="100%" class="LightShadedBox"><tr><td><p>You don't.  Infocentral automatically calculates age based on the birth date given.</p>
	<p>Age will be calculated as best it can with the information given.  At minimum a Birth Year must be entered.
	Even if you don't know a person's birth year, you can always estimate until that information is available.</p></td></tr></table>
</div>

<div class="Help_Section">
  <p><div class="Help_Header">How do I delete a Person?</div>
  <table width="100%" class="LightShadedBox"><tr><td><p>Leaving old people in the database doesn't hurt anything and may help with historical record keeping. But if you have to...</p>
	<ol>
		<li>Filter for the desired person, and bring up their Person View.
		<li>Select "Delete this Record" (if this link doesn't appear, then you don't have permissions to delete records)
		<li>Confirm the deletion
	</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">What are Custom Person Fields?</div>
	<table width="100%" class="LightShadedBox"><tr><td>Custom Person Fields is a powerful feature that allows you to add any fields that you need to use that do not come built-in with InfoCentral. This feature allows you to, for example, add a Mentor to a person, or add an additional date (such as confirmation). The possibilities are endless.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I use Custom Person Fields?</div>
	<table width="100%" class="LightShadedBox"><tr><td>See the <a href="Help.php?page=Custom">Custom Fields</a> help topic.</td></tr></table>
</div>


<div class="Help_Section">
	<p><div class="Help_Header">How do I put a Person in the Cart?</div>
	<table width="100%" class="LightShadedBox"><tr><td>See the <a href="Help.php?page=Cart">Cart</a> help topic.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I assign a Person to a Group?</div>
	<table width="100%" class="LightShadedBox"><tr><td>See the <a href="Help.php?page=Groups">Groups</a> help topic.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I assign a Property to a Person?</div>
	<table width="100%" class="LightShadedBox"><tr><td>See the <a href="Help.php?page=Properties">Properties</a> help topic.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I add a Note to a Person?</div>
	<table width="100%" class="LightShadedBox"><tr><td>See the <a href="Help.php?page=Notes">Notes</a> help topic.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I track Finances of a Person?</div>
	<table width="100%" class="LightShadedBox"><tr><td>See the <a href="Help.php?page=Finances">Finances</a> help topic.</td></tr></table>
</div>

<?php
	require "Include/Footer.php";
?>
