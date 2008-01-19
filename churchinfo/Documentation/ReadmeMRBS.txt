The ChurchInfo distribion includes a version of MRBS (Meeting
Room Booking System) that has been modified to work inside
ChurchInfo.  To enable this feature:

Install or upgrade to 1.2.10
Run the SQL script SQL/mrbs_setup.sql using PHPMyAdmin
    or other MySQL utility.
Log into ChurchInfo as an administrator, check to be sure the
    "Booking" menu has been added toward the right end of the menu.
Select Admin->Edit Users
Locate the current admin user and press the "Edit" link to get to
    the edit-user page.
Scroll down to the bottom
Set the Current Value to "True" for bEditMRBSBooking and 
    bAddMRBSResource
Press the "Save Settings" link at the very bottom of the page.

Select the Booking->Reservation menu option to get to the main
    page of MRBS.  The Admin link near the center of the top
    allows you to add areas and rooms.  Please refer to the
    MRBS help for further information.
