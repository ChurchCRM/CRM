<?php
	$sPageTitle = "Finances";
	require "Include/Header.php";
?>

<div class="Help_Section">
	<div class="Help_Header">What Financial Tracking is provided by ChurchInfo?</div>
	<table width="100%" class="LightShadedBox"><tr>
	<td><p>ChurchInfo keeps track of the following information:</p>
	  <ul>
	    <li><b>Pledge:</b> Promise of support, planning to donate a specific total amount.</li>
	    <li><b>Deposit Slip:</b> Print a batch of donations on a standard bank deposit form for the bank.</li>
       <li><b>Payment:</b> A donation payment by cash, check, credit card, or bank draft.</li>
	    <li><b>Reminder Statements:</b> Print letters to remind Families of their pledge and report progress of their payments for the current fiscal year.</li>
	    <li><b>Tax Statements:</b> Print letters acknowledging donations over the calendar year for tax purposes.</li>
	  </ul></td>
	</tr></table>
</div>

<div class="Help_Section">
	<p>
	<div class="Help_Header">How do I enter a pledge?</div>
    <table width="100%" class="LightShadedBox"><tr>
    <td><p>There are two ways in which pledges can be added:</p>
      <ul>
        <li><strong>From the Family View:</strong> When viewing a Family, a link for
          &quot;Add a new pledge&quot; will be near the bottom of the screen. Enter the information and
          click &quot;Save&quot;.</li>
        <li><strong>Batch Entry:</strong> If you click &quot;Save and Add&quot; rather than &quot;Save&quot;, the Pledge
          Editor will clear and prepare for another pledge entry.  Select the next family making a
          pledge from the list, and fill in the rest of the pledge information.  Continue to click
          &quot;Save and Add&quot; until all the pledges have been entered.</li>
      </ul></td>
    </tr></table>
</div>

<div class="Help_Section">
	<p>
	<div class="Help_Header">How do I deposit donations?</div>
    <table width="100%" class="LightShadedBox"><tr>
    <td><p>When a batch of cash and check donations is received they are entered into
    ChurchInfo so the donating families receive credit against their pledges and also
    for tax purposes.</p>
      <ul>
        <li><strong>Make a new deposit slip:</strong> Select &quot;New Deposit Slip&quot;
          from the &quot;Deposit&quot; menu.</li>
        <li><strong>Enter the deposits:</strong> See below.</li>
        <li><strong>Print the deposit slip:</strong> Select &quot;Edit Deposit Slip&quot;
          from the &quot;Deposit&quot; menu.  Click on &quot;Generate PDF&quot;.  This PDF
          document will print on a standard bank deposit form.</li>
        <li><strong>Close the deposit:</strong> Select &quot;Close deposit slip&quot; to
          close the deposit slip once the deposit has been packaged for the bank.</li>
      </ul></td>
    </tr></table>
</div>

<div class="Help_Section">
	<p>
	<div class="Help_Header">How do I enter a payment?</div>
    <table width="100%" class="LightShadedBox"><tr>
    <td><p>Payments are very similar to pledges.  There are two ways in which payments can be added:</p>
      <ul>
        <li><strong>From the Family View:</strong> When viewing a Family, a link for
          &quot;Add a new payment&quot; will be near the bottom of the screen. Enter the information and
          click &quot;Save&quot;.</li>
        <li><strong>Batch Entry:</strong> If you click &quot;Save and Add&quot; rather than &quot;Save&quot;, the Payment
          Editor will clear and prepare for another pledge entry.  Select the next family making a
          payment from the list, and fill in the rest of the payment information.  Continue to click
          &quot;Save and Add&quot; until all the payments have been entered.</li>
      </ul></td>
    </tr></table>
</div>

<?php
	require "Include/Footer.php";
?>
