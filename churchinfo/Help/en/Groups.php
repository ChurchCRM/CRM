<?php
	$sPageTitle = "Groups";
	require "Include/Header.php";
?>

<div class="Help_Section">
	<div class="Help_Header">What is a Group?</div>
	<table width="100%" class="LightShadedBox"><tr><td>A Group is a collection of People, all of which occupy Roles within the Group.  Groups can be used to represent organization, educational, and social constructs within the church.
	<p>For example, a Group may be "Friday Night Bible Study."  Roles within this group may be Leader, Assistant Leader, and Member.  If 16 people are assigned to this group, 13 may occupy the Role of Member, 2 may occupy the Role of Assistant Leader, and 1 may occupy the Role of Leader.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I add a new Group?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
		<li>From the top menu, click on "Add a New Group" (under &quot;Groups&quot;).
		<li>Complete the form.
		<li>Press Save.
	</ol>
	<p>When a new Group is created, a Role of "Member" is automatically created and assigned as the Default Role for that Group.  (You may immediately change the name of this role, however.)</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I change the Name/Description/Type of a Group?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
		<li>From the top menu, select &quot;List Groups&quot; (under &quot;Groups&quot;).
		<li>Click on the desired Group.
		<li>Click on "Edit this group."
		<li>Under the heading of "Group Editor" is a list of current group roles. Type the new title in the input box, and press "Save".
		<li>The new information should appear.
	</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I add a new Role to a Group?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
		<li>From the top menu, select &quot;List Groups&quot; (under &quot;Groups&quot;).
		<li>Click on the desired Group.
		<li>Click on "Edit this group."
		<li>Under the heading of "Group Roles" is a list of current group roles. Type the name of the desired new Role in the input box, and press "Add."
		<li>The new Role should appear in the list.
	</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I change a Role in a Group?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
		<li>From the top menu, select &quot;List Groups&quot; (under &quot;Groups&quot;).
		<li>Click on the desired Group.
		<li>Click on "Edit this group."
		<li>Under the heading of "Group Roles" is a list of current group roles. Type the name of the desired new name in the input box of the old name, and press "Save Changes." Please note, if you make changes without clicking "Save Changes", all changes will be lost.
		<li>The new Role should appear in the list.
	</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">What is the Default Role?</div>
	<table width="100%" class="LightShadedBox"><tr><td>Every Group has a Default Role, which is simply the "standard" Role for a Member of that Group.  For a class, for instance, the Default Role might be Student, because 95% of the people in the class  will be Students.  There will be other Roles -- Teacher, Assistant Teacher, etc. -- but most everyone will be a Student, so that's the Default Role.
	<p>Default Roles allow you to quickly add new Members to a group.  If you have 200 People in your Cart, you don't have to specify Roles for every record to dump the Cart to a Group, they are simply added as the Default Role.  This also allows quick sorting of the organizers of a Group.  The Group View producers all Members of a Group who do not occupy the Default Role, which are usually the organizers and administrators of the Group.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I change the Default Role for a Group?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
		<li>From the top menu, select &quot;List Groups&quot; (under &quot;Groups&quot;).
        <li>Click on the desired Group.
 		<li>Click on "Edit this group."
		<li>Under the heading of "Group Roles" is a list of current group roles. Click on "Make Default" to make the desired role the Default. The word "Default" appears over the number of the shaded box for the default role.
    </ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">What is Group Type?</div>
	<table width="100%" class="LightShadedBox"><tr><td>Group types allow you to categorize your groups. For example, a group called "Gleaners Class" can be type "Sunday School" and a group called "Franklin House" can be type "Cell Groups". This helps in further classifying groups so that you don't have to memorize which group is associated with which type.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I set a Group Type?</div>
	<table width="100%" class="LightShadedBox"><tr><td>When a new group is created, you are given the option to set the group type.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I change the available Group Types?</div>
	<table width="100%" class="LightShadedBox"><tr><td>From the top menu, click on "Edit Group Types" (under "Groups").</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">What are Group-Specific Properties</div>
	<table width="100%" class="LightShadedBox"><tr><td>Group-Specific Properties is a powerful feature that allows you to add any fields that you need to use that do not come built-in with ChurchInfo. This feature allows you to, for example, add a Mentor to a person, or add an additional date (such as confirmation). The possibilities are endless.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I use Group-Specific Properties?</div>
	<table width="100%" class="LightShadedBox"><tr><td>See the <a href="HelpCustom.php">Custom Fields</a> help topic.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I add People to a Group?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
		<li>Add the desired people to your Cart.
		<li>When you have the desired people in the Cart, from the top menu, select "Empty Cart to Group" (under &quot;Cart&quot;).
		<li>On the resulting screen, select the desired Group, then press "Add to Group:&quot;.
	</ol>
	<p>All members of the cart will be added to the specified Group IF they do not already exist in that Group.  If a Person in the cart already exists in the specified Group, that Person will not be added again.</p>
	<p>All People will be added to the Group in that group's Default Role.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I change the role of a Person in a Group?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
		<li>From the left menu, select "List Groups."
		<li>Click on the desired Group.
		<li>Click on "View Members".
		<li>Find the desired Member and click on "Change Role".
		<li>Select the new Role from the drop-down list.
		<li>Press "Update".
	</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">What is "Add Group Members to Cart"</div>
	<table width="100%" class="LightShadedBox"><tr><td>Adding group members to the cart is an easy way to add a group of individuals to the cart. Right now, the cart can be used to add individuals to a group. However, in future releases, the cart will be able to make group mailing lists and other features. For more information, see the <a href="HelpCart.php">Cart</a> help topic.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I assign a Property to a Group?</div>
	<table width="100%" class="LightShadedBox"><tr><td>See the <a href="Help.php?page=Properties">Properties</a> help topic.</td></tr></table>
</div>

<?php
	require "Include/Footer.php";
?>
