<?php
        $sPageTitle = "Events";
        require "Include/Header.php";
?>
<div class="Help_Section">
  <div class="Help_Header">What is an Event?</div>
    <table width="100%" class="LightShadedBox">
      <tr>
        <td>
          <p>An <b>Event</b> is an occasion that may, or may not, be hosted at your location.</p>
          <p>In it's basic usage an <b>Event</b> could be a Service or Sunday School. It could also be a fundraiser, a picnic, etc. By using the <b>Event</b> module you can generate reports on who showed up, who didn't, and a list of any guests attended.</p>
        </td>
      </tr>
   </table>
  </div>
</div>

<div class="Help_Section">
  <div class="Help_Header">How do I see what Events are available?</div>
    <table width="100%" class="LightShadedBox">
      <tr>
        <td>
          <p>In the <b>Events</b> Tab of the Menu you will see a link "<b>List Church Event</b>". By clicking on that link you will be taken to a page displaying all the <b>Events</b> that are recorded listed by month for the current year.</p>
        </td>
      </tr>
   </table>
  </div>
</div>

<div class="Help_Section">
  <div class="Help_Header">How do I add a New Event?</div>
    <table width="100%" class="LightShadedBox">
      <tr>
        <td>
          <p>In the <b>Events</b> Tab of the Menu you will see a link "<b>Add Church Event</b>". By clicking on that link you will be taken to a page displaying both the <b>Events</b> that are available in the current month and below that you will find a form for adding new <b>Events</b></p>
        </td>
      </tr>
    </table>
    <table width="100%" class="LightShadedBox">
      </tr>
      <tr>
        <td class="HeaderRow"><b>Field</b></td>
        <td class="HeaderRow"><b>Type</b></td>
        <td class="HeaderRow"><b>Description</b></td>
      </tr>
      <tr>
        <td><b>Event Type</b></td>
        <td>REQ.</td>
        <td>A dropdown of the available <b>Event Names</b></td>
      </tr>
      <tr>
        <td><b>Event Title</b></td>
        <td>REQ.</td>
        <td>Enter a Title for your <b>Event</b>. 255 characters or less<br>This information will be displayed on the <b>Event Listing</b>. A required field.</td>
      </tr>
      <tr>
        <td><b>Event Description</b></td>
        <td>REQ.</td>
        <td>Enter a Short Description for your <b>Event</b>. 255 characters or less<br>This information will be displayed on the <b>Event Listing</b>. A required field.</td>
      </tr>
      <tr>
        <td><b>Event Sermon</b></td>
        <td>OPT.</td>
        <td>Enter the Text of your Sermon, if any, for your <b>Event</b>.<br>This information will be availabe via a link on the <b>Event Listing</b>. An optional field.</td>
      </tr>
      <tr>
        <td><b>Start Date</b></td>
        <td>REQ.</td>
        <td>Enter a start date - [format: YYYY-MM-DD] - for your <b>Event</b>.<br>You may use the calendar image next to the text box to visually select the date.<br>This information will be displayed on the <b>Event Listing</b>. A required field.</td>
      </tr>
      <tr>
        <td><b>Start Time</b></td>
        <td>REQ.</td>
        <td>Choose a start time for your <b>Event</b>. Time is displayed in 15 minute increments.<br>This information will be displayed on the <b>Event Listing</b>. A required field.</td>
      </tr>
      <tr>
        <td><b>End Date</b></td>
        <td>OPT.</td>
        <td>Enter a end date - [format: YYYY-MM-DD] - for your <b>Event</b>.<br>You may use the calendar image next to the text box to visually select the date, the calendar will "remember" the start date from your earlier selection.<br>This information will be displayed on the <b>Event Listing</b>. An optional field.</td>
      </tr>
      <tr>
        <td><b>End Time</b></td>
        <td>OPT.</td>
        <td>Choose an end time for your <b>Event</b>. Time is displayed in 15 minute increments.<br>This information will be displayed on the <b>Event Listing</b>. An optional field.</td>
      </tr>
      <tr>
        <td><b>Event Status</b></td>
        <td>REQ.</td>
        <td>Whether the <b>Event</b> is active or not. <b>Events</b> can be "temporarily disabled" to allow for pre scheduling or archiving.<br>This information will be displayed on the <b>Event Listing</b>. A required field.</td>
      </tr>
    </table>
  </div>
</div>

<?php if ($_SESSION['bAdmin']) { ?>
<div class="Help_Section">
  <div class="Help_Header">How do I add an Event Name to the list?</div>
    <table width="100%" class="LightShadedBox">
      <tr>
        <td>
          <p>In the <b>Events</b> Tab of the Menu you will see a link "<b>Manage Event Names</b>". By clicking on that link you will be taken to a page displaying the <b>Event Names</b> that are currently available. You may modify these names to suit your organization, however, you cannot delete them as this would break the historical data linked to the <b>Event ID</b> of that <b>Event</b>.</p>
          <p>To modify an <b>Event Name</b> Enter the <b>New Event Name</b> in the text box corresponding with the <b>Event ID</b>. Clicking on the "<b>Save Changes</b>" Button will save the changes to the <b>Event Name</b></p>
          <p>To add an <b>New Event Name</b> Enter the <b>Event Name</b> in the "<b>New Event Name</b>" text box. Clicking on the "<b>Add Event Name</b>" Button will permanently add the <b>Event Name</b></p>
        </td>
      </tr>
   </table>
</div>
<? } else { ?>
<div class="Help_Section">
  <div class="Help_Header">I don't see the Event listed in the dropdown box, how do I add an Event Name to the list?</div>
    <table width="100%" class="LightShadedBox">
      <tr>
        <td>
          <p>Please contact the site administrator to have your Event Name added.</p>
        </td>
      </tr>
   </table>
</div>
<?php } ?>

<div class="Help_Section">
  <div class="Help_Header">How keep track of attendance Attendance?</div>
    <table width="100%" class="LightShadedBox">
      <tr>
        <td>
          <p>First you will want to make sure that all attendees (members and guests) are added to the membership database. See <a href="Help.php?page=People">Help -> People</a> on how to add people into the database.</p>
          <p>Once that is done you will need to put attendees into the "<b>Cart</b>". To do this select "<b>View all persons</b> from the <b>People/Families</b> dropdown in the menu. You get a display of all Person in the membership database.</p>
          <p>Select each individual by clicking on the <u>Add to cart</u> link. (This will change to "Remove from cart" after successful placement in the Cart). Do this for each person that attended an <b>Event</b>.</p>
          <p>Next you will need to select "<b>Empty Cart to Event</b>" from the <b>Cart</b> dropdown in the menu. This will bring you to a page to "<b>Select Event</b>". Choose an event from the dropdown and click "<b>Add Cart to Event</p>" button.</p>
          <p>If you don't see your <b>Event</b> listed you may add it via the <u>Add New Event</u> Link.</p>
        </td>
      </tr>
   </table>
  </div>
</div>

<div class="Help_Section">
  <div class="Help_Header">How do I retreive Attendance Reports?</div>
    <table width="100%" class="LightShadedBox">
      <tr>
        <td>
          <p>TBD</p>
        </td>
      </tr>
   </table>
  </div>
</div>

<?php
        require "Include/Footer.php";
?>
