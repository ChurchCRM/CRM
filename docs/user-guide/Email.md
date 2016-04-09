# Mass Email

How to send mass emails from ChurchCRM

Before you can send emails via ChurchCRM email functionality must first be configured. See the [Configuration](Configuration.md) page in the User Guide.

## To send email from an email client on your PC:

1. select and Add Group Members to Cart

2. go to _"Cart → List Cart Items"_.

3. Scroll to the bottom of the CartView and click _"Email Cart"_.

4. Emails will be forward to your external email client.

## Email via _Mailchimp_

The recommended method for mass emailing your members is via MailChimp.

- You must first signup and generate an api key via _Mailchimp_ http://kb.mailchimp.com/accounts/management/about-api-keys

- Add Mailchimp API key to `mailChimpApiKey` under _"Edit General Settings"_ accessible in the ⚙ (gear) menu.

- Enable newsletter under _"Family View"_ for each family that wishes to be part of the Newsletter

- You can then import the family's email via _Mailchimp_ subscribers
