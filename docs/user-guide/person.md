# Persons

Not surprisingly, a Person record represents a single individual. Person records can be grouped together into [Families](families.md), can belong to [Groups](Groups.md), can have [Properties](Properties.md), and can be made Users of the application.

## How do I find a specific Person?

On left menu, find the search box just above the _"Dashboard"_ heading. Enter a search string in this box, as you begin to type results will begin showing all of matches.

The system will return all Person records containing that search string in the first or last name.

Clicking on the Person's name will reveal the Person View which lists all information about that Person, including any Assigned Properties, Assigned Groups, and Notes.

This is a wild-card search, meaning the system is looking for that sequence of characters, no matter where in the first or last name they may appear. For example, searching for "Ian" will return all People records with the first name of "Ian" or Brian" (or anything else containing the characters "ian" in that order).

## Why is some of the information on the Person View in red text?

This is information inherited from an associated Family record. People can grouped into Families. People assigned to the same Family will likely share much of the same information -- the same Address, the same Phone, the same Email, etc. In these cases, this information only needs to be entered for the Family and all People assigned to that Family will "inherit" that information, unless the Person record in question has its own information.

For example, the Smith family has four members: John, Mary, Billy, and Sally. None of the four Person records have an address, phone, or email address listed, but this information is present in the Smith Family record. When Sally Smith's Person View is displayed, the system displays the address from the Family record. It uses red text to indicate that this information has been inherited. Say that Sally goes to college, and an address for her dorm room is entered in her Person record. Since she now has her own address, that address will display in black text on her Person View.

This makes it easy to change common information for all members of a Family. For a Family of 10 Person records, changing 10 addresses every time they move invites an error to creep in somewhere. By inheriting the Family information, the address needs to be changed in only one place.

## How do I add a new Person?

There are two ways to add a new Person:

From the left menu, click on _"Add a New Person"_
Complete the form Press _"Save"_ or _"Save and Add"_. The latter will add the person and return you to an empty form to add another person, which is handy for large amounts of data entry.

However, to enter a new Family and several Person records at once which you plan to assign to that Family, use the Family Editor.

## What is a Classification?

This defines the Person's role within the church. Common [Classifications](Classifications.md) are Member, Guest, Regular Attender, Non-Attender, etc.

## How do I enter a person's age?

You don't. ChurchCRM automatically calculates age based on the birth date given.

Age will be calculated as best it can with the information given. At minimum a Birth Year must be entered. Even if you don't know a person's birth year, you can always estimate until that information is available.

## How do I delete a Person?

Leaving old people in the database doesn't hurt anything and may help with historical record keeping. But if you have to...

1. Filter for the desired person, and bring up their Person View.

2. Select _"Delete this Record"_
> If this link doesn't appear, then you don't have permissions to delete records

3. Confirm the deletion

## What are Custom Person Fields?

Custom Person Fields is a powerful feature that allows you to add any fields that you need to use that do not come built-in with ChurchCRM. This feature allows you to, for example, add a Mentor to a person, or add an additional date (such as confirmation). The possibilities are endless.

## How do I use Custom Person Fields?

See the [Custom Fields](Custom Fields.md) help topic.

## How do I put a Person in the Cart?

See the [Cart](Cart.md) help topic.

## How do I assign a Person to a Group?

See the [Groups](Groups.md) help topic.

## How do I assign a Property to a Person?

See the [Properties](Properties.md) help topic.

## How do I add a Note to a Person?

See the [Notes](Notes.md) help topic.

## How do I track Finances of a Person?

See the [Finances](Finances.md) help topic.
