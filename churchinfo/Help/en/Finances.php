<?php
	$sPageTitle = "Finances";
	require "Include/Header.php";
?>

<div class="Help_Section">
	<div class="Help_Header">What Financial Tracking is provided by InfoCentral?</div>
	<table width="100%" class="LightShadedBox"><tr>
	<td><p>InfoCentral allows a user to keep track of the following information:</p>
	  <ul>
	    <li><b>Donation Entry:</b> Batch donation entry by envelope number with multiple funds</li>
        <li><b>Exemption Letter:</b> Print a yearly exemption letter for all individuals</li>
	    <li><b>Donation Receipt:</b> Print a receipt for individual donations</li>
	    <li><b>Donation Reports:</b> Reports based on donations given</li>
	    <li><b>Envelope Management:</b> Keep track of envelope numbers and export for envelope printing service</li>
	  </ul></td>
	</tr></table>
</div>

<div class="Help_Section">
	<p>
	<div class="Help_Header">How do I assign a new envelope number?</div>
	<table width="100%" class="LightShadedBox"><tr>
	<td>
		<p>Thre are multiple ways in which to assign an envelope number:</p>
		<ul>
		  <li>Envelope
		      numbers can be assigned when a person is entered into the system.
		    InfoCentral automatically displays the next available number to ensure no
		    overlap
	      of numbers. </li>
	      <li>Envelope numbers can be assigned to multiple individuals by placing
	        them in the cart and then clicking on the &quot;Auto assign envelopes
	        to all persons in the cart without envelopes&quot; link in the Donation
	        Envelope Manager, which can be found in the drop-down menu under
	        &quot;Finances&quot;.</li>
		  <li>Envelope numbers can be re-assigned to all individuals by clicking on
		    the &quot;Re-assign all existing envelopes by assignees' names in alphabetical
	    order&quot; link in the Donation Envelop Manger. This tool re-assigns all
		    previously assigned envelopes (in case of a new fiscal year, wanting
		    to have all numbers in alphabetical order).</li>
		</ul></td>
	</tr></table>
</div>

<div class="Help_Section">
	<p>
	<div class="Help_Header">How do I view all assigned envelopes?</div>
    <table width="100%" class="LightShadedBox"><tr>
    <td>In the &quot;Donation Envelopes Manger&quot; (found under &quot;Finance&quot; on the drop-down
      menu), there is a link for &quot;Display a list of all envelope assignment.&quot;
      This prints a PDF document of all envelope numbers and the individuals
      assigned to that number.</td>
    </tr></table>
</div>

<div class="Help_Section">
	<p>
	<div class="Help_Header">How do I enter a donation?</div>
    <table width="100%" class="LightShadedBox"><tr>
    <td><p>There are two ways in which donations can be added:</p>
      <ul>
        <li><strong>Batch Entry:</strong> Batch entry is done by the envelope number. Simply enter
          the envelope number and move to the next field. The address of that
          envelope will appear in the shaded box below to ensure that the donation
          matches the information displayed. When you are finished, select &quot;Enter
          Donation&quot;. The information will be saved and this screen will be cleared
          for the next donation. When you have finished entering all the donations,
          simply click &quot;Exit&quot;.</li>
        <li><strong>Individual Entry:</strong> When viewing an individual's profile, a link for
          &quot;Add Donation&quot; will be in the row of links. Enter the information and
          click &quot;Enter Donation&quot;.</li>
      </ul></td>
    </tr></table>
</div>

<div class="Help_Section">
	<p>
	<div class="Help_Header">How do I view donations for an individual?</div>
    <table width="100%" class="LightShadedBox"><tr>
    <td>In the person view, there is a link for &quot;View Donations.&quot; This link will
      take you to a page that displays all donations from this individual.</td>
    </tr></table>
</div>

<div class="Help_Section">
	<p>
	<div class="Help_Header">How do I print an exemption letters?</div>
    <table width="100%" class="LightShadedBox"><tr>
    <td><p>There are two ways to print an exemption letter:</p>
      <ul>
        <li><strong>Individually:</strong> In the person view, there is a link for &quot;View Donations.&quot;
          On this page there is a link called &quot;View XXXX (current year) exemption
          letter&quot;. This creates an exemption letter for that individual</li>
        <li><strong>Batch:</strong> In the &quot;Reports Menu&quot; (found under &quot;Data/Reports&quot; on the drop-down
          menu), there is a link for &quot;Donation End of Year Reports for All Members&quot;.
          This creats a member contribution letter for all members with donations.</li>
      </ul></td>
    </tr></table>
</div>

<div class="Help_Section">
	<p>
	<div class="Help_Header">How do I view donation reports for a given day?</div>
    <table width="100%" class="LightShadedBox"><tr>
    <td>In the &quot;Reports Menu&quot; (found under &quot;Data/Reports&quot; on
      the drop-down menu), there is a link for &quot;Donation Summary Report&quot; .
      This displays a donation summary report for a specified day, including
      breakdown by fund.</td>
    </tr></table>
</div>

<div class="Help_Section">
	<p>
	<div class="Help_Header">How do I view donations for an individual?</div>
    <table width="100%" class="LightShadedBox"><tr>
    <td>In the person view, there is a link for &quot;View Donations.&quot; This link will
      take you to a page that displays all donations from this individual.</td>
    </tr></table>
</div>

<div class="Help_Section">
	<p>
	<div class="Help_Header">How do I reassign donations from an individual?</div>
    <table width="100%" class="LightShadedBox"><tr>
    <td><ol>
      <li>Place the individual which you wish to reassign the donations <em><strong>to</strong></em><strong> </strong>into
          the cart.</li>
      <li>In the person view, there is a link for &quot;View Donations.&quot; On
        this page select the  link called &quot;Move All Donations&quot;.</li>
      <li>Select the individual in the cart from the drop-down menu and click
        &quot;Move&quot;.</li>
      <li>Confirm the move.</li>
    </ol>
    </td>
    </tr></table>
</div>

<?php
	require "Include/Footer.php";
?>