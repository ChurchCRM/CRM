
## Overview

[ChurchCRM](http://churchcrm.io) is is based on [ChurchInfo](http://churchdb.org) which was based on InfoCentral.

The software was developed by a team of volunteers, in their spare time, for the purpose of providing churches and with high-quality free software.

If you'd like to find out more or want to help out, checkout our [github.com repo](https://github.com/ChurchCRM/CRM/)

---

**ChurchCRM is currently still in development.**

We're progressing quickly, but the documentation still needs filling in, and there are a few rough edges.  The 1.0 release is planned to arrive in the next few months.

---

#### Host anywhere.

TODO

#### Great themes available.

---

## Installation

ChurchCRM is a PHP/MySQL application which runs on a web server, providing web pages so users can update and access the data in the database. You can run both the server and the browser on a single computer, but the real power of a web database application is visible when you have multiple users accessing the database from their own computers.

---


## Getting started

The application is based on the concepts of people who are members of families and are also members of common interest groups.

After you have installed the ChurchCRM application and can login, you are ready to configure the application.

The first thing to do is enter your church name, address, phone and email address into the Report Settings.

You can add a custom header to ChurchCRM by entering the HTML for the custom header in the General Settings. From the Admin menu, choose “Edit General Settings”. Near the bottom of the General Settings page, enter the HTML for the custom header into the field “sHeader”. Example: If you enter-
```html
<H2>My Church</H2>
```

ChurchCRM will display “My Church” in large, bold letters at the top of each page.

During the configuration stage, give some consideration to how you will use ChurchCRM:

1. What are the groups that you will use?
2. What properties do you need to record for people, families and groups?
3. Do you need to use custom fields?
4. Who needs access to the administration features?
5. Who should be given access to the Financial records?
6. Who can add or change records?

## Deploying

The documentation site that we've just built only uses static files so you'll be able to host it from pretty much anywhere. [GitHub project pages] and [Amazon S3] are good hosting options. Upload the contents of the entire `site` directory to wherever you're hosting your website from and you're done.

## Getting help

To get help with ChurchCRM, please use the [GitHub issues].

[GitHub issues]: https://github.com/ChurchCRM/CRM/issues

