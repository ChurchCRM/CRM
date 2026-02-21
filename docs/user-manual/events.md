# Events

ChurchCRM allows you to create events that appear in your church calendar. Events can be **recurring** (such as a weekly church service) or **one-time** (such as a community outreach day).

Once an event is created, you can:
- Take attendance and review attendance metrics
- Manage child care security by checking children in and out of classrooms
- View event history and statistics

> **Tip:** Events appear on your church calendar and can be set to recur automatically.

---

## Event Types

Event types act as **templates** that define the default parameters for events you create. They let you standardize recurring formats (e.g., "Sunday Service", "Newcomers' Lunch", "Youth Night").

An event type can define:
- **Recurrence pattern** — How often this type of event repeats
- **Default start time**
- **Attendance count fields** — Free-text labels for tracking different groups of attendees (e.g., "Regular Churchgoers", "Newcomers", "Children")

### Creating an Event Type

1. Go to **Events → List Event Types**
2. Click **Add Event Type**
3. Fill in the name, recurrence pattern, default start time, and any attendance count labels
4. Click **Save Changes**

**Example:** Creating a "Newcomers' Lunch" event type:
- **Event Type Name:** Newcomers' Lunch
- **Recurrence Pattern:** None (it doesn't happen on a fixed schedule)
- **Default Start Time:** 12:30 PM
- **Attendance Counts:** Regular Churchgoers, Newcomers

---

## Creating an Event

1. Go to **Events → Add Church Event**
2. Select an event type
3. Enter the title, description, date range, and status
4. Click **Save Changes**

---

## Taking Attendance

### Adding an Existing Person to an Event

1. Go to **People → View All Persons**
2. Search for the person using the filter, then click **Add to Cart**
3. Repeat for all attendees
4. Click the cart icon in the header, then **Empty Cart to Event**
5. Select the event and click **Add Cart to Event**

### Adding a Visitor (New Person)

1. Go to **People → Add New Person**
2. Set the **Classification** to *Guest*
3. Click **Save**, then **Add to Cart**
4. Follow the same cart-to-event steps above

---

## Attendance Reports

Generate attendance history reports for any event:

1. Go to **Data/Reports → Reports Menu**
2. Find the **Event Attendance Reports** section
3. Select an event type and a person type to generate the report

---

## Child Check-In and Check-Out

ChurchCRM supports child security check-in during events. Each person in the system has a `PersonID` number visible in the URL of their record.

### Checking In a Child

1. Go to **Events → Check-in and Check-out**
2. Select the event
3. Enter the child's `PersonID` in the left field and the parent/guardian's `PersonID` in the right field
4. Click **Verify**, review the information, and click **CheckIn**

### Checking Out a Child

1. Go to **Events → Check-in and Check-out**
2. Select the event
3. Find the child in the checked-in list and click **Checkout**
4. Enter the parent/guardian's `PersonID`
5. Click **Verify CheckOut**, review, and click **Finalize CheckOut**

> **Tip:** Use **People → View All Persons** to look up a person's `PersonID` if you don't have it memorized.

---

## Related

- [Groups](./groups.md) — Groups whose members attend events
- [Search & Cart](./search-and-cart.md) — Adding people to events using the Cart
- [Sunday School](https://github.com/ChurchCRM/CRM/wiki/Sunday-School) — Sunday School class management
