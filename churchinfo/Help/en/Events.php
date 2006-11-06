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
          <p>In it's basic usage an <b>Event</b> could be a Worship Service or Sunday School. It could also be a fundraiser, a picnic, etc. By using the <b>Event</b> module you can generate reports on who showed up, who didn't, and a list of any guests attended.  <b>Events</b> are created using a template called and <b>Event Type</b>.</p>
        </td>
      </tr>
   </table>
  </div>
</div>

<div class="Help_Section">
  <div class="Help_Header">What is an Event Type?</div>
    <table width="100%" class="LightShadedBox">
      <tr>
        <td>
          <p>An <b>Event Type</b> is a template which defines a default pattern used to create a particular kind of event. Every <b> Event</b> is created using an <b>Event Type</b>.  The <b>Event Type</b> definition includes an Event Name, a Recurrance Pattern, the default Start Time, and a list of the Attendance Counts tracked by this <b>Event Type</b>. </p>
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
          <p>In the <b>Events</b> Tab of the Menu you will see a link "<b>List Church Event</b>". By clicking on that link you will be taken to a page displaying all the <b>Events</b> that are recorded listed by month for the current year.  On this display you will see the Event Name, Description, the recorded Attendance counts, the Start Time, and a button which displays the number of Attendees recorded for this event.  Clicking the Attendees button will display a list of the People registered as attending this <b>Event</b>.  At the top of the page is a drop down box used to control which types of <b>Events</b> are displayed on the page.  When a single <b>Event Type</b> is displayed on the page, the report will include a monthly average of the recorded Attendance Counts.  By default "All" <b>Event Types</b> are displayed.</p>
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
          <p>In the <b>Events</b> Tab of the Menu you will see a link "<b>Add Church Event</b>". By clicking on that link you will be taken to a page displaying the available <b>Event Types</b>.  Select the type of <b>Event</b> you wish to create and click on the button labeled "Create=>Event".  You will then be presented with an <b>Event</b> form pre-filled using the <b>Event Type</b> information.  Fill in the remaining fields, check the pre-filled information, and click "Save".</p>
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
        <td>Prefilled using the <b>Event Type</b> information</td>

      </tr>
      <tr>
        <td><b>Event Title</b></td>
        <td>REQ.</td>
        <td>Enter a Title for your <b>Event</b>. 255 characters or less<br>This information will be displayed on the <b>Event Listing</b>.</td>

      </tr>
      <tr>
        <td><b>Event Description</b></td>
        <td>REQ.</td>
        <td>Enter a Short Description for your <b>Event</b>. 255 characters or less<br>This information will be displayed on the <b>Event Listing</b>. A required field.</td>

      </tr>
      <tr>
        <td><b>Start Date</b></td>
        <td>REQ.</td>
        <td><p>[format: YYY-MM-DD] Pre-filled using the Recurrance Pattern of the <b>Event Type</b>.  If Recurrance Pattern =:</p>
<p><b>"None"</b> - Date is set to today's date</p>
<p><b>"Weekly on __"</b> - Date is set to either (1)The most recent date which matches the pattern or (2) The next occurance of the date pattern for which there is no existing event of this <b>Event Type<b>.</p>
<p><b>"Monthly on  ___"</b> - Date is set to either (1)The most recent date which matches the pattern or (2) The next occurance of the date pattern for which there is no existing event of this <b>Event Type<b>.</p>
<p><b>"Yearly on ____"</b> - Date is set to either (1)The most recent date which matches the pattern or (2) The next occurance of the date pattern for which there is no existing event of this <b>Event Type<b>.</p>
The prefilled values may be edited.  </td>

      </tr>
      <tr>
        <td><b>Start Time</b></td>
        <td>REQ.</td>
        <td>The Start Time is also set using the values defined in the <b>Event Type</b> and may be changed.  Time is displayed in 15 minute increments.<br>This information will be displayed on the <b>Event Listing</b>.</td>

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
        <td><b>Attendance Counts</b></td>
        <td>OPT.</td>
        <td>The form will display a list of Attendance Count fields based upon the <b>Event Type</b> definition.  Enter the appropriate attendance count in the box adjacent to the name.  Enter a Total (this is not a calculated total).  These values will be displayed on the <b>List Church Events</b> page.</td>

      </tr>
      <tr>
        <td><b>Event Sermon</b></td>
        <td>OPT.</td>
        <td>Enter the Text of your Sermon, if any, for your <b>Event</b>.<br>This information will be availabe via a link on the <b>Event Listing</b>. An optional field.</td>

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
  <div class="Help_Header">How do I add an Event Type to the list?</div>
    <table width="100%" class="LightShadedBox">
      <tr>
        <td>
          <p>In the <b>Events</b> Tab of the Menu you will see a link "<b>List Event Types</b>". By clicking on that link you will be taken to a page displaying the <b>Event Types</b> that are currently available. At the bottom of the page there is a button labeled "Add Event Type".  Clicking this button will present a fill-in form where you can create a new Event Type.  NOTE: Event Types cannot be edited, but they can be deleted.  However, remember that monthly totals will only be calculated for events with the identical Event Type ID. </p>
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
        <td><b>Event Type Name</b></td>
        <td>REQ.</td>
        <td>Enter a name for this Event Type.  This name will be displayed on the List Church Events, and the Event Reports pages</td>
      </tr>
      <tr>
        <td><b>Recurrance Pattern</b></td>
        <td>REQ.</td>
        <td><p>The Recurrance Pattern is used to pre-fill the Event Start Date / Time when a new Event is created.  Choose from one of the following types of Recurrance by selecting the appropriate Radio Button and filling in the values associated with the Recurrance Pattern.</p>
