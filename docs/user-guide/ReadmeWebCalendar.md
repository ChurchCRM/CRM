This version of ChurchInfo includes a portal feature which allows WebCalendar to run
within ChurchInfo.  Here are the installation steps required to enable this
feature.

1. Download the desired version of WebCalendar from
   https://sourceforge.net/projects/webcalendar/files

   Note that the current version that has been tested with ChurchInfo is 1.2.7

2. Copy the WebCalendar distribution to your server.  The default value for the WebCalendar location is 
    /WebCalendar-1.2.7, next to /churchinfo.  If you use a different location the global setting 
    sWebCalendarPath must be modified to reflect the actual location.

3. Update the ChurchInfo database to enable the WebCalendar option by importing this SQL file:
   SQL/AddWebCalendar.sql
   Note that this scripts adds one menu option and two general parameters that can be
   modified later using the menu configuration pages and the general settings pages
   respectively. 
   
4. Browse to the new WebCalendar installation directory and follow the automatic installation
   steps, specifying the same database used by ChurchInfo.  The WebCalendar system 
   will install its tables with the prefix webcal_ so they do not conflict with ChurchInfo.
   Once you get to step 4 in the WebCalendar installation you will be able to use WebCalendar 
   from within ChurchInfo.
   
5. Log into ChurchInfo as an administrator and select the new menu option Main->WebCalendar.

6. Note that the WebCalendar system may be run outside of ChurchInfo by browsing to its web directory.
   For example, if ChurchInfo is located in the standard director the URL will be
   http://www.domain.com/churchinfo and the WebCalendar system will be at http://www.domain.com/WebCalendar-1.2.7

The ChurchInfo WebCalendar integration works by copying the current ChurchInfo user's login information
into the WebCalendar database as well, and setting the session variables such that WebCalendar will
recognize the current user.  There may be a lot of configuration work required within WebCalendar to
make it do what you want.  For example, to fully enable the capability of scheduling Group meetings
in WebCalendar you will need to enable the public calendar and external users features of WebCalendar.
