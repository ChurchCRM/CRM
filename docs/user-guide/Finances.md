# Finances

ChurchCRM has a finance function for tracking tithes, pledges and gifts.

The software keeps track of the following information:

*   **Pledge:** Promise of support, planning to donate a specific total amount.

*   **Deposit Slip:** Print a batch of donations on a standard bank deposit form for the bank.

*   **Payment:** A donation payment by cash, check, credit card, or bank draft.

*   **Reminder Statements:** Print letters to remind Families of their pledge and report progress of their payments for the current fiscal year.

*   **Tax Statements:** Print letters acknowledging donations over the calendar year for tax purposes.

## How do I enter a pledge?

There are two ways in which pledges can be added:

### From the Family View

1. When viewing a [family](families.md), a link for _"Add a new pledge"_ will be near the bottom of the screen.

2. Enter the information

3. click _"Save"_.

### Batch Entry

1. If you click _"Save and Add"_ rather than _"Save"_, the Pledge Editor will clear and prepare for another pledge entry.  

2. Select the next family making a pledge from the list, and fill in the rest of the pledge information.  Continue to click...

3. _"Save and Add"_ until all the pledges have been entered.

## How do I deposit donations?

When a batch of cash and check donations is received they are entered into ChurchCRM so the donating families receive credit against their pledges and also for tax purposes.

*   **Make a new deposit slip:** Select "New Deposit Slip (checks and cash)"  from the "Deposit" menu.

*   **Enter the deposits:** See below.

*   **Print the deposit slip:**
  1. Select _"Edit Deposit Slip"_ from the _"Deposit"_ menu.  

  2. Click on _"Generate PDF"_.  

  3. This PDF document will print on a standard bank deposit form.

* **Close the deposit:**
Select _"Close deposit slip"_ to close the deposit slip once the deposit has been packaged for the bank.

Automatic credit card and bank draft deposits are supported for churches and other organizations with an ECHO account.

* **Configure the automatic payments** For each family participating in the automatic payment program, in the Family view, click _"Add a new automatic payment"_.

* **Fill in the automatic payment information** Fill in all of the fields
in this form, except for the last six fields.  Of the last six fields, the first three must be filled for credit card transactions, and the last three must be filled for bank draft transactions.  Many of these fields start with default values taken from the Family record, but these values may be edited if appropriate.  Note that the date specified here is the first date that the payment is authorized, and the payment interval specifies the period of time in months until another payment is authorized.

* **Make a new deposit slip:** Select _"New Deposit Slip (credit card)"_ or _"New Deposit Slip (bank draft)"_ from the _"Deposit"_ menu.

* **Load the authorized payments** Press _"Load Authorized Transactions"_ to create payment records for all of the automatic transactions that have been authorized as of today.  Note that only credit card transactions or bank draft transactions will be loaded, depending on the nature of this deposit slip.  When the transactions are loaded the next payment date for each automatic payment is pushed forward by the specified interval in months.

* **Process payments** Press _"Run Transactions"_ to execute all of the transactions using the ECHO transaction server.  This may take some time, depending on the number of transactions and the speed of the network connection.  When the page refreshes note the status of each transaction in the _Cleared_ column.

* **Fix problems with payments that did not clear** Press _"Details"_ for any transactions that do not clear to see why the transaction failed.  Edit the automatic transaction record using the Family view to correct any errors.  After making corrections, repeat the _"Process payments"_ step to re-submit the failed transactions. Successful transactions will not be submitted again.

* **Close the deposit slip** Enable _"Close deposit slip"_ when finished  with this deposit slip, and press _"Save"_.

## How do I enter a payment?

Payments are very similar to pledges.  There are two ways in which payments can be added:

* **From the Family View:** When viewing a Family, a link for _"Add a new payment"_ will be near the bottom of the screen. Enter the information and click _"Save"_.

* **Batch Entry:**
  1. If you click _"Save and Add"_ rather than _"Save"_, the Payment Editor will clear and prepare for another pledge entry.  

  2. Select the next family making a payment from the list, and fill in the rest of the payment information.

  3. Continue to click _"Save and Add"_ until all the payments have been entered.