<p>"none" - No pattern.  New Events will have Date set to today's date</p>
<p>"weekly on __" - The Event normally occurs weekly on a particular day of the week,  New Events will have Date set to either (1)The most recent date which matches the pattern or (2) The next occurance of the date pattern for which there is no existing event of this <b>Event Type<b>.</p>
<p>"Monthly on  ___" - The Event normally occurs on a particular day of the month.  New Events will have Date set to either (1)The most recent date which matches the pattern or (2) The next occurance of the date pattern for which there is no existing event of this <b>Event Type<b>.</p>
<p>"Yearly on ____" - The Event normally occurs on a particular day of the year (i.e. Easter, Christmas).  New Events will have Date set to either (1)The most recent date which matches the pattern or (2) The next occurance of the date pattern for which there is no existing event of this <b>Event Type<b>.</p></td>
      </tr>
      <tr>
        <td><b>Default Start Time</b></td>
        <td>REQ.</td>
        <td>The Default Start Time will be used to pre-fill the Start Time value when new Events of this type are created.  Time is displayed in 15 minute increments.<br>This information will be displayed on the <b>Event Listing</b>.</td>
      </tr>
      <tr>
        <td><b>Attendance Counts</b></td>
        <td>OPT.</td>
        <td>Enter a list of the names of the Attendance Counts you want to include with this Event Type separated by a comma. Each name entered will create an Attendance Count field on the Event Edit screen.  A TOTAL Attendance Count field will be added automatically.  Be careful when entering Attendance Counts because they cannot be edited and will be displayed exactly as entered.  Examples of Attendance Counts include (Members, Attenders, Visitors) or (Men, Women, Children) or (Adult Class, Teen Class, etc.)  Keep the number of Attendance Counts to less than 5-6.  More than 5-6 will result in an unsatisfactory display of Attendance Count data on the List Church Events page. </td>
      </tr>
    </table>
</div>
<?php } else { ?>
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
          <p>There are two ways of tracking attendance; By Attendance Counts and By Attendees.</p>
	  <p>By Counts - When an Event is created using the "Create=>Event" button on the "List Event Types" screen, a list of Attendance Count fields will be displayed.  Simply enter the desired count value in the appropriate Count Field on the form.</p>
 	 <p>By Attendees - First you will want to make sure that all attendees (members and guests) are added to the membership database. See <a href="Help.php?page=People">Help -> People</a> on how to add people into the database.</p>
          <p>Once that is done you will need to put attendees into the "<b>Cart</b>". To do this select "<b>View all persons</b> from the <b>People/Families</b> dropdown in the menu. You get a display of all Person in the membership database.</p>
          <p>Select each individual by clicking on the <u>Add to cart</u> link. (This will change to "Remove from cart" after successful placement in the Cart). Do this for each person that attended an <b>Event</b>.</p>
          <p>Next you will need to select "<b>Empty Cart to Event</b>" from the <b>Cart</b> dropdown in the menu. This will bring you to a page to "<b>Select Event</b>". Choose an event from the dropdown and click "<b>Add Cart to Event" button.</p>
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
