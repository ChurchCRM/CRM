<?php
	$sPageTitle = "Notes";
	require "Include/Header.php";
?>

<div class="Help_Section">
	<div class="Help_Header">What is a Note?</div>
	<table width="100%" class="LightShadedBox"><tr><td>A Note is just a miscellaneous memo assigned to a Person or Family record.  Any User with the Notes permission can add a Note, and as many Notes as desired can be added to a Person or Family record.
		<p>Notes can be public, meaning every User can see them, or private, meaning only the User who authored the Note can read it on subsequent views of the Person or Family record.  Notes can be deleted or edited as desired.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I view the Notes for a Person record?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
			<li>Filter for the desired Person, and bring up the Person View for that record.
			<li>At the bottom of the Person View will be a section called "Notes" which will contain all the notes for that record, in reverse chronological order (the most recent note first).
		</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I add a Note?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
			<li>Filter for the Person record to which you'd like to add the Note, and bring up the Person View for that record. 
			<li>At the bottom of the Person View will be a section called "Notes."  Click "Add a Note to this Record".
			<li>On the resulting page enter the text of the Note in the input box provided.  You may enter as much or as little text as you like.  If you would like the note to be private, meaning only you will able to read the Note in the future, check the box marked "Private."
			<li>When finished, press "Save".
		</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I edit a Note?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
			<li>Filter for the Person record to which the desired Note is assigned.
			<li>Find the desired Note in the "Notes" section and click "Edit this Note."
			<li>On the resulting form make any desired changes.  Press "Save" when finished.
		</ol></td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How to I make a private Note viewable by everyone?</div>
	<table width="100%" class="LightShadedBox"><tr><td>Edit the Note, and uncheck the "Private" checkbox.</td></tr></table>
</div>

<div class="Help_Section">
	<p><div class="Help_Header">How do I delete a Note?</div>
	<table width="100%" class="LightShadedBox"><tr><td><ol>
			<li>Filter for the Person record to which the desired Note is assigned.
			<li>Find the desired Note in the "Notes" section and click "Delete this Note."
			<li>On the resulting screen, confirm the deletion.
		</ol></td></tr></table>
</div>

<?php
	require "Include/Footer.php";
?>
