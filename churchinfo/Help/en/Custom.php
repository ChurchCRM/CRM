<?php
	$sPageTitle = "Custom Fields";
	require "Include/Header.php";
?>

<div class="Help_Section">
	<div class="Help_Header">What are Custom Fields?</div>
	<table width="100%" class="LightShadedBox"><tr><td><p>Custom Fields allow you to expand the functionality of ChurchInfo beyond
	  the base information that can be stored as a default. Custom fields allow
	  you to personalize the database to meet your specific needs. Custom fields
	  can be added to individuals and to groups. For individuals, you could,
	  for example, have a custom field that shows an individual's mentor. For
	  groups, you could have a start and stop date for a group of ushers.</p>
</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I assign Custom Fields?</div>
	<table width="100%" class="LightShadedBox"><tr><td><p>For people and groups it is different.</p>
	<p>For people, click on &quot;Edit Custom Person Fields&quot; under &quot;Admin&quot; on
	  the drop down menu. To add a new field, select the type, a name and the
	  side on which it should appear. The name will appear in the shaded box
	  on Person View and the side determines which column it shows up in when
	  viewing the Person View.</p>
	<p>For groups, click on the group you wish to add a custom field to and click
	  on "Edit Group-Specific Properties Form". If this link is not visible, this
	  group may not have group-specific properties enabled. Click on "Edit this
	  Group" and select the checkbox by "Use group-specific properties?". Too add
	  a new field, select the type, name, description and click &quot;Add new field&quot;.</p></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">What are the Types?</div>
	<table width="100%" class="LightShadedBox"><tr><td>See the <a href="Help.php?page=Types">Types</a> help topic.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I edit a Custom Field?</div>
	<table width="100%" class="LightShadedBox"><tr><td><p>For people, you can change the name, special option, and Person-View side.
	  When changes are made to those categories, you must click &quot;Save Changes&quot; before
	  anything else or all changes will be lost. If the type needs to be changed,
	  it can only be done by creating a new field and deleting the undesired field.
	  If you wish to change the order
	  in which the fields are displayed, use the up and down arrows to the left
	  to move its location. To delete a field, click the &quot;X&quot; on the left side.</p>
	<p>For groups, you can change the name, description, and person view. When changes
	  are made to those categories, you must click &quot;Save Changes&quot; before anything
	  else or all changes will be lost. Enabling the person view allows this
	  property to be shown when viewing an individual
	  in Person View. If the type needs to be changed, it can only be done by
	  creating a new field and deleting the undesired field. If you wish to change
	  the order
	  in which the fields are displayed, use the up and down arrows to the left
	  to move its location. To delete a field, click the &quot;X&quot; on the left
	  side.</p></td></tr></table>
</div>

<?php
	require "Include/Footer.php";
?>
