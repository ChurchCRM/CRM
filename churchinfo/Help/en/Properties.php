<?php
	$sPageTitle = "Properties";
	require "Include/Header.php";
?>

<div class="Help_Section">
	<div class="Help_Header">What is a Property?</div>
	<table width="100%" class="LightShadedBox"><tr><td><p>A Property is a label that can be applied to a Person, a Group, or a Family.  Separate sets of Properties are defined for the three different record types, and new properties can be created as need.  A record can be assigned an unlimited number of Properties.</p>
	<p>Additionally, Properties can have values which contain information related to that Property.  For example, a Property for a Person  record might be "Hospitalized."  A person with this Property is currently in the hospital, and the value of this Property could contain the name of the hospital and the room number.</p></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I know what Properties have been assigned?</div>
	<table width="100%" class="LightShadedBox"><tr><td><p>On the Person, Family, or Group View, you'll find a section called "Assigned
	    Properties" in which will be listed all the Properties assigned to that Person, Family
	    or Group along with the Property Values, if supported by that Property.</p></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I assign a Property to a Person/Family/Group?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
		<li>For a person or family, filter for the desired person and bring up the
		  Person/Family View for that record. For a group, click on &quot;List Groups&quot; under &quot;Groups&quot; in
		  the drop-down menu and select the desired group.
		<li>Under the section called "Assigned Properties" will be a drop-down list of all available Properties which are not currently assigned to that Person.  Select the desired Property and press "Assign."
		<li>If the property supports a Property Value, then you'll be prompted to enter the Value.  If the Property does not support a Value, the Property will just be automatically assigned.
	</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do a edit a Property Value assigned to a Person/Family/Group?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
		<li>For a person or family, filter for the desired person and bring up the
		  Person/Family View for that record. For a group, click on &quot;List Groups&quot;
		  under &quot;Groups&quot; in the
		  drop-down menu and select the desired group.
		<li>Under the section called "Assigned Properties" find the Property you wish to edit.  Click the "Edit" link (if this link is not present, then the Property does not support a value and there is nothing to edit).
		<li>On the resulting page, edit the Value.  Press "Update."
	</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I remove or un-assign a Property from a Person/Family/Group?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
		<li>For a person or family, filter for the desired person and bring up the
		  Person/Family View for that record. For a group, click on &quot;List Groups&quot; under &quot;Groups&quot; in
		  the drop-down menu and select the desired group.
		<li>Under the section called "Assigned Properties" find the Property you wish to remove.  Click the "Remove" link.
		<li>On the resulting screen, confirm the removal.
	</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do a I add a brand-new Property that I can assign to a Person record?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
		<li>From the top menu, select "List Person Properties" (under &quot;Properties&quot;).	
		<li>On the resulting screen, select "Add a New Person Property."
		<li>Complete the form.  If you would like the Property to support a Value, enter a prompt (ex. - "Enter the hospital name and room number.").  Leaving the Prompt field blank will disallow the storing of a Value with the Property.
	</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do a I add a brand-new Property that I can assign to a Family record?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
        <li>From the top menu, select "List Family Properties" (under &quot;Properties&quot;).
        <li>On the resulting screen, select "Add a New Family Property."
        <li>Complete the form. If you would like the Property to support a Value,
          enter a prompt (ex. - "Enter the hospital name and room number.").
          Leaving the Prompt field blank will disallow the storing of a Value
          with the Property.
      </ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do a I add a brand-new Property that I can assign to a Group record?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
        <li>From the top menu, select "List Group Properties" (under &quot;Properties&quot;).
        <li>On the resulting screen, select "Add a New Group Property."
        <li>Complete the form. If you would like the Property to support a Value,
          enter a prompt (ex. - "Enter the hospital name and room number.").
          Leaving the Prompt field blank will disallow the storing of a Value
          with the Property.
      </ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header"><p>What is a Property Type?</p></div>
	<table width="100%" class="LightShadedBox"><tr><td><p>This is just a method of organizing Properties into groups.  A Property must
	  be associated with a Property Type. Common Property Types might be: Physical
	  Status containing the Properties of: Disabled, Homebound, Hospitalized,
	  etc.</p></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I add a new Property Type?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
		<li>From the top menu, select "Property Types" (under &quot;Properties&quot;).	
		<li>On the resulting screen, select "Add a New Property Type."
		<li>Complete the form and press "Save."
	</ol></td></tr></table>
</div>

<?php
	require "Include/Footer.php";
?>