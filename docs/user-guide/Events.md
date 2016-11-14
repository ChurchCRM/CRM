# Events

ChurchCRM allows you to create events which will appear in your church calendar.  
Events can be recurring (such as a weekly church service) or unique (such as a community outreach day).
Once an event is created, you can:
- Take attendance for your event and review attendance metrics
- Manage child care security by checking children in and out of classrooms

## Creating an Event Type
Event types define the parameters for events you will create in the future, such as the recurrence frequency, the default start time, and the member classification you wish to track.

**To create an event type:**

1.	Choose *Events – List Event Types*.

2.	Choose *Add Event Type*.

3.	Make your entries and choose *Save Changes*.

## Creating an Event
Events inherit some properties from event types.  You can additionally define the event title, description, date range, event sermon (if any), and status.

**To create an event:**

1.	Choose *Events -> Add Church Event*.

2.	Select an event type.

3.	Make your entries and choose *Save Changes*.

## Taking Attendance for an Event

Throughout the course of an event, you can take attendance to track the participation across various people classifications, such as members, regular attenders, visitors, and so on.

**To add an existing person to an event:**

1.	Choose *Members -> View All Persons*.

2.	In the **Filter and Cart** area, enter a name in the search field and choose *Apply Filter*.

3.	In the **Listing** area, choose the *Add to Cart* icon.

4.	Continue adding people to the cart as described in the previous steps.

5.	Choose the shopping cart icon in the header bar at the top of the screen.

6.	Choose *Empty Cart to Event*.

7.	Select your event and choose *Add Cart to Event*.

**To add a visitor to an event:**

1.	Choose *Members -> Add New Person*.

2.	Make your entries, ensuring you set the **Classification** field to *Guest*.

3.	Choose *Save*.

4.	Choose *Add to Cart*.

5.	Choose the shopping cart icon in the header bar at the top of the screen.

6.	Choose *Empty Cart to Event*.

7.	Select your event and choose *Add Cart to Event*.

## Generating Attendance Reports for an Event

You can generate reports based on attendance history for an event.  The tracked person types measured are defined in the event type.

**To generate a report:**

1.	Choose *Data/Reports -> Reports Menu*.

2.	Choose an event type from the *Event Attendance Reports* area.

3.	Choose a person type corresponding to the event for which you wish to generate a report.

## Checking Children In and Out of an Event

During any event, you can monitor the checking in and checking out of children to comply with your church child protection policy.
In this scenario, you use the `PersonID` number associated with an individual to identify them in the system.  This `PersonID` number is recognizable in the URL of the individual’s record in the system.

**To check a child into an event:**

1.	Choose *Events -> Check-in and Check-out*.

2.	Select an event.

3.	Enter the `PersonID` of the child in the left-hand field and the `PersonID` of the parent or guardian in the right-hand field.  Note that you can use the Person Listing (*Members -> View All Members*) to look up and determine the `PersonID` of any individual.

4.	Choose *Verify*.

5.	Review the data and choose *CheckIn*.

**To check a child out of an event:**

1.	Choose *Events -> Check-in and Check-out*.

2.	Select an event.

3.	Locate the entry of a child who had previously been checked into the system and choose *Checkout*.

4.	Enter the `PersonID` of the parent or guardian in the right-hand field.  Note that you can use the **Person Listing** (*Members -> View All Members*) to look up and determine the `PersonID` of any individual.

5.	Choose *Verify CheckOut*.

6.	Review the data and choose *Finalize CheckOut*.
