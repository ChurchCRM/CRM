# Configuration

After you have installed your ChurchCRM application and can login, you are ready to configure the application.

## Report Settings

Within Report Settings you can enter your church information. You can also change the default text that is printed on reports. Under the Admin menu, choose _"Edit Report Settings"_. Enter your church name, address, phone and email address. Review the default report text and change the information as needed.

## Custome Header

You can add a custom header to ChurchCRM by entering the HTML for the custom header in the General Settings. From the Admin menu, choose _"Edit General Settings"_. Near the bottom of the General Settings page, enter the HTML for the custom header into the field `sHeader`. Example: If you enter ``<H2>My Church</H2>``, ChurchCRM will display "My Church" in large, bold letters at the top of each page.

## Configuring Email

ChurchCRM has a powerful email system, but it requires some effort to configure.

1. First, select _Admin → Edit General Settings_. Scroll about half-way down. Set bEmailSend to True. Set the next six values appropriately for your email system.

2. Next, select _Admin → Edit Users_. Click the _Edit_ link for yourself. Scroll down to the bottom section of the page. Set bEmailMailto to True to enable use of a standard email client. Set `sMailtoDelimiter` to `;` for using a Microsoft email client Outlook or Outlook Express, use `,` for any other client such as Thunderbird or Eudora. Set `bSendPHPMail` to `True` to enable direct mailing from ChurchInfo. Set `sFromEmailAddress` and `sFromName` to the values you want to use for your ChurchInfo messages.

3. To test your settings, select _Admin → Please select this option to register ChurchInfo after configuring_. Fill in the form and press _Send_. This will send a message to `info@churchcrm.io`. If it goes back to the welcome page your message was sent successfully. If an error appears that means there is a problem with your configuration settings.

4. After the first time you use the registration page, the menu option changes to _Admin → Update Registration_. You can send your registration message as many times as you want. The default values are taken from the settings in _Admin → Edit Report Settings_. You might want to update these first before going to the registration page.
