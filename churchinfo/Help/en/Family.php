<?php
	$sPageTitle = "Families";
	require "Include/Header.php";
?>

<div class="Help_Section">
	<div class="Help_Header">What is a Family?</div>
	<table width="100%" class="LightShadedBox"><tr><td><p>A Family is a group of Person records.  Person records are grouped into Families for two reasons:</p>
	<ol>
		<li>To represent the social constructs of the Family within the church
		<li>To share information common to all members of the family -- things like address, phone number, email address, etc.
	</ol>
	<p>A person record doesn't have to belong to a family.  Generally speaking, Family records should represent a spousal or parental relationship.  A married couple, for instance, rperesents a Family.  A single parent with a chlild would also represent a Family.  However, a single person with no children would not have a Family record.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I add a new Family?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
		<li>From the top menu, select &quot;Add New Family&quot; (under &quot;People/Families&quot;).
		<li>Complete the form.  Note that you can insert up to ten family members from
		  directly from this form. Complete the individual lines for each person,
		  but only enter the last name if it <i>differs</i> from the last name of the
		  Family record. All people entered in this manner will create a new
		  Person record
		  which will be assigned to the designated Family record.
		<li>Press Save when the form is complete.
	</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I view a family?</div>
	<table width="100%" class="LightShadedBox"><tr><td><p>There are two ways to view a family:</p>
	<ol>
		<li>Enter a name to look for in the search field at the top of the page, click the button beside "Family" and press enter.</li>
		<li>Click on "View All Families"(under "People/Families").</li>
	</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I change the available Family Roles?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
		  <li>If you have permission, you should find a link called "Family Roles Manager" (under "People/Families").</li>
		  <li>If you want to add a new Family Role, type it into the blank field on the bottom of the page</li>
  		  <li>If you want to change a new Family Role, type it into the field you wish to change. NOTE: Field changes will be lost if you do not "Save Changes" before using an up, down, delete, or 'add new' button!</li>
		  <li>If you want to re-arrange the order, click the "up" and "down" links to the left of the field you wish to re-order.</li>
		  <li>If you want to delete a Family Role, click on the "delete" button to the right of the field you wish to delete.</li>
		</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I delete a Family?</div>
	<table width="100%" class="LightShadedBox"><tr><td><p>First of all, don't.</p>
	<p>But if you have to...</p>
	<ol>
		<li>Filter for the desired family, and bring up the Family View.
		<li>Select "Delete this Record" (if this link doesn't appear, then either you don't have permissions to delete records, or the Family still has Person records assigned to it; you cannot delete a Family record until all Person records have been unassigned from it)
		<li>Confirm the deletion
	</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I assign a Property to a Family?</div>
	<table width="100%" class="LightShadedBox"><tr><td>See the <a href="Help.php?page=Properties">Properties</a> help topic.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I add a Note to a Family?</div>
	<table width="100%" class="LightShadedBox"><tr><td>See the <a href="Help.php?page=Notes">Notes</a> help topic.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">What is the Classification feature?</div>
	<table width="100%" class="LightShadedBox"><tr><td>See the <a href="Help.php?page=Class">Classification</a> help topic.</td></tr></table>
</div>

<?php
	require "Include/Footer.php";
?>
